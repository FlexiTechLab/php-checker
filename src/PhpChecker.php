<?php

declare(strict_types=1);

namespace PhpChecker;

use PhpChecker\Config\CheckerConfig;
use PhpChecker\Engine\PhpStan\PhpStanRunner;
use PhpChecker\Rules\RuleRegistry;
use PhpChecker\Reporting\Violation;

final class PhpChecker
{
	private string $path = '.';

	/** @var list<string> */
	private array $useRules = [];

	/** @var list<string> */
	private array $skipRules = [];

	/**
	 * PHPStan built-in rule level. `null` means only the configured custom
	 * rules run; a non-null value opts into PHPStan's built-in analysis too.
	 */
	private ?int $level = null;

	public function __construct(
		private readonly ?PhpStanRunner $runner = null,
		private readonly ?RuleRegistry $ruleRegistry = null,
	) {}

	public function path(string $path): self
	{
		$this->path = $path;

		return $this;
	}

	/**
	 * @param list<string> $rules
	 */
	public function useRules(array $rules): self
	{
		$this->useRules = $rules;

		return $this;
	}

	/**
	 * @param list<string> $rules
	 */
	public function skipRules(array $rules): self
	{
		$this->skipRules = $rules;

		return $this;
	}

	/**
	 * Explicitly opt into PHPStan's built-in analysis at the given level.
	 * Without this call only the configured custom rules are executed.
	 */
	public function level(int $level): self
	{
		$this->level = $level;

		return $this;
	}

	/**
	 * @return list<Violation>
	 */
	public function run(): array
	{
		$config = new CheckerConfig(
			paths: [$this->path],
			useRules: $this->useRules,
			skipRules: $this->skipRules,
			level: $this->level,
		);

		$ruleRegistry = $this->ruleRegistry ?? new RuleRegistry();
		$runner = $this->runner ?? new PhpStanRunner();

		return $runner->run($config, $ruleRegistry);
	}
}
