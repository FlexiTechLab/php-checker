<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI;

use PhpChecker\CLI\Commands\ConfigureRulesCommand;
use PhpChecker\Config\ConfigLoader;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigureRulesCommandTest extends TestCase
{
	private string $projectRoot;

	private string $previousDirectory;

	private ConfigLoader $configLoader;

	protected function setUp(): void
	{
		$this->previousDirectory = getcwd() ?: '.';
		$this->projectRoot = sys_get_temp_dir()
			. '/php-checker-configure-'
			. bin2hex(random_bytes(6));

		mkdir($this->projectRoot);
		file_put_contents($this->projectRoot . '/composer.json', '{}');
		chdir($this->projectRoot);

		$this->configLoader = new ConfigLoader();
	}

	protected function tearDown(): void
	{
		chdir($this->previousDirectory);

		$configPath = $this->projectRoot . '/php-checker.json';

		if (is_file($configPath)) {
			unlink($configPath);
		}

		unlink($this->projectRoot . '/composer.json');
		rmdir($this->projectRoot);
	}

	private function createCommand(): ConfigureRulesCommand
	{
		return new ConfigureRulesCommand(
			new RuleRegistry(),
			new ConfigLoader(),
			new ProjectRootResolver(),
		);
	}

	/**
	 * @return list<string>
	 */
	private function enabledRules(): array
	{
		return $this->configLoader->load($this->projectRoot)['enabledRules'];
	}

	public function testSavesMultipleSelectedRules(): void
	{
		$tester = new CommandTester($this->createCommand());
		$tester->setInputs([
			'phpdoc.method,typeDeclaration.return',
		]);

		$status = $tester->execute([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame(
			['phpdoc.method', 'typeDeclaration.return'],
			$this->enabledRules(),
		);
	}

	public function testPreservesPreviouslySelectedRulesWhenReopening(): void
	{
		$this->configLoader->save($this->projectRoot, [
			'phpdoc.method',
			'typeSafety.disallowMixed',
		]);

		$tester = new CommandTester($this->createCommand());
		$tester->setInputs(["\n"]);

		$status = $tester->execute([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame(
			['phpdoc.method', 'typeSafety.disallowMixed'],
			$this->enabledRules(),
		);
	}

	public function testCanDisableEveryRule(): void
	{
		$this->configLoader->save($this->projectRoot, ['phpdoc.method']);

		$tester = new CommandTester($this->createCommand());
		$tester->setInputs(["none\n"]);

		$status = $tester->execute([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame([], $this->enabledRules());
	}

	public function testMissingConfigurationStartsWithoutDefaults(): void
	{
		$tester = new CommandTester($this->createCommand());
		$tester->setInputs(["\n"]);

		$status = $tester->execute([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame([], $this->enabledRules());
	}

	public function testInvalidConfigurationFailsWithClearMessage(): void
	{
		file_put_contents(
			$this->configLoader->getConfigPath($this->projectRoot),
			'{not valid json',
		);

		$tester = new CommandTester($this->createCommand());
		$tester->setInputs(["\n"]);

		$status = $tester->execute([]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString(
			'Invalid JSON in configuration file',
			$tester->getDisplay(),
		);
	}

	public function testNonTtyInputFallsBackToCommaSeparatedPrompt(): void
	{
		$tester = new CommandTester($this->createCommand());
		$tester->setInputs(['phpdoc.method']);

		$status = $tester->execute([], ['interactive' => true]);

		$display = $tester->getDisplay();

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString(
			'Enter multiple choices separated by commas.',
			$display,
		);
		// The interactive checklist must not be used without a TTY.
		$this->assertStringNotContainsString('Use ↑/↓ to navigate', $display);
		$this->assertSame(['phpdoc.method'], $this->enabledRules());
	}

	public function testFallbackListsHumanReadableRuleNames(): void
	{
		$tester = new CommandTester($this->createCommand());
		$tester->setInputs(['phpdoc.method']);

		$tester->execute([], ['interactive' => true]);

		$display = $tester->getDisplay();

		$this->assertStringContainsString(
			'Require Method PHPDoc (phpdoc.method)',
			$display,
		);
		$this->assertStringContainsString(
			'Disallow Mixed Types (typeSafety.disallowMixed)',
			$display,
		);
	}

	public function testNonInteractiveEnvironmentKeepsCurrentSelection(): void
	{
		$this->configLoader->save($this->projectRoot, ['phpdoc.method']);

		$tester = new CommandTester($this->createCommand());

		$status = $tester->execute([], ['interactive' => false]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame(['phpdoc.method'], $this->enabledRules());
	}
}
