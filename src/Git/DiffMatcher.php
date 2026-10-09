<?php

declare(strict_types=1);

namespace PhpChecker\Git;

use PhpChecker\Reporting\Violation;

final class DiffMatcher
{
	/**
	 * @param list<Violation> $violations
	 *
	 * @return list<Violation>
	 */
	public function filter(array $violations, Diff $diff, string $repositoryRoot,): array
	{
		$repositoryRoot = str_replace(
			'\\',
			'/',
			rtrim($repositoryRoot, '/\\'),
		);

		$filtered = [];

		foreach ($violations as $violation) {
			$file = $this->relativePath(
				$violation->file,
				$repositoryRoot,
			);

			if ($file === null) {
				continue;
			}

			if (in_array($violation->line, $diff->changedLinesFor($file), true)) {
				$filtered[] = $violation;
			}
		}

		return $filtered;
	}

	private function relativePath(string $file, string $repositoryRoot): ?string
	{
		$file = str_replace('\\', '/', $file);

		if (
			!str_starts_with($file, '/')
			&& preg_match('/^[A-Za-z]:\//', $file) !== 1
		) {
			$file = $repositoryRoot . '/' . ltrim($file, '/');
		}

		if ($file === $repositoryRoot) {
			return null;
		}

		$prefix = $repositoryRoot . '/';

		if (!str_starts_with($file, $prefix)) {
			return null;
		}

		return substr($file, strlen($prefix));
	}
}
