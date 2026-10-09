<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\CheckboxItem;
use PhpChecker\CLI\Prompt\CheckboxPrompt;
use PhpChecker\CLI\Prompt\Key;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckboxPromptTest extends TestCase
{
	/**
	 * @return list<CheckboxItem>
	 */
	private function items(): array
	{
		return [
			new CheckboxItem('phpdoc.method', 'Require Method PHPDoc', 'Docs.'),
			new CheckboxItem('typeDeclaration.parameter', 'Require Parameter Types', 'Types.'),
			new CheckboxItem('typeDeclaration.return', 'Require Return Types', 'Returns.'),
		];
	}

	public function testReturnsSelectionFromScriptedKeys(): void
	{
		$mode = new RecordingTerminalMode();

		$selected = (new CheckboxPrompt($mode))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader([Key::Down, ' ', Key::Enter]),
			$this->items(),
			[],
		);

		$this->assertSame(['typeDeclaration.parameter'], $selected);
		$this->assertSame(['entered', 'restored'], $mode->events);
	}

	public function testPreselectedRuleCanBeDeselectedToSaveEmptySelection(): void
	{
		$selected = (new CheckboxPrompt(new RecordingTerminalMode()))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader([' ', Key::Enter]),
			$this->items(),
			['phpdoc.method'],
		);

		$this->assertSame([], $selected);
	}

	public function testToggleAllFromScriptedKeys(): void
	{
		$selected = (new CheckboxPrompt(new RecordingTerminalMode()))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader(['a', Key::Enter]),
			$this->items(),
			[],
		);

		$this->assertSame(
			[
				'phpdoc.method',
				'typeDeclaration.parameter',
				'typeDeclaration.return',
			],
			$selected,
		);
	}

	public function testSavingIncludesHiddenRulesWhileAFilterIsActive(): void
	{
		// Pre-select the first rule, then filter down to the last one and
		// toggle it. The final selection must include both even though the
		// first rule was hidden by the filter.
		$selected = (new CheckboxPrompt(new RecordingTerminalMode()))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader([
				'/', // focus the search box
				'r', 'e', 't', 'u', 'r', 'n', // type "return"
				Key::Tab, // back to the list, filter kept
				' ', // toggle the only match
				Key::Enter,
			]),
			$this->items(),
			['phpdoc.method'],
		);

		$this->assertSame(
			['phpdoc.method', 'typeDeclaration.return'],
			$selected,
		);
	}

	public function testSearchQueryPersistsUntilEscapeClearsIt(): void
	{
		$output = new BufferedOutput();

		(new CheckboxPrompt(new RecordingTerminalMode()))->ask(
			$output,
			new ScriptedKeyReader([
				'/',
				'p', 'a', 'r',
				Key::Tab,
				Key::Escape, // clears the query
				Key::Enter,
			]),
			$this->items(),
			[],
		);

		$this->assertStringContainsString('Search: par', $output->fetch());
	}

	public function testQuitReturnsNullAndRestoresTerminal(): void
	{
		$mode = new RecordingTerminalMode();

		$selected = (new CheckboxPrompt($mode))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader([Key::Quit]),
			$this->items(),
			[],
		);

		$this->assertNull($selected);
		$this->assertSame(['entered', 'restored'], $mode->events);
	}

	public function testEndOfInputAbortsAndRestoresTerminal(): void
	{
		$mode = new RecordingTerminalMode();

		$selected = (new CheckboxPrompt($mode))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader([]),
			$this->items(),
			[],
		);

		$this->assertNull($selected);
		$this->assertSame(['entered', 'restored'], $mode->events);
	}

	public function testRendersNamesAndIdentifiers(): void
	{
		$output = new BufferedOutput();

		(new CheckboxPrompt(new RecordingTerminalMode()))->ask(
			$output,
			new ScriptedKeyReader([Key::Enter]),
			$this->items(),
			[],
			80,
		);

		$display = $output->fetch();

		$this->assertStringContainsString(
			'Require Method PHPDoc (phpdoc.method)',
			$display,
		);
		$this->assertStringContainsString(
			'Require Parameter Types (typeDeclaration.parameter)',
			$display,
		);
	}

	public function testTerminalIsRestoredWhenReaderThrows(): void
	{
		$mode = new RecordingTerminalMode();

		try {
			(new CheckboxPrompt($mode))->ask(
				new BufferedOutput(),
				new ThrowingKeyReader(),
				$this->items(),
				[],
			);

			$this->fail('Expected the reader to throw.');
		} catch (RuntimeException $exception) {
			$this->assertSame('read failed', $exception->getMessage());
		}

		$this->assertSame(['entered', 'restored'], $mode->events);
	}
}
