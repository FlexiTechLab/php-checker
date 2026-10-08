<?php

declare(strict_types=1);

namespace PhpChecker\Config;

final class CheckerConfig
{
	/**
	 * @param list<string> $paths
	 * @param list<string> $useRules
	 * @param list<string> $skipRules
	 * @param list<string> $excludePaths
	 */
	public function __construct(
		private readonly array $paths = ['.'],
		private readonly array $useRules = [],
		private readonly array $skipRules = [],
		private readonly array $excludePaths = [
			'vendor',
			'node_modules',
			'tests',
		],
		private readonly int $level = 8,
	) {}

	/**
	 * @return list<string>
	 */
	public function getPaths(): array
	{
		return $this->paths;
	}

	/**
	 * @return list<string>
	 */
	public function getUseRules(): array
	{
		return $this->useRules;
	}

	/**
	 * @return list<string>
	 */
	public function getSkipRules(): array
	{
		return $this->skipRules;
	}

	/**
	 * @return list<string>
	 */
	public function getExcludePaths(): array
	{
		return $this->excludePaths;
	}

	public function getLevel(): int
	{
		return $this->level;
	}

	public function shouldSkipRule(string $ruleId): bool
	{
		return in_array($ruleId, $this->skipRules, true);
	}

	public function shouldUseRule(string $ruleId): bool
	{
		if ($this->shouldSkipRule($ruleId)) {
			return false;
		}

		// Empty = use all registered rules.
		if ($this->useRules === []) {
			return true;
		}

		return in_array($ruleId, $this->useRules, true);
	}
}
