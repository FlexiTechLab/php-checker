<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Rules\TypeDeclaration;

use PhpChecker\Rules\TypeDeclaration\RequireReturnTypeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RequireReturnTypeRule>
 */
final class RequireReturnTypeRuleTest extends RuleTestCase
{
	protected function getRule(): Rule
	{
		return new RequireReturnTypeRule();
	}

	public function testFunctionsAndMethodsMustDeclareReturnTypes(): void
	{
		$this->analyse(
			[
				__DIR__ . '/../../fixtures/Rules/TypeDeclaration/TypeDeclarationFixture.php',
			],
			[
				[
					'Function missingReturnType() must declare a return type.',
					15,
				],
				[
					'Method missingMethodReturn() must declare a return type.',
					32,
				],
			],
		);
	}
}
