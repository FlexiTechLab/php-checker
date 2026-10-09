<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives an interactive {@see CheckboxList}, reading key presses from a
 * {@see KeyReader} and restoring the terminal settings afterwards.
 */
final class CheckboxPrompt
{
	public function __construct(
		private readonly TerminalMode $terminalMode,
	) {}

	/**
	 * @param list<CheckboxItem> $items
	 * @param list<string>       $selected Identifiers selected by default.
	 *
	 * @return list<string>|null The selected identifiers, or `null` when the
	 *                           user aborted.
	 */
	public function ask(
		OutputInterface $output,
		KeyReader $reader,
		array $items,
		array $selected,
		?int $width = null,
	): ?array {
		$list = new CheckboxList($items, $selected, $output, $width);

		$this->terminalMode->enter();

		try {
			$list->render(initial: true);

			while (true) {
				$press = $reader->read();

				if ($press === null) {
					return null;
				}

				if (! $list->handle($press)) {
					break;
				}

				$list->render();
			}
		} finally {
			$this->terminalMode->restore();
			$output->writeln('');
		}

		if ($list->isAborted()) {
			return null;
		}

		return $list->getSelected();
	}
}
