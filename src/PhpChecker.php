<?php

declare(strict_types=1);

namespace PhpChecker;

use PhpChecker\Config\CheckerConfig;
use PhpChecker\Engine\PhpStan\PhpStanRunner;
use PhpChecker\Reporting\ConsoleReporter;
use PhpChecker\Rules\RuleRegistry;

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
		private readonly ?ConsoleReporter $reporter = null,
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

	public function run(): int
	{
		$config = new CheckerConfig(
			paths: [$this->path],
			useRules: $this->useRules,
			skipRules: $this->skipRules,
		);

		$ruleRegistry = $this->ruleRegistry ?? new RuleRegistry();

		$runner = $this->runner ?? new PhpStanRunner();

		$violations = $runner->run(
			$config,
			$ruleRegistry,
		);

		$reporter = $this->reporter ?? new ConsoleReporter();

		return $reporter->report($violations);
	}
}
