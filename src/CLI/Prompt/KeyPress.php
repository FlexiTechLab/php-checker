<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * A decoded key press.
 *
 * For printable input the {@see $key} is {@see Key::Character} and
 * {@see $character} holds the typed text (which may be a single byte, or a
 * space). Control keys carry an empty character.
 */
final class KeyPress
{
	private function __construct(
		public readonly Key $key,
		public readonly string $character = '',
	) {}

	public static function of(Key $key): self
	{
		return new self($key);
	}

	public static function character(string $character): self
	{
		return new self(Key::Character, $character);
	}

	public function isCharacter(): bool
	{
		return $this->key === Key::Character;
	}
}
