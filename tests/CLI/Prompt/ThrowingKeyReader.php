<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\KeyPress;
use PhpChecker\CLI\Prompt\KeyReader;
use RuntimeException;

/**
 * Fails while reading so tests can verify the terminal is restored when an
 * exception escapes the prompt.
 */
final class ThrowingKeyReader implements KeyReader
{
	public function read(): ?KeyPress
	{
		throw new RuntimeException('read failed');
	}
}
