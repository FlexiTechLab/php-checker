<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

use PhpChecker\Support\TextWrapper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * Holds the state of an interactive checkbox list and renders it.
 *
 * The class is deliberately free of any terminal handling so it can be
 * exercised in tests without a TTY.
 */
final class CheckboxList
{
	private const DEFAULT_WIDTH = 80;

	/** @var list<CheckboxItem> */
	private array $items = [];

	/** @var list<string> */
	private array $identifiers = [];

	/** @var array<string, bool> */
	private array $selected = [];

	private int $cursor = 0;

	private int $renderedLines = 0;

	private bool $aborted = false;

	private int $width;

	/**
	 * @param list<CheckboxItem> $items
	 * @param list<string>       $selected Identifiers selected by default.
	 */
	public function __construct(
		array $items,
		array $selected,
		private readonly OutputInterface $output,
		?int $width = null,
	) {
		$this->width = $width ?? self::detectWidth();

		foreach ($items as $item) {
			$this->items[] = $item;
			$this->identifiers[] = $item->identifier;
			$this->selected[$item->identifier] = \in_array($item->identifier, $selected, true);
		}
	}

	private static function detectWidth(): int
	{
		$width = (new Terminal())->getWidth();

		return $width > 0 ? $width : self::DEFAULT_WIDTH;
	}

	/**
	 * @return list<string>
	 */
	public function getSelected(): array
	{
		return array_values(array_filter(
			$this->identifiers,
			fn (string $identifier): bool => $this->selected[$identifier],
		));
	}

	public function isSelected(string $identifier): bool
	{
		return $this->selected[$identifier] ?? false;
	}

	public function getCursor(): int
	{
		return $this->cursor;
	}

	public function getCursorIdentifier(): ?string
	{
		return $this->identifiers[$this->cursor] ?? null;
	}

	public function isAborted(): bool
	{
		return $this->aborted;
	}

	public function moveUp(): void
	{
		$count = \count($this->identifiers);

		if ($count === 0) {
			return;
		}

		$this->cursor = ($this->cursor - 1 + $count) % $count;
	}

	public function moveDown(): void
	{
		$count = \count($this->identifiers);

		if ($count === 0) {
			return;
		}

		$this->cursor = ($this->cursor + 1) % $count;
	}

	public function toggle(): void
	{
		$identifier = $this->getCursorIdentifier();

		if ($identifier === null) {
			return;
		}

		$this->selected[$identifier] = ! $this->selected[$identifier];
	}

	public function toggleAll(): void
	{
		$selectAll = false;

		foreach ($this->identifiers as $identifier) {
			if (! $this->selected[$identifier]) {
				$selectAll = true;

				break;
			}
		}

		foreach ($this->identifiers as $identifier) {
			$this->selected[$identifier] = $selectAll;
		}
	}

	/**
	 * Applies a key press to the list state.
	 *
	 * @return bool `true` when the prompt should keep running, `false` when
	 *              the user finished (Enter) or aborted (Quit).
	 */
	public function handle(Key $key): bool
	{
		match ($key) {
			Key::Up => $this->moveUp(),
			Key::Down => $this->moveDown(),
			Key::Space => $this->toggle(),
			Key::ToggleAll => $this->toggleAll(),
			Key::Enter => null,
			Key::Quit => $this->aborted = true,
		};

		return ! \in_array($key, [Key::Enter, Key::Quit], true);
	}

	/**
	 * Draws the list, moving the cursor back up over the previously drawn
	 * lines when it is not the initial render.
	 */
	public function render(bool $initial = false): void
	{
		$lines = $this->lines();

		if (! $initial && $this->renderedLines > 0) {
			$this->output->write(sprintf("\x1b[%dA", $this->renderedLines));
		}

		foreach ($lines as $line) {
			$this->output->write("\x1b[2K");
			$this->output->writeln($line);
		}

		$this->renderedLines = \count($lines);
	}

	/**
	 * @return list<string>
	 */
	private function lines(): array
	{
		$lines = [];

		foreach ($this->items as $index => $item) {
			if ($index > 0) {
				$lines[] = '';
			}

			foreach ($this->formatItem($item, $index === $this->cursor) as $line) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * @return list<string>
	 */
	private function formatItem(CheckboxItem $item, bool $isCursor): array
	{
		$head = ($isCursor ? '> ' : '  ')
			. ($this->selected[$item->identifier] ? '[x] ' : '[ ] ');

		$headWidth = \strlen($head);
		$title = sprintf('%s (%s)', $item->name, $item->identifier);
		$lines = [];

		foreach (TextWrapper::wrap($title, $this->width - $headWidth) as $titleLine) {
			$lines[] = ($lines === [] ? $head : str_repeat(' ', $headWidth)) . $titleLine;
		}

		if ($item->description !== '') {
			$indent = str_repeat(' ', $headWidth);

			foreach (TextWrapper::wrap($item->description, $this->width - $headWidth) as $descriptionLine) {
				$lines[] = $indent . $descriptionLine;
			}
		}

		if ($isCursor) {
			return array_map(
				static fn (string $line): string => sprintf('<info>%s</info>', $line),
				$lines,
			);
		}

		return $lines;
	}
}
