<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\Key;
use PhpChecker\CLI\Prompt\KeyReader;
use RuntimeException;

/**
 * Fails while reading so tests can verify the terminal is restored when an
 * exception escapes the prompt.
 */
final class ThrowingKeyReader implements KeyReader
{
	public function read(): ?Key
	{
		throw new RuntimeException('read failed');
	}
}
