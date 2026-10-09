<?php

declare(strict_types=1);

namespace PhpChecker\CLI;

use PhpChecker\CLI\Commands\CheckCommand;
use PhpChecker\Engine\PhpStan\PhpStanRunner;
use PhpChecker\Git\DiffMatcher;
use PhpChecker\Git\GitClient;
use PhpChecker\PhpChecker;
use PhpChecker\Reporting\ConsoleReporter;
use PhpChecker\Rules\RuleRegistry;
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

		$checker = new PhpChecker(
			runner: new PhpStanRunner(),
			ruleRegistry: new RuleRegistry()
		);

		$application->addCommand(
			new CheckCommand(
				$checker,
				new GitClient(),
				new DiffMatcher(),
				new ConsoleReporter(),
			),
		);

		return $application->run($input, $output);
	}
}
