<?php

declare(strict_types=1);

namespace PhpChecker\Rules;

use PhpChecker\Config\CheckerConfig;
use PhpChecker\Rules\Documentation\RequireMethodPhpDocRule;
use PhpChecker\Rules\TypeDeclaration\RequireParameterTypeRule;
use PhpChecker\Rules\TypeDeclaration\RequireReturnTypeRule;
use PhpChecker\Rules\TypeSafety\DisallowMixedTypeRule;

final class RuleRegistry
{
	/**
	 * @return array<string, class-string>
	 */
	public function all(): array
	{
		return [
			'phpdoc.method' => RequireMethodPhpDocRule::class,

			'typeDeclaration.parameter' => RequireParameterTypeRule::class,

			'typeDeclaration.return' => RequireReturnTypeRule::class,

			'typeSafety.disallowMixed' => DisallowMixedTypeRule::class,
		];
	}

	/**
	 * @return array<string, class-string>
	 */
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
