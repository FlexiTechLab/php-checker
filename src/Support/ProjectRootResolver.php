<?php

declare(strict_types=1);

namespace PhpChecker\Support;

use RuntimeException;

final class ProjectRootResolver
{
	public function resolve(?string $startDirectory = null): string
	{
		$directory = $startDirectory ?? getcwd();

		if ($directory === false) {
			throw new RuntimeException(
				'Unable to determine the current working directory.',
			);
		}

		$directory = realpath($directory);

		if ($directory === false || ! is_dir($directory)) {
			throw new RuntimeException(
				'The project directory does not exist.',
			);
		}

		while (true) {
			if (is_file($directory . '/composer.json')) {
				return $directory;
			}

			$parent = dirname($directory);

			if ($parent === $directory) {
				break;
			}

			$directory = $parent;
		}

		throw new RuntimeException(
			'Could not find composer.json in the current directory or its parent directories.',
		);
	}
}
