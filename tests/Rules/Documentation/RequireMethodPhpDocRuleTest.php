<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Rules\Documentation;

use PhpChecker\Rules\Documentation\RequireMethodPhpDocRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RequireMethodPhpDocRule>
 */
final class RequireMethodPhpDocRuleTest extends RuleTestCase
{
	protected function getRule(): Rule
	{
		return new RequireMethodPhpDocRule();
	}

	public function testMethodMustHavePhpDoc(): void
	{
		$this->analyse(
			[
				__DIR__ . '/../../fixtures/Rules/Documentation/RequireMethodPhpDocRuleFixture.php',
			],
			[
				[
					'Method withoutDoc() must have PHPDoc.',
					12,
				],
			],
		);
	}
}
