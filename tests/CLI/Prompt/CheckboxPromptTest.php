<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\CheckboxPrompt;
use PhpChecker\CLI\Prompt\Key;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckboxPromptTest extends TestCase
{
	/**
	 * @return array<string, string>
	 */
	private function items(): array
	{
		return [
			'phpdoc.method' => 'phpdoc.method',
			'typeDeclaration.parameter' => 'typeDeclaration.parameter',
		];
	}

	public function testReturnsSelectionFromScriptedKeys(): void
	{
		$mode = new RecordingTerminalMode();

		$selected = (new CheckboxPrompt($mode))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader([Key::Down, Key::Space, Key::Enter]),
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
			new ScriptedKeyReader([Key::Space, Key::Enter]),
			$this->items(),
			['phpdoc.method'],
		);

		$this->assertSame([], $selected);
	}

	public function testToggleAllFromScriptedKeys(): void
	{
		$selected = (new CheckboxPrompt(new RecordingTerminalMode()))->ask(
			new BufferedOutput(),
			new ScriptedKeyReader([Key::ToggleAll, Key::Enter]),
			$this->items(),
			[],
		);

		$this->assertSame(
			['phpdoc.method', 'typeDeclaration.parameter'],
			$selected,
		);
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
