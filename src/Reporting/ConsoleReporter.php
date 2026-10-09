<?php

declare(strict_types=1);

namespace PhpChecker\Reporting;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleReporter implements Reporter
{
	public function __construct(
		private ?OutputInterface $output = null,
	) {}

	/**
	 * @param list<Violation> $violations
	 * @param array<string, string> $fileDiffs
	 */
	public function report(
		array $violations,
		?OutputInterface $output = null,
		array $fileDiffs = [],
		?string $projectRoot = null,
	): int {
		$finalOutput = $output
			?? $this->output
			?? new ConsoleOutput();

		if ($violations === []) {
			$finalOutput->writeln('');
			$finalOutput->writeln(
				'<bg=green;fg=white;options=bold> PASS </> <info>No violations found.</info>',
			);
			$finalOutput->writeln('');

			return Command::SUCCESS;
		}

		//  Group violations by file so each file is displayed only once.
		/** @var array<string, list<Violation>> $violationsByFile */
		$violationsByFile = [];

		foreach ($violations as $violation) {
			$violationsByFile[$violation->file][] = $violation;
		}

		$finalOutput->writeln('');
		$finalOutput->writeln(sprintf(
			'<bg=red;fg=white;options=bold> FAIL </> <error>%d violation(s) found across %d file(s).</error>',
			count($violations),
			count($violationsByFile),
		));
		$finalOutput->writeln('');

		foreach ($violationsByFile as $file => $fileViolations) {
			$displayPath = $this->relativePath($file, $projectRoot);

			// Display the file path once.
			$finalOutput->writeln(sprintf(
				'<fg=cyan>%s</>',
				$displayPath,
			));

			// Display every violation belonging to this file.
			foreach ($fileViolations as $violation) {
				$identifier = $violation->identifier !== null
					? sprintf('[%s]', $violation->identifier)
					: '';

				$finalOutput->writeln(sprintf(
					'  <fg=red>✘</> <fg=yellow>Line %d</> %s',
					$violation->line,
					$identifier,
				));

				$finalOutput->writeln(sprintf(
					'      %s',
					$violation->message,
				));

				$finalOutput->writeln('');
			}

			// Display the file diff once, after listing all violations.
			$fileDiff = $fileDiffs[$file] ?? '';

			if ($fileDiff !== '') {
				$finalOutput->writeln('');
				$this->writeDiff($finalOutput, $fileDiff);
			}

			$finalOutput->writeln('');
		}

		return Command::FAILURE;
	}

	private function writeDiff(OutputInterface $output, string $diff): void
	{
		if ($diff === '') {
			return;
		}

		$output->writeln(
			'<comment>-------- begin diff --------</comment>',
		);

		foreach (explode("\n", rtrim($diff, "\n")) as $line) {
			if (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
				$output->writeln('<fg=green>' . $line . '</>');
			} elseif (str_starts_with($line, '-') && !str_starts_with($line, '---')) {
				$output->writeln('<fg=red>' . $line . '</>');
			} else {
				$output->writeln($line);
			}
		}

		$output->writeln(
			'<comment>--------- end diff ---------</comment>',
		);
	}

	private function relativePath(string $file, ?string $projectRoot): string
	{
		if ($projectRoot === null || $projectRoot === '') {
			return $file;
		}

		$root = realpath($projectRoot);

		if ($root === false) {
			return $file;
		}

		$root = rtrim(str_replace('\\', '/', $root), '/');
		$file = str_replace('\\', '/', $file);

		if (
			!str_starts_with($file, '/')
			&& preg_match('/^[A-Za-z]:\//', $file) !== 1
		) {
			$file = $root . '/' . ltrim($file, '/');
		}

		$prefix = $root . '/';

		if (str_starts_with($file, $prefix)) {
			return substr($file, strlen($prefix));
		}

		return $file;
	}
}
