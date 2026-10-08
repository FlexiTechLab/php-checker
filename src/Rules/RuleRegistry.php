<?php

declare(strict_types=1);

namespace PhpChecker\Rules;

use PhpChecker\Config\CheckerConfig;
use PhpChecker\Rules\Documentation\RequireMethodPhpDocRule;

final class RuleRegistry
{
	/**
	 * @return array<string, class-string>
	 */
	public function all(): array
	{
		return [
			'phpdoc.method' => RequireMethodPhpDocRule::class,
		];
	}

	public function getEnabledRules(CheckerConfig $config): array
	{
		$rules = [];

		foreach ($this->all() as $id => $class) {
			if ($config->shouldUseRule($id)) {
				$rules[$id] = $class;
			}
		}

		return $rules;
	}
}
