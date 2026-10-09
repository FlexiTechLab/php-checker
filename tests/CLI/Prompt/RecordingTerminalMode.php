<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\TerminalMode;

/**
 * Records the terminal mode transitions so tests can assert that the
 * terminal is always restored.
 */
final class RecordingTerminalMode implements TerminalMode
{
	/** @var list<string> */
	public array $events = [];

	public function enter(): void
	{
		$this->events[] = 'entered';
	}

	public function restore(): void
	{
		$this->events[] = 'restored';
	}
}
