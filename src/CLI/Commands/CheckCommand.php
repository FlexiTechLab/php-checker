<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Commands;

use PhpChecker\Config\ConfigLoader;
use PhpChecker\Git\DiffMatcher;
use PhpChecker\Git\GitClient;
use PhpChecker\PhpChecker;
use PhpChecker\Reporting\ConsoleReporter;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'check',
	description: 'Analyze PHP source code using PHPStan and custom rules.',
)]
final class CheckCommand extends Command
{
	public function __construct(
		private readonly PhpChecker $checker,
		private readonly GitClient $gitClient,
		private readonly DiffMatcher $diffMatcher,
		private readonly ConsoleReporter $reporter,
		private readonly ConfigLoader $configLoader,
		private readonly ProjectRootResolver $projectRootResolver,
		private readonly RuleRegistry $ruleRegistry,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument(
			'path',
			InputArgument::OPTIONAL,
			'Path to the directory to analyze. Defaults to the current working directory.',
			'.',
		);

		$this->addOption(
			'diff',
			null,
			InputOption::VALUE_NONE,
			'Only report violations on added or modified lines.',
		);

		$this->addOption(
			'show-diff',
			null,
			InputOption::VALUE_NONE,
			'Display Git patch output in the terminal.',
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$path = (string) $input->getArgument('path');

		try {
			$projectRoot = $this->projectRootResolver->resolve($path);
			$config = $this->configLoader->load($projectRoot);

			$unknownRules = $this->ruleRegistry->getUnknownRules(
				$config['enabledRules'],
			);

			if ($unknownRules !== []) {
				throw new \RuntimeException(sprintf(
					'Unknown rule identifier(s) in %s: %s. Run "bin/php-checker rules:configure" to select valid rules.',
					$this->configLoader->getConfigPath($projectRoot),
					implode(', ', $unknownRules),
				));
			}
		} catch (\RuntimeException $exception) {
			$output->writeln(sprintf(
				'<error>%s</error>',
				$exception->getMessage(),
			));

			return Command::FAILURE;
		}

		try {
			$violations = $this->checker
				->path($path)
				->useRules($config['enabledRules'])
				->run();
		} catch (\RuntimeException $exception) {
			$output->writeln(sprintf(
				'<error>%s</error>',
				$exception->getMessage(),
			));

			return Command::FAILURE;
		}

		$showDiff = (bool) $input->getOption('show-diff');
		$filterDiff = (bool) $input->getOption('diff');

		$repositoryRoot = null;
		$diff = null;

		if ($filterDiff || $showDiff) {
			$repositoryRoot = $this->gitClient->getRepositoryRoot(
				getcwd() ?: '.',
			);

			$diff = $this->gitClient->getDiff($repositoryRoot);

			if ($filterDiff) {
				$violations = $this->diffMatcher->filter(
					$violations,
					$diff,
					$repositoryRoot,
				);
			}
		}

		$fileDiffs = [];

		if ($showDiff) {
			foreach ($violations as $violation) {
				$file = $violation->file;

				if (isset($fileDiffs[$file])) {
					continue;
				}

				$fileDiffs[$file] = $this->gitClient->getFileDiff(
					$repositoryRoot,
					$file,
				);
			}
		}

		return $this->reporter->report($violations, $output, $fileDiffs, $repositoryRoot ?? (getcwd() ?: '.'));
	}
}
