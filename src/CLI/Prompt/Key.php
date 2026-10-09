<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * A decoded key press relevant to an interactive checkbox list.
 */
enum Key
{
	case Up;

	case Down;

	case Space;

	case Enter;

	case ToggleAll;

	case Quit;
}
