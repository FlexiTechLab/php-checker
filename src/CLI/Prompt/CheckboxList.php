<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

use PhpChecker\Support\TextWrapper;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * Holds the state of an interactive checkbox list and renders it.
 *
 * The class is deliberately free of any terminal handling so it can be
 * exercised in tests without a TTY. It is also the home of the search and
 * filtering logic: the visible list is derived from the full set of items,
 * while the selection itself is always kept across every registered rule.
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

	private string $query = '';

	private bool $searching = false;

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
	 * Every selected rule, in registration order, regardless of the active
	 * filter.
	 *
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
		$items = $this->visibleItems();

		return isset($items[$this->cursor]) ? $items[$this->cursor]->identifier : null;
	}

	public function isAborted(): bool
	{
		return $this->aborted;
	}

	public function getQuery(): string
	{
		return $this->query;
	}

	public function isSearching(): bool
	{
		return $this->searching;
	}

	public function getMatchCount(): int
	{
		return \count($this->visibleItems());
	}

	/**
	 * Identifiers that match the active filter, in registration order.
	 *
	 * @return list<string>
	 */
	public function getVisibleIdentifiers(): array
	{
		return array_map(
			static fn (CheckboxItem $item): string => $item->identifier,
			$this->visibleItems(),
		);
	}

	public function moveUp(): void
	{
		$count = $this->getMatchCount();

		if ($count === 0) {
			return;
		}

		$this->cursor = ($this->cursor - 1 + $count) % $count;
	}

	public function moveDown(): void
	{
		$count = $this->getMatchCount();

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

	/**
	 * Toggles every rule that is currently visible.
	 *
	 * With no active filter this selects or clears every rule. While a
	 * filter is active only the matching rules change; the selection of the
	 * hidden rules is left untouched.
	 */
	public function toggleAll(): void
	{
		$identifiers = $this->getVisibleIdentifiers();

		if ($identifiers === []) {
			return;
		}

		$selectAll = false;

		foreach ($identifiers as $identifier) {
			if (! $this->selected[$identifier]) {
				$selectAll = true;

				break;
			}
		}

		foreach ($identifiers as $identifier) {
			$this->selected[$identifier] = $selectAll;
		}
	}

	/**
	 * Replaces the active search query and re-applies the filter.
	 */
	public function filter(string $query): void
	{
		$this->query = $query;
		$this->clampCursor();
	}

	public function clearQuery(): void
	{
		$this->query = '';
		$this->clampCursor();
	}

	/**
	 * Applies a key press to the list state.
	 *
	 * @return bool `true` when the prompt should keep running, `false` when
	 *              the user finished (Enter) or aborted (Quit/Escape).
	 */
	public function handle(KeyPress $press): bool
	{
		if ($press->key === Key::Quit) {
			$this->aborted = true;

			return false;
		}

		if ($press->key === Key::Enter) {
			return false;
		}

		if ($this->searching) {
			return $this->handleSearchInput($press);
		}

		return $this->handleBrowseInput($press);
	}

	private function handleSearchInput(KeyPress $press): bool
	{
		switch ($press->key) {
			case Key::Up:
				$this->moveUp();

				return true;

			case Key::Down:
				$this->moveDown();

				return true;

			case Key::Backspace:
				$this->deleteQueryCharacter();

				return true;

			case Key::Tab:
				// Leave the search box but keep the filter active.
				$this->searching = false;

				return true;

			case Key::Escape:
				return $this->clearOrCancel();

			case Key::Character:
				$this->appendToQuery($press->character);

				return true;

			default:
				return true;
		}
	}

	private function handleBrowseInput(KeyPress $press): bool
	{
		switch ($press->key) {
			case Key::Up:
				$this->moveUp();

				return true;

			case Key::Down:
				$this->moveDown();

				return true;

			case Key::Backspace:
				$this->deleteQueryCharacter();

				return true;

			case Key::Tab:
				$this->searching = true;

				return true;

			case Key::Escape:
				return $this->clearOrCancel();

			case Key::Character:
				return $this->handleBrowseCharacter($press->character);

			default:
				return true;
		}
	}

	private function handleBrowseCharacter(string $character): bool
	{
		switch ($character) {
			case ' ':
				$this->toggle();

				return true;

			case 'a':
			case 'A':
				$this->toggleAll();

				return true;

			case 'q':
			case 'Q':
				$this->aborted = true;

				return false;

			case '/':
				$this->searching = true;

				return true;

			case 'j':
				$this->moveDown();

				return true;

			case 'k':
				$this->moveUp();

				return true;

			default:
				// Any other printable character starts a search with that
				// character, so the user can simply begin typing.
				$this->searching = true;
				$this->appendToQuery($character);

				return true;
		}
	}

	/**
	 * Clears the filter when there is one, otherwise cancels the prompt.
	 */
	private function clearOrCancel(): bool
	{
		if ($this->query !== '') {
			$this->clearQuery();

			return true;
		}

		$this->aborted = true;

		return false;
	}

	private function appendToQuery(string $character): void
	{
		if ($character === '') {
			return;
		}

		$this->query .= $character;
		$this->clampCursor();
	}

	private function deleteQueryCharacter(): void
	{
		if ($this->query === '') {
			return;
		}

		$this->query = substr($this->query, 0, -1);
		$this->clampCursor();
	}

	private function clampCursor(): void
	{
		$count = $this->getMatchCount();

		if ($count === 0) {
			$this->cursor = 0;

			return;
		}

		if ($this->cursor >= $count) {
			$this->cursor = $count - 1;
		}

		if ($this->cursor < 0) {
			$this->cursor = 0;
		}
	}

	/**
	 * The items matching the current filter, in registration order.
	 *
	 * @return list<CheckboxItem>
	 */
	private function visibleItems(): array
	{
		return CheckboxItemFilter::filter($this->items, $this->query);
	}

	/**
	 * Draws the list, moving the cursor back up over the previously drawn
	 * lines when it is not the initial render.
	 */
	public function render(bool $initial = false): void
	{
		$lines = $this->lines();
		$count = \count($lines);

		if (! $initial && $this->renderedLines > 0) {
			$this->output->write(sprintf("\x1b[%dA", $this->renderedLines));
		}

		foreach ($lines as $line) {
			$this->output->write("\x1b[2K");
			$this->output->writeln($line);
		}

		// Clear any rows left over from a taller previous frame so a
		// shrinking result set never leaves stale rules on screen.
		if ($count < $this->renderedLines) {
			$extra = $this->renderedLines - $count;

			for ($index = 0; $index < $extra; $index++) {
				$this->output->write("\x1b[2K");
				$this->output->writeln('');
			}

			$this->output->write(sprintf("\x1b[%dA", $extra));
		}

		$this->renderedLines = $count;
	}

	/**
	 * @return list<string>
	 */
	private function lines(): array
	{
		$total = \count($this->items);
		$matching = $this->visibleItems();
		$matchCount = \count($matching);
		$query = OutputFormatter::escape($this->query);

		$lines = [];

		$lines[] = $this->searching
			? sprintf('Search (typing): <info>%s</info>', $query)
			: sprintf('Search: %s', $query);

		if ($this->query === '') {
			$lines[] = sprintf('%d rule(s).', $total);
		} elseif ($matchCount === 0) {
			$lines[] = sprintf('No rules match "%s".', $query);
		} else {
			$lines[] = sprintf('%d of %d rule(s) match.', $matchCount, $total);
		}

		$lines[] = '';

		if ($matchCount === 0) {
			$lines[] = '  (no matching rules)';

			return $lines;
		}

		foreach ($matching as $index => $item) {
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
