<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Rules\TypeSafety;

use PhpChecker\Rules\TypeSafety\DisallowMixedTypeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<DisallowMixedTypeRule>
 */
final class DisallowMixedTypeRuleTest extends RuleTestCase
{
	protected function getRule(): Rule
	{
		return new DisallowMixedTypeRule();
	}

	public function testMixedParameterAndReturnTypesAreDisallowed(): void
	{
		$this->analyse(
			[
				__DIR__ . '/../../fixtures/Rules/TypeDeclaration/TypeDeclarationFixture.php',
			],
			[
				[
					'Function mixedTypes() parameter $value must not use mixed.',
					10,
				],
				[
					'Function mixedTypes() must not declare mixed as its return type.',
					10,
				],
			],
		);
	}
}
