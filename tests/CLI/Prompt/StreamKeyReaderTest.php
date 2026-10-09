<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\Key;
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

	public function testDecodesSpaceAndEnter(): void
	{
		$reader = new StreamKeyReader($this->streamFor(" \n"));

		$this->assertSame(Key::Space, $reader->read());
		$this->assertSame(Key::Enter, $reader->read());
		$this->assertNull($reader->read());
	}

	public function testDecodesArrowKeys(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\x1b[A\x1b[B"));

		$this->assertSame(Key::Up, $reader->read());
		$this->assertSame(Key::Down, $reader->read());
		$this->assertNull($reader->read());
	}

	public function testDecodesToggleAllQuitAndCtrlC(): void
	{
		$reader = new StreamKeyReader($this->streamFor("aq\x03"));

		$toggleAll = $reader->read();
		$q = $reader->read();
		$ctrlC = $reader->read();

		$this->assertSame(Key::ToggleAll, $toggleAll);
		$this->assertSame(Key::Quit, $q);
		$this->assertSame(Key::Quit, $ctrlC);
		$this->assertNull($reader->read());
	}

	public function testIgnoresUnknownBytesAndSequences(): void
	{
		$reader = new StreamKeyReader($this->streamFor("xyz\x1b[C "));

		// 'x', 'y', 'z' and the right arrow are ignored; space toggles.
		$this->assertSame(Key::Space, $reader->read());
		$this->assertNull($reader->read());
	}

	public function testLoneEscapeQuits(): void
	{
		$reader = new StreamKeyReader($this->streamFor("\x1b"));

		$this->assertSame(Key::Quit, $reader->read());
		$this->assertNull($reader->read());
	}

	public function testVimKeysNavigate(): void
	{
		$reader = new StreamKeyReader($this->streamFor('kj'));

		$this->assertSame(Key::Up, $reader->read());
		$this->assertSame(Key::Down, $reader->read());
	}
}
