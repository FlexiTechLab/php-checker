<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Engine\PhpStan;

use FilesystemIterator;
use PhpChecker\Engine\PhpStan\PhpStanRunner;
use PhpChecker\PhpChecker;
use PhpChecker\Reporting\Violation;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class PhpStanRunnerTest extends TestCase
{
	private string $projectRoot;

	private string $previousDirectory;

	protected function setUp(): void
	{
		$this->previousDirectory = getcwd() ?: '.';
		$this->projectRoot = sys_get_temp_dir()
			. '/php-checker-phpstan-runner-'
			. bin2hex(random_bytes(6));

		mkdir($this->projectRoot . '/src', 0o777, true);
		file_put_contents($this->projectRoot . '/composer.json', '{}');
		file_put_contents(
			$this->projectRoot . '/src/Example.php',
			"<?php\n",
		);

		$this->writeFakePhpStan($this->defaultFakePhpStanBody());
	}

	protected function tearDown(): void
	{
		chdir($this->previousDirectory);

		$this->removeDirectory($this->projectRoot);
	}

	public function testEnabledRuleIsWrittenToConfigAndViolationIsParsed(): void
	{
		$violations = $this->runChecker(['typeDeclaration.parameter']);

		$configuration = $this->capturedNeonConfiguration();

		$this->assertStringContainsString(
			'customRulesetUsed: true',
			$configuration,
		);
		$this->assertStringNotContainsString('level:', $configuration);
		$this->assertStringContainsString(
			'class: PhpChecker\Rules\TypeDeclaration\RequireParameterTypeRule',
			$configuration,
		);
		$this->assertStringNotContainsString(
			'RequireReturnTypeRule',
			$configuration,
		);
		// Built-in analysis is off, so nothing needs suppressing.
		$this->assertStringNotContainsString('ignoreErrors:', $configuration);
		$this->assertStringNotContainsString('missingType.', $configuration);

		$this->assertCount(1, $violations);
		$this->assertInstanceOf(Violation::class, $violations[0]);
		$this->assertSame('src/Example.php', $violations[0]->file);
		$this->assertSame(5, $violations[0]->line);
		$this->assertSame(
			'typeDeclaration.parameter',
			$violations[0]->identifier,
		);
	}

	public function testEmptyAllowlistRegistersNoRules(): void
	{
		$violations = $this->runChecker([]);

		$configuration = $this->capturedNeonConfiguration();

		$this->assertStringContainsString(
			'customRulesetUsed: true',
			$configuration,
		);
		$this->assertStringNotContainsString('services:', $configuration);
		$this->assertStringNotContainsString('ignoreErrors:', $configuration);
		$this->assertStringNotContainsString('missingType.', $configuration);
		$this->assertCount(1, $violations);
	}

	public function testEverySelectedRuleIsRegistered(): void
	{
		$registry = new RuleRegistry();

		$this->runChecker(array_keys($registry->all()));

		$configuration = $this->capturedNeonConfiguration();

		foreach ($registry->all() as $ruleClass) {
			$this->assertStringContainsString($ruleClass, $configuration);
		}

		$this->assertStringNotContainsString('ignoreErrors:', $configuration);
	}

	public function testExplicitLevelOptsIntoBuiltInsAndSuppressesOverlaps(): void
	{
		$this->runChecker(
			['typeDeclaration.parameter', 'typeDeclaration.return'],
			level: 8,
		);

		$configuration = $this->capturedNeonConfiguration();

		$this->assertStringContainsString(
			'customRulesetUsed: true',
			$configuration,
		);
		$this->assertStringContainsString('level: 8', $configuration);
		$this->assertStringContainsString(
			'reportUnmatchedIgnoredErrors: false',
			$configuration,
		);
		$this->assertStringContainsString(
			'identifier: missingType.parameter',
			$configuration,
		);
		$this->assertStringContainsString(
			'identifier: missingType.return',
			$configuration,
		);
	}

	public function testNonOverlappingRuleDoesNotSuppressBuiltIns(): void
	{
		$this->runChecker(['phpdoc.method'], level: 8);

		$configuration = $this->capturedNeonConfiguration();

		$this->assertStringContainsString('level: 8', $configuration);
		$this->assertStringContainsString(
			'class: PhpChecker\Rules\Documentation\RequireMethodPhpDocRule',
			$configuration,
		);
		$this->assertStringNotContainsString('missingType.', $configuration);
	}

	public function testSubdirectoryAnalysisPathIsResolvedOnce(): void
	{
		$this->runChecker(
			['typeDeclaration.parameter'],
			$this->projectRoot . '/src',
		);

		$configuration = $this->capturedNeonConfiguration();

		$this->assertStringContainsString(
			'- ' . $this->projectRoot . '/src',
			$configuration,
		);
		$this->assertStringNotContainsString(
			$this->projectRoot . '/src/src',
			$configuration,
		);
	}

	public function testFailingPhpStanProcessRaisesClearException(): void
	{
		$this->writeFakePhpStan(
			"fwrite(STDERR, 'boom');\n" . "exit(2);\n",
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'PHPStan analysis failed (exit code 2): boom',
		);

		$this->runChecker(['typeDeclaration.parameter']);
	}

	public function testInvalidJsonOutputRaisesClearException(): void
	{
		$this->writeFakePhpStan("echo 'this is not json';\n");

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'Unable to parse PHPStan JSON output',
		);

		$this->runChecker(['typeDeclaration.parameter']);
	}

	/**
	 * @param list<string> $useRules
	 *
	 * @return list<Violation>
	 */
	private function runChecker(
		array $useRules,
		?string $path = null,
		?int $level = null,
	): array {
		$runner = new PhpStanRunner(new ProjectRootResolver());
		$checker = new PhpChecker($runner, new RuleRegistry());

		$checker = $checker
			->path($path ?? $this->projectRoot)
			->useRules($useRules);

		if ($level !== null) {
			$checker = $checker->level($level);
		}

		return $checker->run();
	}

	private function capturedNeonConfiguration(): string
	{
		$capturePath = $this->projectRoot . '/captured.neon';

		$this->assertFileExists($capturePath);

		return (string) file_get_contents($capturePath);
	}

	private function writeFakePhpStan(string $body): void
	{
		$binDirectory = $this->projectRoot . '/vendor/bin';

		if (! is_dir($binDirectory)) {
			mkdir($binDirectory, 0o777, true);
		}

		$binary = $binDirectory . '/phpstan';

		file_put_contents($binary, "#!/usr/bin/env php\n<?php\n" . $body);
		chmod($binary, 0o755);
	}

	private function defaultFakePhpStanBody(): string
	{
		return <<<'PHP'
			$config = null;

			foreach ($argv as $argument) {
				if (str_starts_with($argument, '--configuration=')) {
					$config = substr($argument, strlen('--configuration='));
				}
			}

			if ($config !== null && is_file($config)) {
				copy($config, getcwd() . '/captured.neon');
			}

			echo json_encode([
				'totals' => ['errors' => 0, 'file_errors' => 1],
				'files' => [
					'src/Example.php' => [
						'errors' => 1,
						'messages' => [
							[
								'message' => 'Function needsTypes() parameter $id must declare a type.',
								'line' => 5,
								'ignorable' => true,
								'identifier' => 'typeDeclaration.parameter',
							],
						],
					],
				],
				'errors' => [],
			]);
			PHP;
	}

	private function removeDirectory(string $directory): void
	{
		if (! is_dir($directory)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$directory,
				FilesystemIterator::SKIP_DOTS,
			),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($items as $item) {
			if ($item->isDir()) {
				rmdir($item->getPathname());
			} else {
				unlink($item->getPathname());
			}
		}

		rmdir($directory);
	}
}
