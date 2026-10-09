<?php

declare(strict_types=1);

namespace PhpChecker\Reporting;

use Symfony\Component\Console\Output\OutputInterface;

interface Reporter
{
	/**
	 * @param list<Violation> $violations
	 * @param array<string, string> $fileDiffs
	 */
	public function report(array $violations, OutputInterface $output, array $fileDiffs = [], ?string $projectRoot = null): int;
}
