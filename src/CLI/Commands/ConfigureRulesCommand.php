<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Commands;

use PhpChecker\Config\ConfigLoader;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'rules:configure',
	description: 'Configure the enabled PHP Checker rules',
)]
final class ConfigureRulesCommand extends Command
{
	public function __construct(
		private readonly RuleRegistry $ruleRegistry,
		private readonly ConfigLoader $configLoader,
		private readonly ProjectRootResolver $projectRootResolver,
	) {
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		try {
			$projectRoot = $this->projectRootResolver->resolve();
			$allRules = $this->ruleRegistry->all();

			if ($allRules === []) {
				$io->warning('No rules are registered.');

				return Command::SUCCESS;
			}

			/** @var array<string, string> $choices */
			$choices = [];

			foreach (array_keys($allRules) as $identifier) {
				$choices[$identifier] = $identifier;
			}

			$currentRules = $this->loadCurrentRules($projectRoot);

			// Keep only identifiers that are still registered, so the
			// selection persists even after rules are added or removed.
			$defaults = array_values(
				array_intersect($currentRules, array_keys($choices)),
			);

			$io->title('PHP Checker — Configure Rules');

			$io->text([
				'Select the rules to enable.',
				'Enter multiple choices separated by commas.',
				sprintf(
					'Press enter to keep the current selection (%d rule(s)).',
					count($defaults),
				),
				'Enter "none" to disable all rules.',
			]);

			$question = new ChoiceQuestion(
				'Enabled rules',
				$choices,
				$defaults === [] ? null : implode(',', $defaults),
			);

			$question->setMultiselect(true);

			$question->setValidator(
				function (mixed $answer) use ($choices): array {
					if ($answer === null) {
						return [];
					}

					$answer = trim((string) $answer);

					if ($answer === '' || strtolower($answer) === 'none') {
						return [];
					}

					$selected = [];

					foreach (explode(',', $answer) as $identifier) {
						$identifier = trim($identifier);

						if ($identifier === '') {
							continue;
						}

						if (! array_key_exists($identifier, $choices)) {
							throw new InvalidArgumentException(
								sprintf(
									'Unknown rule identifier: %s',
									$identifier,
								),
							);
						}

						$selected[] = $identifier;
					}

					return array_values(array_unique($selected));
				},
			);

			$selectedRules = $io->askQuestion($question);

			if (! is_array($selectedRules)) {
				$selectedRules = [];
			}

			/** @var list<string> $selectedRules */
			$selectedRules = array_values(
				array_intersect($selectedRules, array_keys($choices)),
			);

			$this->configLoader->save($projectRoot, $selectedRules);

			$io->success(sprintf(
				'Saved %d enabled rule(s) to %s',
				count($selectedRules),
				$this->configLoader->getConfigPath($projectRoot),
			));

			return Command::SUCCESS;
		} catch (RuntimeException $exception) {
			$io->error($exception->getMessage());

			return Command::FAILURE;
		}
	}

	/**
	 * @return list<string>
	 */
	private function loadCurrentRules(string $projectRoot): array
	{
		if (! $this->configLoader->hasConfig($projectRoot)) {
			return [];
		}

		// Invalid configuration is reported through the caller's error
		// handling instead of silently starting from an empty selection.
		return $this->configLoader->load($projectRoot)['enabledRules'];
	}
}
