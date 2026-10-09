<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Rules\TypeDeclaration;

use PhpChecker\Rules\TypeDeclaration\RequireParameterTypeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RequireParameterTypeRule>
 */
final class RequireParameterTypeRuleTest extends RuleTestCase
{
	protected function getRule(): Rule
	{
		return new RequireParameterTypeRule();
	}

	public function testParametersMustDeclareTypes(): void
	{
		$this->analyse(
			[
				__DIR__ . '/../../fixtures/Rules/TypeDeclaration/TypeDeclarationFixture.php',
			],
			[
				[
					'Function missingParameter() parameter $id must declare a type.',
					5,
				],
				[
					'Method missingMethodParameter() parameter $id must declare a type.',
					27,
				],
			],
		);
	}
}
