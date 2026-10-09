<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\CheckboxItem;
use PhpChecker\CLI\Prompt\CheckboxList;
use PhpChecker\CLI\Prompt\Key;
use PhpChecker\CLI\Prompt\KeyPress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckboxListTest extends TestCase
{
	/**
	 * @return list<CheckboxItem>
	 */
	private function items(): array
	{
		return [
			new CheckboxItem(
				'phpdoc.method',
				'Require Method PHPDoc',
				'Requires PHPDoc documentation for methods.',
			),
			new CheckboxItem(
				'typeDeclaration.parameter',
				'Require Parameter Types',
				'Requires explicit parameter types.',
			),
			new CheckboxItem(
				'typeDeclaration.return',
				'Require Return Types',
				'Requires explicit return types.',
			),
		];
	}

	/**
	 * @param list<string> $selected
	 */
	private function list(
		array $selected = [],
		?BufferedOutput $output = null,
		int $width = 80,
	): CheckboxList {
		return new CheckboxList(
			$this->items(),
			$selected,
			$output ?? new BufferedOutput(),
			$width,
		);
	}

	public function testDefaultSelectionIsPreselected(): void
	{
		$list = $this->list(['typeDeclaration.parameter']);

		$this->assertTrue($list->isSelected('typeDeclaration.parameter'));
		$this->assertFalse($list->isSelected('phpdoc.method'));
		$this->assertSame(['typeDeclaration.parameter'], $list->getSelected());
	}

	public function testEmptySelectionIsPreserved(): void
	{
		$this->assertSame([], $this->list()->getSelected());
	}

	public function testCursorStartsAtFirstItemAndMoves(): void
	{
		$list = $this->list();

		$this->assertSame(0, $list->getCursor());
		$this->assertSame('phpdoc.method', $list->getCursorIdentifier());

		$list->moveDown();
		$this->assertSame(1, $list->getCursor());

		$list->moveUp();
		$this->assertSame(0, $list->getCursor());
	}

	public function testNavigationWrapsAround(): void
	{
		$list = $this->list();

		$list->moveUp();
		$this->assertSame(2, $list->getCursor());

		$list->moveDown();
		$this->assertSame(0, $list->getCursor());
	}

	public function testTogglingUpdatesSelectionState(): void
	{
		$list = $this->list();

		$list->toggle();
		$this->assertTrue($list->isSelected('phpdoc.method'));

		$list->toggle();
		$this->assertFalse($list->isSelected('phpdoc.method'));
	}

	public function testToggleAllSelectsThenClears(): void
	{
		$list = $this->list();

		$list->toggleAll();
		$this->assertSame(
			[
				'phpdoc.method',
				'typeDeclaration.parameter',
				'typeDeclaration.return',
			],
			$list->getSelected(),
		);

		$list->toggleAll();
		$this->assertSame([], $list->getSelected());
	}

	public function testHandleNavigatesAndToggles(): void
	{
		$list = $this->list();

		$this->assertTrue($list->handle(KeyPress::of(Key::Down)));
		$this->assertSame(1, $list->getCursor());

		$this->assertTrue($list->handle(KeyPress::character(' ')));
		$this->assertTrue($list->isSelected('typeDeclaration.parameter'));
	}

	public function testEnterFinishesAndQuitAborts(): void
	{
		$list = $this->list();

		$this->assertFalse($list->handle(KeyPress::of(Key::Enter)));
		$this->assertFalse($list->isAborted());

		$other = $this->list();
		$this->assertFalse($other->handle(KeyPress::of(Key::Quit)));
		$this->assertTrue($other->isAborted());
	}

	public function testQShortcutCancelsInBrowseMode(): void
	{
		$list = $this->list();

		$this->assertFalse($list->handle(KeyPress::character('q')));
		$this->assertTrue($list->isAborted());
	}

	public function testVimKeysNavigateInBrowseMode(): void
	{
		$list = $this->list();

		$list->handle(KeyPress::character('j'));
		$this->assertSame(1, $list->getCursor());

		$list->handle(KeyPress::character('k'));
		$this->assertSame(0, $list->getCursor());
	}

	public function testSearchMatchesNameIdentifierAndDescriptionCaseInsensitively(): void
	{
		$byName = $this->list();
		$byName->filter('METHOD PHPDOC');
		$this->assertSame(['phpdoc.method'], $byName->getVisibleIdentifiers());

		$byIdentifier = $this->list();
		$byIdentifier->filter('TYPEDECLARATION.PARAMETER');
		$this->assertSame(
			['typeDeclaration.parameter'],
			$byIdentifier->getVisibleIdentifiers(),
		);

		$byDescription = $this->list();
		$byDescription->filter('RETURN TYPES');
		$this->assertSame(
			['typeDeclaration.return'],
			$byDescription->getVisibleIdentifiers(),
		);
	}

	public function testSearchFiltersAndNavigatesWithinMatches(): void
	{
		$list = $this->list();
		$list->filter('typeDeclaration');

		$this->assertSame(2, $list->getMatchCount());
		$this->assertSame(
			['typeDeclaration.parameter', 'typeDeclaration.return'],
			$list->getVisibleIdentifiers(),
		);
		$this->assertSame('typeDeclaration.parameter', $list->getCursorIdentifier());

		$list->moveDown();
		$this->assertSame('typeDeclaration.return', $list->getCursorIdentifier());

		$list->moveDown();
		$this->assertSame('typeDeclaration.parameter', $list->getCursorIdentifier());
	}

	public function testSearchWithNoMatchesYieldsEmptyState(): void
	{
		$list = $this->list();
		$list->filter('zzzz');

		$this->assertSame(0, $list->getMatchCount());
		$this->assertSame([], $list->getVisibleIdentifiers());
		$this->assertNull($list->getCursorIdentifier());

		// Toggling and toggling all are no-ops while nothing matches.
		$list->toggle();
		$list->toggleAll();
		$this->assertSame([], $list->getSelected());
	}

	public function testClearingSearchRestoresTheFullList(): void
	{
		$list = $this->list();
		$list->filter('phpdoc');
		$this->assertSame(['phpdoc.method'], $list->getVisibleIdentifiers());

		$list->clearQuery();

		$this->assertSame('', $list->getQuery());
		$this->assertSame(3, $list->getMatchCount());
	}

	public function testFilteringPreservesHiddenSelection(): void
	{
		$list = $this->list(['phpdoc.method']);
		$list->filter('parameter');

		$this->assertSame(['typeDeclaration.parameter'], $list->getVisibleIdentifiers());
		$this->assertTrue($list->isSelected('phpdoc.method'));
		$this->assertSame(['phpdoc.method'], $list->getSelected());
	}

	public function testToggleAllWithFilterOnlyAffectsVisibleRules(): void
	{
		$list = $this->list(['phpdoc.method']);
		$list->filter('parameter');

		$list->toggleAll();
		$this->assertTrue($list->isSelected('typeDeclaration.parameter'));
		$this->assertTrue($list->isSelected('phpdoc.method'));

		$list->toggleAll();
		$this->assertFalse($list->isSelected('typeDeclaration.parameter'));
		// The hidden selection survives deselecting every visible rule.
		$this->assertTrue($list->isSelected('phpdoc.method'));
	}

	public function testTypingStartsASearchWithoutTogglingRules(): void
	{
		$list = $this->list();

		$this->assertTrue($list->handle(KeyPress::character('p')));
		$this->assertTrue($list->isSearching());
		$this->assertSame('p', $list->getQuery());
		$this->assertSame([], $list->getSelected());

		// Typing more characters extends the query without toggling.
		$list->handle(KeyPress::character('a'));
		$this->assertSame('pa', $list->getQuery());
		$this->assertSame(
			['typeDeclaration.parameter'],
			$list->getVisibleIdentifiers(),
		);
		$this->assertSame([], $list->getSelected());
	}

	public function testTabLeavesTheSearchBoxAndKeepsTheFilter(): void
	{
		$list = $this->list();
		$list->handle(KeyPress::character('p'));
		$list->handle(KeyPress::character('a'));

		$list->handle(KeyPress::of(Key::Tab));

		$this->assertFalse($list->isSearching());
		$this->assertSame('pa', $list->getQuery());

		// Now that the list has focus, Space toggles the only match.
		$list->handle(KeyPress::character(' '));
		$this->assertSame(['typeDeclaration.parameter'], $list->getSelected());
	}

	public function testSearchModeTreatsShortcutsAsText(): void
	{
		$list = $this->list();

		// '/' focuses the search box without becoming part of the query.
		$list->handle(KeyPress::character('/'));
		$this->assertTrue($list->isSearching());
		$this->assertSame('', $list->getQuery());

		// Space, 'a' and 'q' are plain text while the search box is focused.
		$list->handle(KeyPress::character('a'));
		$list->handle(KeyPress::character('q'));
		$list->handle(KeyPress::character(' '));

		$this->assertSame('aq ', $list->getQuery());
		$this->assertFalse($list->isAborted());
	}

	public function testEscapeClearsTheSearchThenCancels(): void
	{
		$list = $this->list();
		$list->filter('phpdoc');

		// The first Escape clears the filter and keeps the prompt open.
		$this->assertTrue($list->handle(KeyPress::of(Key::Escape)));
		$this->assertSame('', $list->getQuery());
		$this->assertFalse($list->isAborted());

		// With nothing left to clear, Escape cancels the prompt.
		$this->assertFalse($list->handle(KeyPress::of(Key::Escape)));
		$this->assertTrue($list->isAborted());
	}

	public function testBackspaceDeletesQueryCharacters(): void
	{
		$list = $this->list();
		$list->filter('phpdoc');

		$list->handle(KeyPress::of(Key::Backspace));

		$this->assertSame('phpdo', $list->getQuery());
	}

	public function testRenderShowsNameIdentifierAndDescription(): void
	{
		$output = new BufferedOutput();
		$list = $this->list(['phpdoc.method'], $output);

		$list->render(initial: true);

		$display = $output->fetch();

		$this->assertStringContainsString(
			'> [x] Require Method PHPDoc (phpdoc.method)',
			$display,
		);
		$this->assertStringContainsString(
			'Requires PHPDoc documentation for methods.',
			$display,
		);
		$this->assertStringContainsString(
			'[ ] Require Parameter Types (typeDeclaration.parameter)',
			$display,
		);
	}

	public function testRenderShowsSearchStateAndMatchCount(): void
	{
		$output = new BufferedOutput();
		$list = new CheckboxList($this->items(), [], $output, 80);

		$list->filter('typeDeclaration');
		$list->render(initial: true);

		$display = $output->fetch();

		$this->assertStringContainsString('Search: typeDeclaration', $display);
		$this->assertStringContainsString('2 of 3 rule(s) match.', $display);
	}

	public function testRenderIndicatesWhenTheSearchBoxHasFocus(): void
	{
		$output = new BufferedOutput();
		$list = new CheckboxList($this->items(), [], $output, 80);

		$list->handle(KeyPress::of(Key::Tab));
		$list->render(initial: true);

		$this->assertStringContainsString('Search (typing): ', $output->fetch());
	}

	public function testRenderShowsNoMatchMessage(): void
	{
		$output = new BufferedOutput();
		$list = new CheckboxList($this->items(), [], $output, 80);

		$list->filter('zzz');
		$list->render(initial: true);

		$display = $output->fetch();

		$this->assertStringContainsString('No rules match "zzz".', $display);
		$this->assertStringContainsString('(no matching rules)', $display);
	}

	public function testRenderWrapsContentToTheAvailableWidth(): void
	{
		$output = new BufferedOutput();
		$list = new CheckboxList(
			[
				new CheckboxItem(
					'typeSafety.disallowMixed',
					'Disallow Mixed Types',
					'Flags the use of mixed types to encourage more specific types and stronger static analysis.',
				),
			],
			[],
			$output,
			40,
		);

		$list->render(initial: true);

		$plain = preg_replace(
			'/\x1b\[[0-9;?]*[A-Za-z]/',
			'',
			$output->fetch(),
		) ?? '';

		foreach (explode("\n", $plain) as $line) {
			$this->assertLessThanOrEqual(
				40,
				\strlen($line),
				sprintf('Line exceeds the terminal width: "%s"', $line),
			);
		}

		$this->assertStringContainsString('Disallow Mixed Types', $plain);
		$this->assertStringContainsString('(typeSafety.disallowMixed)', $plain);
	}

	public function testRedrawClearsRowsFromATallerPreviousFrame(): void
	{
		$output = new BufferedOutput();
		$list = new CheckboxList($this->items(), [], $output, 80);

		$list->render(initial: true);
		$output->fetch();

		$list->filter('phpdoc.method');
		$list->render();
		$redraw = $output->fetch();

		// The previous frame had 11 rows and the filtered frame has 5: the
		// redraw moves back over the old frame and clears the 6 extra rows.
		$this->assertStringContainsString("\x1b[11A", $redraw);
		$this->assertStringContainsString("\x1b[6A", $redraw);
		$this->assertSame(11, substr_count($redraw, "\x1b[2K"));
		$this->assertStringContainsString('Search: phpdoc.method', $redraw);
	}
}
