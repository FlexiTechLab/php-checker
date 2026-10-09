<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * Decodes raw terminal bytes into {@see KeyPress} values.
 *
 * Printable bytes (including the space bar) are reported as
 * {@see Key::Character} so the caller can decide whether they should toggle
 * a rule or extend a search query. The reader also understands the ANSI
 * escape sequences emitted by the arrow keys, backspace/delete, tab and
 * Escape. Bytes it does not recognise are skipped.
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

	public function read(): ?KeyPress
	{
		while (($byte = $this->readByte()) !== null) {
			$press = $this->decode($byte);

			if ($press !== null) {
				return $press;
			}
		}

		return null;
	}

	private function decode(string $byte): ?KeyPress
	{
		$control = match ($byte) {
			"\n", "\r" => KeyPress::of(Key::Enter),
			"\x7f", "\x08" => KeyPress::of(Key::Backspace),
			"\t" => KeyPress::of(Key::Tab),
			"\x03", "\x04" => KeyPress::of(Key::Quit),
			"\x1b" => $this->decodeEscapeSequence(),
			default => null,
		};

		if ($control !== null) {
			return $control;
		}

		if ($byte >= ' ' && $byte <= '~') {
			return KeyPress::character($byte);
		}

		return null;
	}

	private function decodeEscapeSequence(): ?KeyPress
	{
		$introducer = $this->readContinuation(1);

		if ($introducer === '') {
			// A lone Escape key press.
			return KeyPress::of(Key::Escape);
		}

		if ($introducer !== '[' && $introducer !== 'O') {
			return null;
		}

		$sequence = $this->readUntilFinalByte();

		return match ($sequence) {
			'A' => KeyPress::of(Key::Up),
			'B' => KeyPress::of(Key::Down),
			'3~' => KeyPress::of(Key::Backspace),
			default => null,
		};
	}

	/**
	 * Reads a CSI/SS3 sequence up to and including its final byte.
	 */
	private function readUntilFinalByte(): string
	{
		$data = '';

		while (($byte = $this->readContinuation(1)) !== '') {
			$data .= $byte;

			$code = \ord($byte);

			if ($code >= 0x40 && $code <= 0x7e) {
				break;
			}
		}

		return $data;
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
