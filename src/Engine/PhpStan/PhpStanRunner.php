<?php

declare(strict_types=1);

namespace PhpChecker\Engine\PhpStan;

use PhpChecker\Config\CheckerConfig;
use PhpChecker\Reporting\Violation;
use PhpChecker\Rules\RuleRegistry;
use RuntimeException;
use Symfony\Component\Process\Process;

final class PhpStanRunner
{
	/**
	 * @return list<Violation>
	 */
	public function run(CheckerConfig $config, RuleRegistry $ruleRegistry): array
	{
		$projectPath = $this->resolveProjectPath($config->getPaths()[0] ?? '.');

		$this->loadProjectAutoloader($projectPath);

		$configFile = $this->createTempConfig($config, $ruleRegistry, $projectPath);

		try {
			return $this->runPhpStan($projectPath, $configFile);
		} finally {
			@unlink($configFile);
		}
	}

	private function resolveProjectPath(string $path): string
	{
		$path = trim($path);

		if ($path === '') {
			$path = '.';
		}

		$realPath = realpath($path);

		if ($realPath === false) {
			throw new RuntimeException(
				sprintf('Path does not exist: %s', $path)
			);
		}

		if (!is_dir($realPath)) {
			throw new RuntimeException(
				sprintf('Path is not a directory: %s', $path)
			);
		}

		return $realPath;
	}

	private function loadProjectAutoloader(string $projectPath): void
	{
		$autoloadPath = $projectPath . '/vendor/autoload.php';

		if (file_exists($autoloadPath)) {
			require_once $autoloadPath;
		}
	}

	private function createTempConfig(CheckerConfig $config, RuleRegistry $ruleRegistry, string $projectPath): string
	{
		$neon = "parameters:\n";

		$neon .= sprintf(
			"    level: %d\n",
			$config->getLevel(),
		);

		$neon .= "    paths:\n";

		foreach ($config->getPaths() as $path) {
			$absolutePath = $this->resolvePath($projectPath, $path);

			if (!file_exists($absolutePath)) {
				throw new RuntimeException(
					sprintf(
						'Analysis path does not exist: %s',
						$absolutePath,
					),
				);
			}

			$neon .= "        - {$absolutePath}\n";
		}

		$excludePaths = $this->resolveExcludePaths($config->getExcludePaths(), $projectPath);

		if ($excludePaths !== []) {
			$neon .= "\n    excludePaths:\n";

			foreach ($excludePaths as $path) {
				$neon .= "        - {$path}\n";
			}
		}

		$neon .= "\nservices:\n";

		foreach ($ruleRegistry->all() as $ruleId => $ruleClass) {
			if (!$config->shouldUseRule($ruleId)) {
				continue;
			}

			$neon .= "    -\n";
			$neon .= "        class: {$ruleClass}\n";
			$neon .= "        tags:\n";
			$neon .= "            - phpstan.rules.rule\n";
		}

		$tmpFile = sys_get_temp_dir()
			. '/php-checker-phpstan-'
			. bin2hex(random_bytes(8))
			. '.neon';

		if (file_put_contents($tmpFile, $neon) === false) {
			throw new RuntimeException(
				'Unable to create temporary PHPStan configuration.',
			);
		}

		return $tmpFile;
	}

	/**
	 * @param list<string> $paths
	 *
	 * @return list<string>
	 */
	private function resolveExcludePaths(
		array $paths,
		string $projectPath,
	): array {
		$excludePaths = [];

		foreach ($paths as $path) {
			$absolutePath = $this->resolvePath(
				$projectPath,
				$path,
			);

			if (!is_dir($absolutePath)) {
				continue;
			}

			$excludePaths[] = $absolutePath;
		}

		return $excludePaths;
	}

	private function resolvePath(string $projectPath, string $path): string
	{
		if (str_starts_with($path, '/')) {
			return $path;
		}

		return rtrim($projectPath, '/')
			. '/'
			. ltrim($path, '/');
	}

	/**
	 * @return list<Violation>
	 */
	private function runPhpStan(string $projectPath, string $configFile): array
	{
		$phpStanBin = $this->findPhpStanBinary();

		$process = new Process(
			[
				$phpStanBin,
				'analyse',
				'--configuration=' . $configFile,
				'--error-format=json',
				'--no-progress',
			],
			$projectPath,
		);

		$process->run();

		$exitCode = $process->getExitCode();

		if ($exitCode > 1) {
			throw new RuntimeException(
				'PHPStan analysis failed: '
					. $process->getErrorOutput()
			);
		}

		return $this->parseJsonOutput($process->getOutput());
	}

	private function findPhpStanBinary(): string
	{
		$projectRoot = dirname(__DIR__, 3);

		$localBinary = $projectRoot . '/vendor/bin/phpstan';

		if (file_exists($localBinary)) {
			return $localBinary;
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
			'PHPStan binary not found. '
				. 'Please install PHPStan in the project.'
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

		$data = json_decode(
			$output,
			true,
			flags: JSON_THROW_ON_ERROR,
		);

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
