<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * Decodes raw terminal bytes into {@see Key} values.
 *
 * The reader understands the ANSI escape sequences emitted by the arrow
 * keys, the vim-style movement keys and the single-byte keys (space,
 * enter, toggle-all and quit). Bytes it does not recognise are skipped.
 */
final class StreamKeyReader implements KeyReader
{
	/** @var resource */
	private $stream;

	/**
	 * @param resource $stream
	 */
	public function __construct($stream)
	{
		$this->stream = $stream;
	}

	public function read(): ?Key
	{
		while (($byte = $this->readByte()) !== null) {
			$key = $this->decode($byte);

			if ($key !== null) {
				return $key;
			}
		}

		return null;
	}

	private function decode(string $byte): ?Key
	{
		return match ($byte) {
			' ' => Key::Space,
			"\n", "\r" => Key::Enter,
			'a', 'A' => Key::ToggleAll,
			'q', 'Q', "\x03", "\x04" => Key::Quit,
			'j' => Key::Down,
			'k' => Key::Up,
			"\x1b" => $this->decodeEscapeSequence(),
			default => null,
		};
	}

	private function decodeEscapeSequence(): ?Key
	{
		$sequence = $this->readContinuation(2);

		if ($sequence === '') {
			// A lone Escape key press.
			return Key::Quit;
		}

		return match ($sequence[\strlen($sequence) - 1]) {
			'A' => Key::Up,
			'B' => Key::Down,
			default => null,
		};
	}

	/**
	 * Reads up to $length bytes, waiting only briefly so that a lone Escape
	 * key press does not stall on an escape sequence that never arrives.
	 */
	private function readContinuation(int $length): string
	{
		$data = '';

		while (\strlen($data) < $length && $this->waitForByte()) {
			$chunk = @fread($this->stream, 1);

			if ($chunk === false || $chunk === '') {
				break;
			}

			$data .= $chunk;
		}

		return $data;
	}

	/**
	 * Waits briefly for the next byte of an escape sequence.
	 */
	private function waitForByte(): bool
	{
		try {
			$read = [$this->stream];
			$write = [];
			$except = [];

			return @stream_select($read, $write, $except, 0, 50000) > 0;
		} catch (\Throwable) {
			// Memory and other non-selectable streams always have their
			// buffered bytes available immediately.
			return true;
		}
	}

	private function readByte(): ?string
	{
		$byte = @fread($this->stream, 1);

		if ($byte === false || $byte === '') {
			return null;
		}

		return $byte;
	}
}
