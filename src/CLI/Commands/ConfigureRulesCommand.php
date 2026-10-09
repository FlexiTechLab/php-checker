<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Commands;

use PhpChecker\CLI\Prompt\CheckboxPrompt;
use PhpChecker\CLI\Prompt\SttyTerminalMode;
use PhpChecker\CLI\Prompt\StreamKeyReader;
use PhpChecker\Config\ConfigLoader;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Support\ProjectRootResolver;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
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

			// Keep only identifiers that are still registered, so the
			// selection persists even after rules are added or removed.
			$defaults = array_values(array_intersect(
				$this->loadCurrentRules($projectRoot),
				array_keys($choices),
			));

			$io->title('PHP Checker — Configure Rules');

			$selectedRules = $this->selectRules(
				$input,
				$output,
				$io,
				$choices,
				$defaults,
			);

			// A null result means the interactive selection was cancelled.
			if ($selectedRules === null) {
				$io->warning('No changes were saved.');

				return Command::SUCCESS;
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
	 * Uses the native checkbox selector when the terminal supports it and
	 * falls back to the comma-separated question otherwise.
	 *
	 * @param array<string, string> $choices
	 * @param list<string>          $defaults
	 *
	 * @return list<string>|null
	 */
	private function selectRules(
		InputInterface $input,
		OutputInterface $output,
		SymfonyStyle $io,
		array $choices,
		array $defaults,
	): ?array {
		$stream = $this->resolveInputStream($input);

		if (
			\is_resource($stream)
			&& $this->supportsInteractiveSelection($input, $output, $stream)
		) {
			$io->text([
				'Select the rules to enable.',
				'Use the arrow keys to move, space to toggle a rule,',
				'"a" to toggle all, and enter to save.',
				'Press "q" to cancel without saving.',
			]);

			return (new CheckboxPrompt(new SttyTerminalMode()))->ask(
				$output,
				new StreamKeyReader($stream),
				$choices,
				$defaults,
			);
		}

		return $this->askChoiceQuestion($io, $choices, $defaults);
	}

	/**
	 * @param resource $stream
	 */
	private function supportsInteractiveSelection(InputInterface $input, OutputInterface $output, $stream): bool
	{
		if (! $input->isInteractive()) {
			return false;
		}

		if (! $output->isDecorated()) {
			return false;
		}

		if (! @stream_isatty($stream)) {
			return false;
		}

		return SttyTerminalMode::isSupported();
	}

	/**
	 * @return resource|null
	 */
	private function resolveInputStream(InputInterface $input)
	{
		if (
			$input instanceof StreamableInputInterface
			&& $input->getStream() !== null
		) {
			return $input->getStream();
		}

		return \defined('STDIN') ? \STDIN : null;
	}

	/**
	 * @param array<string, string> $choices
	 * @param list<string>          $defaults
	 *
	 * @return list<string>
	 */
	private function askChoiceQuestion(SymfonyStyle $io, array $choices, array $defaults): array
	{
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

		$selected = $io->askQuestion($question);

		if (! is_array($selected)) {
			return [];
		}

		/** @var list<string> $selected */
		return array_values(array_unique($selected));
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
