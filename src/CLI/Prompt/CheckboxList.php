<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Holds the state of an interactive checkbox list and renders it.
 *
 * The class is deliberately free of any terminal handling so it can be
 * exercised in tests without a TTY.
 */
final class CheckboxList
{
	/** @var list<string> */
	private array $identifiers = [];

	/** @var array<string, string> */
	private array $labels = [];

	/** @var array<string, bool> */
	private array $selected = [];

	private int $cursor = 0;

	private int $renderedLines = 0;

	private bool $aborted = false;

	/**
	 * @param array<string, string> $items    Identifier => label.
	 * @param list<string>          $selected Identifiers selected by default.
	 */
	public function __construct(
		array $items,
		array $selected,
		private readonly OutputInterface $output,
	) {
		foreach ($items as $identifier => $label) {
			$this->identifiers[] = $identifier;
			$this->labels[$identifier] = $label;
			$this->selected[$identifier] = \in_array($identifier, $selected, true);
		}
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
		if (! $initial && $this->renderedLines > 0) {
			$this->output->write(sprintf("\x1b[%dA", $this->renderedLines));
		}

		$lines = 0;

		foreach ($this->identifiers as $index => $identifier) {
			$this->output->write("\x1b[2K");
			$this->output->writeln($this->formatLine($index, $identifier));
			++$lines;
		}

		$this->renderedLines = $lines;
	}

	private function formatLine(int $index, string $identifier): string
	{
		$pointer = $index === $this->cursor ? '>' : ' ';
		$mark = $this->selected[$identifier] ? '[x]' : '[ ]';
		$label = $this->labels[$identifier];

		if ($index === $this->cursor) {
			return sprintf('<info>%s %s %s</info>', $pointer, $mark, $label);
		}

		return sprintf('%s %s %s', $pointer, $mark, $label);
	}
}
