<?php

declare(strict_types=1);

namespace PhpChecker\CLI;

use PhpChecker\CLI\Commands\CheckCommand;
use PhpChecker\CLI\Commands\ConfigureRulesCommand;
use PhpChecker\Config\ConfigLoader;
use PhpChecker\Engine\PhpStan\PhpStanRunner;
use PhpChecker\Git\DiffMatcher;
use PhpChecker\Git\GitClient;
use PhpChecker\PhpChecker;
use PhpChecker\Reporting\ConsoleReporter;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Application
{
	public function run(
		?InputInterface $input = null,
		?OutputInterface $output = null,
	): int {
		$application = new SymfonyApplication(
			'PHP Checker',
			'0.1.0',
		);

		$ruleRegistry = new RuleRegistry();
		$configLoader = new ConfigLoader();
		$projectRootResolver = new ProjectRootResolver();

		$checker = new PhpChecker(
			runner: new PhpStanRunner($projectRootResolver),
			ruleRegistry: $ruleRegistry,
		);

		$application->addCommands([
			new ConfigureRulesCommand(
				$ruleRegistry,
				$configLoader,
				$projectRootResolver,
			),
			new CheckCommand(
				$checker,
				new GitClient(),
				new DiffMatcher(),
				new ConsoleReporter(),
				$configLoader,
				$projectRootResolver,
				$ruleRegistry,
			),
		]);

		return $application->run($input, $output);
	}
}
