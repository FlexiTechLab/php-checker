<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\CheckboxItem;
use PhpChecker\CLI\Prompt\CheckboxList;
use PhpChecker\CLI\Prompt\Key;
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

		$this->assertTrue($list->handle(Key::Down));
		$this->assertSame(1, $list->getCursor());

		$this->assertTrue($list->handle(Key::Space));
		$this->assertTrue($list->isSelected('typeDeclaration.parameter'));
	}

	public function testEnterFinishesAndQuitAborts(): void
	{
		$list = $this->list();

		$this->assertFalse($list->handle(Key::Enter));
		$this->assertFalse($list->isAborted());

		$other = $this->list();
		$this->assertFalse($other->handle(Key::Quit));
		$this->assertTrue($other->isAborted());
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
}
