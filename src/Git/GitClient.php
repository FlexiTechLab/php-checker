<?php

declare(strict_types=1);

namespace PhpChecker\Git;

use RuntimeException;
use Symfony\Component\Process\Process;

final class GitClient
{
	public function getRepositoryRoot(string $directory): string
	{
		$directory = realpath($directory);

		if ($directory === false || !is_dir($directory)) {
			throw new RuntimeException(
				'Git working directory does not exist.',
			);
		}

		$process = new Process(
			['git', 'rev-parse', '--show-toplevel'],
			$directory,
		);

		$process->run();

		if (!$process->isSuccessful()) {
			throw new RuntimeException('The current directory is not inside a Git repository. ' . trim($process->getErrorOutput()));
		}

		$root = realpath(trim($process->getOutput()));

		if ($root === false) {
			throw new RuntimeException('Unable to resolve the Git repository root.');
		}

		return $root;
	}

	public function getDiff(string $repositoryRoot): Diff
	{
		$repositoryRoot = realpath($repositoryRoot);

		if ($repositoryRoot === false) {
			throw new RuntimeException('Git repository directory does not exist.');
		}

		// A baseline commit is needed to calculate the diff.
		$this->runGit(
			['rev-parse', '--verify', 'HEAD'],
			$repositoryRoot,
		);

		// Includes staged and unstaged changes relative to HEAD.
		$patch = $this->runGit(
			[
				'diff',
				'--no-ext-diff',
				'--no-color',
				'--unified=0',
				'HEAD',
				'--',
			],
			$repositoryRoot,
		);

		$diff = Diff::fromUnifiedDiff($patch);

		// git diff does not include untracked files.
		$untracked = $this->runGit(
			[
				'ls-files',
				'--others',
				'--exclude-standard',
				'-z',
			],
			$repositoryRoot,
		);

		foreach (explode("\0", $untracked) as $relativePath) {
			if ($relativePath === '') {
				continue;
			}

			if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'php') {
				continue;
			}

			$absolutePath = $repositoryRoot . DIRECTORY_SEPARATOR . $relativePath;

			if (!is_file($absolutePath)) {
				continue;
			}

			$lines = file($absolutePath, FILE_IGNORE_NEW_LINES);

			if ($lines === false || $lines === []) {
				continue;
			}

			$diff = $diff->withChangedLines($relativePath, range(1, count($lines)));
		}

		return $diff;
	}

	/**
	 * @param list<string> $arguments
	 */
	private function runGit(array $arguments, string $workingDirectory): string
	{
		$process = new Process(
			['git', ...$arguments],
			$workingDirectory,
		);

		$process->run();

		if (!$process->isSuccessful()) {
			throw new RuntimeException('Git command failed: ' . trim($process->getErrorOutput()));
		}

		return $process->getOutput();
	}
}
