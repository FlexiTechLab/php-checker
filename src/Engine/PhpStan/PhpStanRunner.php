<?php

declare(strict_types=1);

namespace PhpChecker\Engine\PhpStan;

use JsonException;
use PhpChecker\Config\CheckerConfig;
use PhpChecker\Reporting\Violation;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use RuntimeException;
use Symfony\Component\Process\Process;

final class PhpStanRunner
{
	public function __construct(
		private readonly ?ProjectRootResolver $projectRootResolver = null,
	) {}

	/**
	 * @return list<Violation>
	 */
	public function run(CheckerConfig $config, RuleRegistry $ruleRegistry): array
	{
		$projectRoot = $this->resolveProjectRoot($config);

		$this->loadProjectAutoloader($projectRoot);

		$configFile = $this->createTempConfig($config, $ruleRegistry, $projectRoot);

		try {
			return $this->runPhpStan($projectRoot, $configFile);
		} finally {
			if (is_file($configFile)) {
				@unlink($configFile);
			}
		}
	}

	/**
	 * Resolves the project root used for the Composer autoloader, the
	 * PHPStan binary and relative exclude paths.
	 */
	private function resolveProjectRoot(CheckerConfig $config): string
	{
		$firstPath = trim($config->getPaths()[0] ?? '.');

		if ($firstPath === '') {
			$firstPath = '.';
		}

		$realPath = realpath($firstPath);

		if ($realPath === false) {
			throw new RuntimeException(
				sprintf('Analysis path does not exist: %s', $firstPath),
			);
		}

		$startDirectory = is_dir($realPath)
			? $realPath
			: dirname($realPath);

		$resolver = $this->projectRootResolver ?? new ProjectRootResolver();

		try {
			return $resolver->resolve($startDirectory);
		} catch (RuntimeException) {
			// Analysis of a directory without a Composer manifest is still
			// possible; fall back to the directory that was requested.
			return $startDirectory;
		}
	}

	private function loadProjectAutoloader(string $projectRoot): void
	{
		$autoloadPath = $projectRoot . '/vendor/autoload.php';

		if (file_exists($autoloadPath)) {
			require_once $autoloadPath;
		}
	}

	private function createTempConfig(CheckerConfig $config, RuleRegistry $ruleRegistry, string $projectRoot): string
	{
		$lines = [];

		$lines[] = 'parameters:';
		// Tell PHPStan that this configuration provides its own complete
		// ruleset. Without a `level`, only the rules registered below run;
		// PHPStan's built-in rules stay disabled.
		$lines[] = '    customRulesetUsed: true';

		$level = $config->getLevel();

		if ($level !== null) {
			$lines[] = sprintf('    level: %d', $level);
		}

		$lines[] = '    paths:';

		foreach ($config->getPaths() as $path) {
			$lines[] = '        - ' . $this->formatNeonValue(
				$this->resolveAnalysisPath($path),
			);
		}

		$excludePaths = $this->resolveExcludePaths(
			$config->getExcludePaths(),
			$projectRoot,
		);

		if ($excludePaths !== []) {
			$lines[] = '    excludePaths:';

			foreach ($excludePaths as $path) {
				$lines[] = '        - ' . $this->formatNeonValue($path);
			}
		}

		// Built-in analysis only runs when a level was explicitly
		// requested. In that mode the built-in counterparts of the enabled
		// custom rules are suppressed so the same problem is not reported
		// twice. Nothing else is suppressed.
		$suppressedIdentifiers = $level === null
			? []
			: $ruleRegistry->getSuppressedBuiltInIdentifiers($config);

		if ($suppressedIdentifiers !== []) {
			$lines[] = '    reportUnmatchedIgnoredErrors: false';
			$lines[] = '    ignoreErrors:';

			foreach ($suppressedIdentifiers as $identifier) {
				$lines[] = '        -';
				$lines[] = '            identifier: ' . $this->formatNeonValue(
					$identifier,
				);
			}
		}

		$enabledRules = $ruleRegistry->getEnabledRules($config);

		if ($enabledRules !== []) {
			$lines[] = '';
			$lines[] = 'services:';

			foreach ($enabledRules as $ruleClass) {
				$lines[] = '    -';
				$lines[] = '        class: ' . $this->formatNeonValue($ruleClass);
				$lines[] = '        tags:';
				$lines[] = '            - phpstan.rules.rule';
			}
		}

		$neon = implode("\n", $lines) . "\n";

		$tempFile = $this->createTempFile();
		$configFile = $tempFile . '.neon';

		if (! @rename($tempFile, $configFile)) {
			@unlink($tempFile);

			throw new RuntimeException(
				sprintf(
					'Unable to prepare the temporary PHPStan configuration: %s',
					$configFile,
				),
			);
		}

		if (file_put_contents($configFile, $neon) === false) {
			@unlink($configFile);

			throw new RuntimeException(
				sprintf(
					'Unable to write the temporary PHPStan configuration: %s',
					$configFile,
				),
			);
		}

		return $configFile;
	}

