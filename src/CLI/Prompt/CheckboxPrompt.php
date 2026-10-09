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
	 * @param array<string, string> $items    Identifier => label.
	 * @param list<string>          $selected Identifiers selected by default.
	 *
	 * @return list<string>|null The selected identifiers, or `null` when the
	 *                           user aborted.
	 */
	public function ask(
		OutputInterface $output,
		KeyReader $reader,
		array $items,
		array $selected,
	): ?array {
		$list = new CheckboxList($items, $selected, $output);

		$this->terminalMode->enter();

		try {
			$list->render(initial: true);

			while (true) {
				$key = $reader->read();

				if ($key === null) {
					return null;
				}

				if (! $list->handle($key)) {
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
