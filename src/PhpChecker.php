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
	 * @return list<Violation>
	 */
	public function run(): array
	{
		$config = new CheckerConfig(
			paths: [$this->path],
			useRules: $this->useRules,
			skipRules: $this->skipRules,
		);

		$ruleRegistry = $this->ruleRegistry ?? new RuleRegistry();
		$runner = $this->runner ?? new PhpStanRunner();

		return $runner->run($config, $ruleRegistry);
	}
}
