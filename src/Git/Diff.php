<?php

declare(strict_types=1);

namespace PhpChecker\Git;

final readonly class Diff
{
	/**
	 * @param array<string, list<int>> $changedLinesByFile
	 */
	public function __construct(
		private array $changedLinesByFile = [],
	) {}

	public static function fromUnifiedDiff(string $patch): self
	{
		$changes = [];
		$currentFile = null;
		$newLine = null;

		foreach (preg_split('/\r\n|\r|\n/', $patch) ?: [] as $line) {
			if (str_starts_with($line, '+++ ')) {
				$path = substr($line, 4);

				if ($path === '/dev/null') {
					$currentFile = null;
					$newLine = null;
					continue;
				}

				$currentFile = str_starts_with($path, 'b/')
					? substr($path, 2)
					: $path;

				$newLine = null;
				continue;
			}

			if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches) === 1) {
				$newLine = (int) $matches[1];
				continue;
			}

			if ($currentFile === null || $newLine === null) {
				continue;
			}

			if (str_starts_with($line, '+')) {
				if (!str_starts_with($line, '+++')) {
					$changes[$currentFile][] = $newLine;
					++$newLine;
				}

				continue;
			}

			if (str_starts_with($line, '-')) {
				continue;
			}

			if (str_starts_with($line, '\\')) {
				continue;
			}

			if (str_starts_with($line, ' ')) {
				++$newLine;
			}
		}

		foreach ($changes as &$lines) {
			$lines = array_values(array_unique($lines));
			sort($lines);
		}
		unset($lines);

		return new self($changes);
	}

	/**
	 * Add every line of a new, untracked file.
	 *
	 * @param list<int> $lines
	 */
	public function withChangedLines(string $file, array $lines): self
	{
		$changes = $this->changedLinesByFile;
		$file = str_replace('\\', '/', $file);

		$changes[$file] = array_values(array_unique([
			...($changes[$file] ?? []),
			...$lines,
		]));

		sort($changes[$file]);

		return new self($changes);
	}

	/**
	 * @return list<int>
	 */
	public function changedLinesFor(string $file): array
	{
		$file = str_replace('\\', '/', $file);

		return $this->changedLinesByFile[$file] ?? [];
	}

	/**
	 * @return list<string>
	 */
	public function files(): array
	{
		return array_keys($this->changedLinesByFile);
	}
}
