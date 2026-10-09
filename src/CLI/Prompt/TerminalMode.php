<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * Switches the terminal into a mode suitable for reading individual key
 * presses and restores it afterwards.
 */
interface TerminalMode
{
	/**
	 * Enables the interactive mode.
	 */
	public function enter(): void;

	/**
	 * Restores the settings captured by {@see enter()}. Safe to call more
	 * than once and when {@see enter()} was never called.
	 */
	public function restore(): void;
}
