<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Commands;

use PhpChecker\PhpChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'check',
	description: 'Analyze PHP source code using PHPStan and custom rules.',
)]
final class CheckCommand extends Command
{
	public function __construct(
		private readonly PhpChecker $checker,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument(
			'path',
			InputArgument::OPTIONAL,
			'Path to the PHP project.',
			'.',
		);
	}

	protected function execute(
		InputInterface $input,
		OutputInterface $output,
	): int {
		$path = (string) $input->getArgument('path');

		return $this->checker
			->path($path)
			->run();
	}
}
