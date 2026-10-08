<?php

declare(strict_types=1);

namespace PhpChecker\Reporting;

use Symfony\Component\Console\Output\OutputInterface;

interface Reporter
{
	/**
	 * @param list<Violation> $violations
	 */
	public function report(array $violations, OutputInterface $output): int;
}
