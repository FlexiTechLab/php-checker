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

	public function report(array $violations, ?OutputInterface $output = null): int
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

			$finalOutput->writeln(sprintf(
				'  <fg=cyan>%s:%d</>',
				$violation->file,
				$violation->line,
			));

			$finalOutput->writeln(sprintf(
				'    <fg=red>✘</> %s%s',
				$identifier,
				$violation->message,
			));

			$finalOutput->writeln('');
		}

		return Command::FAILURE;
	}
}
