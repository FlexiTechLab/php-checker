<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\Key;
use PhpChecker\CLI\Prompt\KeyPress;
use PhpChecker\CLI\Prompt\KeyReader;

/**
 * Replays a fixed list of keys and then behaves as if the input ended.
 *
 * Each entry may be a {@see Key} control key, a {@see KeyPress} or a raw
 * string that is interpreted as typed text (for example `' '` or `'p'`).
 */
final class ScriptedKeyReader implements KeyReader
{
	/** @var list<KeyPress> */
	private array $keys = [];

	/**
	 * @param list<Key|KeyPress|string> $keys
	 */
	public function __construct(array $keys)
	{
		foreach ($keys as $key) {
			$this->keys[] = match (true) {
				$key instanceof KeyPress => $key,
				$key instanceof Key => KeyPress::of($key),
				default => KeyPress::character($key),
			};
		}
	}

	public function read(): ?KeyPress
	{
		if ($this->keys === []) {
			return null;
		}

		return array_shift($this->keys);
	}
}
