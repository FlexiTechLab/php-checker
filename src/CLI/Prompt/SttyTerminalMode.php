<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

use RuntimeException;

/**
 * Switches the terminal into raw mode using the same `stty` mechanism that
 * Symfony Console relies on for its own interactive questions.
 *
 * Raw mode is required to receive key presses (including arrow keys) as
 * they happen instead of line by line. `isig` is disabled as well so that
 * Ctrl-C is delivered as a normal byte and can be handled as a cancellation
 * instead of killing the process and leaving the terminal in raw mode.
 *
 * Callers must invoke {@see restore()} from a `finally` block.
 */
final class SttyTerminalMode implements TerminalMode
{
	private ?string $initialState = null;

	private bool $active = false;

	/**
	 * Whether the current environment looks capable of raw terminal input.
	 */
	public static function isSupported(): bool
	{
		if (! \function_exists('shell_exec')) {
			return false;
		}

		$result = @shell_exec('stty 2>/dev/null');

		return \is_string($result) && trim($result) !== '';
	}

	public function enter(): void
	{
		if ($this->active) {
			return;
		}

		$state = @shell_exec('stty -g 2>/dev/null');

		if (! \is_string($state) || trim($state) === '') {
			throw new RuntimeException(
				'Unable to switch the terminal into interactive mode.',
			);
		}

		$this->initialState = trim($state);

		// Disable canonical mode (deliver bytes immediately), echoing (the
		// selector draws the state itself) and signal generation (Ctrl-C
		// arrives as \x03 so the terminal is always restored via finally).
		@shell_exec('stty -icanon -echo -isig');

		$this->active = true;
	}

	public function restore(): void
	{
		if (! $this->active) {
			return;
		}

		$this->active = false;

		if ($this->initialState === null) {
			return;
		}

		// Fall back to "stty sane" so the terminal stays usable even if the
		// captured state cannot be applied back.
		@shell_exec(sprintf(
			'stty %s 2>/dev/null || stty sane',
			$this->initialState,
		));
	}
}
