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
	public function report(array $violations, ?OutputInterface $output = null, array $fileDiffs = [], ?string $projectRoot = null): int
	{
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

		$count = count($violations);

		$finalOutput->writeln('');
		$finalOutput->writeln(sprintf(
			'<bg=red;fg=white;options=bold> FAIL </> <error>%d violation(s) found.</error>',
			$count,
		));
		$finalOutput->writeln('');

		foreach ($violations as $violation) {
			$identifier = $violation->identifier !== null
				? sprintf(
					'<comment>[%s]</comment> ',
					$violation->identifier,
				)
				: '';

			$file = $this->relativePath($violation->file, $projectRoot);

			$finalOutput->writeln(sprintf(
				'  <fg=cyan>%s:%d</>',
				$file,
				$violation->line,
			));

			$finalOutput->writeln(sprintf(
				'    <fg=red>✘</> %s%s',
				$identifier,
				$violation->message,
			));

			$fileDiff = $fileDiffs[$violation->file] ?? '';

			if ($fileDiff !== '') {
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
