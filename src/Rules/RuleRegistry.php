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

	/**
	 * Returns the identifiers that are not registered in this registry.
	 *
	 * The configuration is an allowlist, so unknown identifiers are a
	 * configuration error rather than something to silently ignore.
	 *
	 * @param list<string> $identifiers
	 *
	 * @return list<string>
	 */
	public function getUnknownRules(array $identifiers): array
	{
		$known = array_fill_keys(array_keys($this->all()), true);

		return array_values(array_filter(
			$identifiers,
			static fn (string $identifier): bool => ! isset($known[$identifier]),
		));
	}

	/**
	 * Built-in PHPStan error identifiers that a custom rule fully replaces.
	 *
	 * A custom rule and its built-in counterpart report the same problem on
	 * the same node, so running both would duplicate every violation. When
	 * the custom rule is enabled the built-in identifier is suppressed.
	 *
	 * @return array<string, list<string>>
	 */
	public function getSupersededBuiltInIdentifiers(): array
	{
		return [
			'typeDeclaration.parameter' => ['missingType.parameter'],
			'typeDeclaration.return' => ['missingType.return'],
		];
	}

	/**
	 * Built-in identifiers that must be ignored for the current selection.
	 *
	 * Only identifiers belonging to an enabled custom rule are returned, so
	 * PHPStan's built-in analysis keeps running for every other rule.
	 *
	 * @return list<string>
	 */
	public function getSuppressedBuiltInIdentifiers(CheckerConfig $config): array
	{
		$superseded = $this->getSupersededBuiltInIdentifiers();
		$suppressed = [];

		foreach (array_keys($this->getEnabledRules($config)) as $ruleId) {
			foreach ($superseded[$ruleId] ?? [] as $identifier) {
				$suppressed[] = $identifier;
			}
		}

		return array_values(array_unique($suppressed));
	}
}
