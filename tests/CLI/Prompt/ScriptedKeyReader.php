<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\Key;
use PhpChecker\CLI\Prompt\KeyReader;

/**
 * Replays a fixed list of keys and then behaves as if the input ended.
 */
final class ScriptedKeyReader implements KeyReader
{
	/** @var list<Key> */
	private array $keys;

	/**
	 * @param list<Key> $keys
	 */
	public function __construct(array $keys)
	{
		$this->keys = $keys;
	}

	public function read(): ?Key
	{
		if ($this->keys === []) {
			return null;
		}

		return array_shift($this->keys);
	}
}
