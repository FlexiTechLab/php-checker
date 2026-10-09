<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * The kind of key press relevant to an interactive checkbox list.
 *
 * Printable text (letters, digits, punctuation and the space bar) is
 * reported as {@see Key::Character}; the actual text is carried by
 * {@see KeyPress}. Keeping the reader free of any notion of "modes" means
 * the same character can toggle a rule or extend the search query depending
 * on whether the search box currently has focus.
 */
enum Key
{
	case Up;

	case Down;

	case Enter;

	case Backspace;

	case Escape;

	case Tab;

	case Quit;

	case Character;
}