	private function createTempFile(): string
	{
		$tempFile = tempnam(
			sys_get_temp_dir(),
			'php-checker-phpstan-',
		);

		if ($tempFile === false) {
			throw new RuntimeException(
				'Unable to create a temporary file for the PHPStan configuration.',
			);
		}

		return $tempFile;
	}

	private function resolveAnalysisPath(string $path): string
	{
		$path = trim($path);

		if ($path === '') {
			$path = '.';
		}

		$realPath = realpath($path);

		if ($realPath === false) {
			throw new RuntimeException(
				sprintf('Analysis path does not exist: %s', $path),
			);
		}

		return $realPath;
	}

	/**
	 * @param list<string> $paths
	 *
	 * @return list<string>
	 */
	private function resolveExcludePaths(array $paths, string $projectRoot): array
	{
		$excludePaths = [];

		foreach ($paths as $path) {
			$absolutePath = $this->resolvePath($projectRoot, $path);

			if (! is_dir($absolutePath)) {
				continue;
			}

			$excludePaths[] = $absolutePath;
		}

		return $excludePaths;
	}

	private function resolvePath(string $projectRoot, string $path): string
	{
		if (str_starts_with($path, '/')) {
			return $path;
		}

		return rtrim($projectRoot, '/')
			. '/'
			. ltrim($path, '/');
	}

	private function formatNeonValue(string $value): string
	{
		if (
			$value !== ''
			&& preg_match('/^[A-Za-z0-9_.\/\\\\:-]+$/', $value) === 1
		) {
			return $value;
		}

		return "'" . str_replace(
			['\\', "'"],
			['\\\\', "\\'"],
			$value,
		) . "'";
	}

	/**
	 * @return list<Violation>
	 */
	private function runPhpStan(string $projectRoot, string $configFile): array
	{
		$phpStanBin = $this->findPhpStanBinary($projectRoot);

		$process = new Process(
			[
				$phpStanBin,
				'analyse',
				'--configuration=' . $configFile,
				'--error-format=json',
				'--no-progress',
			],
			$projectRoot,
		);

		$process->run();

		$exitCode = $process->getExitCode();

		if ($exitCode === null) {
			throw new RuntimeException(
				'PHPStan did not terminate correctly.',
			);
		}

		if ($exitCode > 1) {
			$error = trim($process->getErrorOutput());

			if ($error === '') {
				$error = trim($process->getOutput());
			}

			throw new RuntimeException(
				sprintf(
					'PHPStan analysis failed (exit code %d): %s',
					$exitCode,
					$error,
				),
			);
		}

		return $this->parseJsonOutput($process->getOutput());
	}

	private function findPhpStanBinary(string $projectRoot): string
	{
		$candidates = array_unique([
			$projectRoot . '/vendor/bin/phpstan',
			(getcwd() ?: '.') . '/vendor/bin/phpstan',
			dirname(__DIR__, 3) . '/vendor/bin/phpstan',
		]);

		foreach ($candidates as $candidate) {
			if (is_file($candidate)) {
				return $candidate;
			}
		}

		$process = new Process(['which', 'phpstan']);

		$process->run();

		if ($process->getExitCode() === 0) {
			$binary = trim($process->getOutput());

			if ($binary !== '') {
				return $binary;
			}
		}

		throw new RuntimeException(
			'PHPStan binary not found. Install PHPStan in the project '
				. '(vendor/bin/phpstan) or make "phpstan" available on the PATH.',
		);
	}

	/**
	 * @return list<Violation>
	 */
	private function parseJsonOutput(string $output): array
	{
		if (trim($output) === '') {
			return [];
		}

		try {
			$data = json_decode(
				$output,
				true,
				512,
				JSON_THROW_ON_ERROR,
			);
		} catch (JsonException $exception) {
			throw new RuntimeException(
				sprintf(
					'Unable to parse PHPStan JSON output: %s',
					$exception->getMessage(),
				),
				previous: $exception,
			);
		}

		if (! is_array($data)) {
			throw new RuntimeException(
				'Unexpected PHPStan output: expected a JSON object.',
			);
		}

		$violations = [];

		foreach ($data['files'] ?? [] as $file => $fileData) {
			foreach ($fileData['messages'] ?? [] as $message) {
				$violations[] = new Violation(
					file: $file,
					line: (int) ($message['line'] ?? 1),
					message: (string) ($message['message'] ?? ''),
					identifier: isset($message['identifier'])
						? (string) $message['identifier']
						: null,
				);
			}
		}

		return $violations;
	}
}
