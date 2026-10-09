<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\CheckboxList;
use PhpChecker\CLI\Prompt\Key;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckboxListTest extends TestCase
{
	/**
	 * @return array<string, string>
	 */
	private function items(): array
	{
		return [
			'phpdoc.method' => 'phpdoc.method',
			'typeDeclaration.parameter' => 'typeDeclaration.parameter',
			'typeDeclaration.return' => 'typeDeclaration.return',
		];
	}

	/**
	 * @param list<string> $selected
	 */
	private function list(array $selected = [], ?BufferedOutput $output = null): CheckboxList
	{
		return new CheckboxList(
			$this->items(),
			$selected,
			$output ?? new BufferedOutput(),
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

	public function testRenderShowsIndicatorsAndCursor(): void
	{
		$output = new BufferedOutput();
		$list = $this->list(['phpdoc.method'], $output);

		$list->render(initial: true);

		$display = $output->fetch();

		$this->assertStringContainsString('> [x] phpdoc.method', $display);
		$this->assertStringContainsString(
			'[ ] typeDeclaration.parameter',
			$display,
		);
	}
}
