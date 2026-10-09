<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\Key;
use PhpChecker\CLI\Prompt\KeyPress;
use PhpChecker\CLI\Prompt\StreamKeyReader;
use PHPUnit\Framework\TestCase;

final class StreamKeyReaderTest extends TestCase
{
	/**
	 * @return resource
	 */
	private function streamFor(string $bytes)
	{
		$stream = fopen('php://memory', 'r+');

		$this->assertIsResource($stream);

		fwrite($stream, $bytes);
		rewind($stream);

		return $stream;
	}

	private function readKey(StreamKeyReader $reader): ?Key
	{
		$press = $reader->read();

		return $press?->key;
	}

	public function testDecodesSpaceAsACharacterAndEnter(): void
	{
		$reader = new StreamKeyReader($this->streamFor(" \n"));

		$space = $reader->read();

		$this->assertNotNull($space);
		$this->assertSame(Key::Character, $space->key);
		$this->assertSame(' ', $space->character);

		$this->assertSame(Key::Enter, $this->readKey($reader));
		$this->assertNull($reader->read());
	}

	public function testDecodesArrowKeys(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\x1b[A\x1b[B"));

		$this->assertSame(Key::Up, $this->readKey($reader));
		$this->assertSame(Key::Down, $this->readKey($reader));
		$this->assertNull($reader->read());
	}

	public function testDecodesSs3ArrowKeys(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\x1bOA\x1bOB"));

		$this->assertSame(Key::Up, $this->readKey($reader));
		$this->assertSame(Key::Down, $this->readKey($reader));
	}

	public function testDecodesLettersAsCharactersAndCtrlCAsQuit(): void
	{
		$reader = new StreamKeyReader($this->streamFor("aq\x03"));

		$a = $reader->read();
		$q = $reader->read();

		$this->assertNotNull($a);
		$this->assertNotNull($q);
		$this->assertSame(Key::Character, $a->key);
		$this->assertSame('a', $a->character);
		$this->assertSame(Key::Character, $q->key);
		$this->assertSame('q', $q->character);
		$this->assertSame(Key::Quit, $this->readKey($reader));
		$this->assertNull($reader->read());
	}

	public function testDecodesTabAndBackspace(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\t\x7f\x08"));

		$tab = $this->readKey($reader);
		$delete = $this->readKey($reader);
		$backspace = $this->readKey($reader);

		$this->assertSame(Key::Tab, $tab);
		$this->assertSame(Key::Backspace, $delete);
		$this->assertSame(Key::Backspace, $backspace);
	}

	public function testDecodesTheDeleteSequenceAsBackspace(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\x1b[3~"));

		$this->assertSame(Key::Backspace, $this->readKey($reader));
		$this->assertNull($reader->read());
	}

	public function testPrintableBytesBecomeCharacters(): void
	{
		$reader = new StreamKeyReader($this->streamFor('xyz'));

		foreach (['x', 'y', 'z'] as $expected) {
			$press = $reader->read();

			$this->assertNotNull($press);
			$this->assertSame(Key::Character, $press->key);
			$this->assertSame($expected, $press->character);
		}

		$this->assertNull($reader->read());
	}

	public function testIgnoresUnrecognisedEscapeSequences(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\x1b[C "));

		// The right arrow is skipped, then the space becomes a character.
		$press = $reader->read();

		$this->assertNotNull($press);
		$this->assertSame(Key::Character, $press->key);
		$this->assertSame(' ', $press->character);
		$this->assertNull($reader->read());
	}

	public function testLoneEscapeIsReportedAsEscape(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\x1b"));

		$this->assertSame(Key::Escape, $this->readKey($reader));
		$this->assertNull($reader->read());
	}
}
