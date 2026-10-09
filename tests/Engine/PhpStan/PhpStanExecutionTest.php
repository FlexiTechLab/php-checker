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

/**
 * Runs the real PHPStan binary to prove that the generated configuration
 * executes only the rules enabled in the allowlist.
 */
final class PhpStanExecutionTest extends TestCase
{
	private string $projectRoot;

	protected function setUp(): void
	{
		$this->projectRoot = sys_get_temp_dir()
			. '/php-checker-phpstan-exec-'
			. bin2hex(random_bytes(6));

		mkdir($this->projectRoot . '/src', 0o777, true);
		file_put_contents($this->projectRoot . '/composer.json', '{}');
		file_put_contents(
			$this->projectRoot . '/src/Fixture.php',
			<<<'PHP'
				<?php

				declare(strict_types=1);

				function needsTypes($id)
				{
					return $id;
				}

				class Service
				{
					public function run($input)
					{
						return $input;
					}
				}
				PHP,
		);
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->projectRoot);
	}

	public function testOnlyEnabledCustomRuleIsReported(): void
	{
		$identifiers = $this->violationIdentifiers(
			['typeDeclaration.parameter'],
		);

		$this->assertContains('typeDeclaration.parameter', $identifiers);
		$this->assertNotContains('typeDeclaration.return', $identifiers);
		$this->assertNotContains('phpdoc.method', $identifiers);
		$this->assertNoBuiltInIdentifiers($identifiers);
	}

	public function testDisabledCustomRuleNeverRuns(): void
	{
		$identifiers = $this->violationIdentifiers(['phpdoc.method']);

		$this->assertContains('phpdoc.method', $identifiers);
		$this->assertNotContains('typeDeclaration.parameter', $identifiers);
		$this->assertNotContains('typeDeclaration.return', $identifiers);
		$this->assertNoBuiltInIdentifiers($identifiers);
	}

	public function testEmptyAllowlistReportsNothing(): void
	{
		$this->assertSame([], $this->violationIdentifiers([]));
	}

	public function testExplicitLevelAddsBuiltInsWithoutDuplicates(): void
	{
		$identifiers = $this->violationIdentifiers(
			['typeDeclaration.parameter'],
			level: 8,
		);

		// The enabled custom rule still reports.
		$this->assertContains('typeDeclaration.parameter', $identifiers);
		// Its built-in counterpart is suppressed to avoid duplicates.
		$this->assertNotContains('missingType.parameter', $identifiers);
		// Built-in analysis that is not covered by an enabled rule is kept.
		$this->assertContains('missingType.return', $identifiers);
		// The unselected custom rule does not run.
		$this->assertNotContains('typeDeclaration.return', $identifiers);
	}

	/**
	 * @param list<string> $useRules
	 *
	 * @return list<string>
	 */
	private function violationIdentifiers(
		array $useRules,
		?int $level = null,
	): array {
		$runner = new PhpStanRunner(new ProjectRootResolver());
		$checker = new PhpChecker($runner, new RuleRegistry());
		$checker = $checker
			->path($this->projectRoot)
			->useRules($useRules);

		if ($level !== null) {
			$checker = $checker->level($level);
		}

		return array_map(
			static fn (Violation $violation): string => (string) $violation->identifier,
			$checker->run(),
		);
	}

	/**
	 * @param list<string> $identifiers
	 */
	private function assertNoBuiltInIdentifiers(array $identifiers): void
	{
		foreach ($identifiers as $identifier) {
			$this->assertStringStartsNotWith(
				'missingType.',
				$identifier,
			);
		}
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
