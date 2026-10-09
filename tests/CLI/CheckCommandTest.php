<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI;

use PhpChecker\CLI\Application;
use PhpChecker\CLI\Commands\CheckCommand;
use PhpChecker\Config\ConfigLoader;
use PhpChecker\Git\DiffMatcher;
use PhpChecker\Git\GitClient;
use PhpChecker\PhpChecker;
use PhpChecker\Reporting\ConsoleReporter;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandTest extends TestCase
{
	private string $projectRoot;

	private string $previousDirectory;

	protected function setUp(): void
	{
		$this->previousDirectory = getcwd() ?: '.';
		$this->projectRoot = sys_get_temp_dir()
			. '/php-checker-check-'
			. bin2hex(random_bytes(6));

		mkdir($this->projectRoot);
		file_put_contents($this->projectRoot . '/composer.json', '{}');
		chdir($this->projectRoot);
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

	public function testApplicationCanBeInstantiated(): void
	{
		$application = new Application();

		$this->assertInstanceOf(Application::class, $application);
	}

	public function testMissingConfigurationFailsWithClearMessage(): void
	{
		$tester = new CommandTester($this->createCommand());

		$status = $tester->execute([]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString(
			'Configuration file not found',
			$tester->getDisplay(),
		);
	}

	public function testInvalidConfigurationFailsWithClearMessage(): void
	{
		$configLoader = new ConfigLoader();

		file_put_contents(
			$configLoader->getConfigPath($this->projectRoot),
			'{not valid json',
		);

		$tester = new CommandTester($this->createCommand());

		$status = $tester->execute([]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString(
			'Invalid JSON in configuration file',
			$tester->getDisplay(),
		);
	}

	public function testUnknownConfiguredRuleFailsWithClearMessage(): void
	{
		$configLoader = new ConfigLoader();
		$configLoader->save(
			$this->projectRoot,
			['phpdoc.method', 'does.not.exist'],
		);

		$tester = new CommandTester($this->createCommand());

		$status = $tester->execute([]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString(
			'Unknown rule identifier(s)',
			$tester->getDisplay(),
		);
		$this->assertStringContainsString(
			'does.not.exist',
			$tester->getDisplay(),
		);
	}

	private function createCommand(): CheckCommand
	{
		return new CheckCommand(
			new PhpChecker(),
			new GitClient(),
			new DiffMatcher(),
			new ConsoleReporter(),
			new ConfigLoader(),
			new ProjectRootResolver(),
			new RuleRegistry(),
		);
	}
}
