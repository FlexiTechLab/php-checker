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
		$finalOutput = $output ?? $this->output ?? new ConsoleOutput();

		if ($violations === []) {
			$finalOutput->writeln("No violations found.");
			return Command::SUCCESS;
		}

		foreach ($violations as $violation) {
			$identifier = $violation->identifier !== null
				? "[{$violation->identifier}] "
				: '';

			$finalOutput->writeln("{$violation->file}:{$violation->line}");
			$finalOutput->writeln("{$identifier}{$violation->message}");
		}

		return Command::FAILURE;
	}
}
