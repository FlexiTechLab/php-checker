<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

interface KeyReader
{
	/**
	 * Reads the next key press, blocking until one is available.
	 *
	 * @return Key|null `null` when the input is exhausted.
	 */
	public function read(): ?Key;
}
