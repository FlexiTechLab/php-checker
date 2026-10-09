<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Rules;

use PhpChecker\Config\CheckerConfig;
use PhpChecker\Rules\Documentation\RequireMethodPhpDocRule;
use PhpChecker\Rules\RuleRegistry;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase
{
	public function testEmptySelectionYieldsNoRules(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			[],
			$registry->getEnabledRules(new CheckerConfig(useRules: [])),
		);
	}

	public function testOnlySelectedRulesAreReturned(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			['phpdoc.method' => RequireMethodPhpDocRule::class],
			$registry->getEnabledRules(
				new CheckerConfig(useRules: ['phpdoc.method']),
			),
		);
	}

	public function testUnknownIdentifiersAreIgnored(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			[],
			$registry->getEnabledRules(
				new CheckerConfig(useRules: ['does.not.exist']),
			),
		);
	}

	public function testEveryRegisteredRuleCanBeSelected(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			$registry->all(),
			$registry->getEnabledRules(
				new CheckerConfig(useRules: array_keys($registry->all())),
			),
		);
	}

	public function testOverlappingRulesDeclareTheirBuiltInCounterparts(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			[
				'typeDeclaration.parameter' => ['missingType.parameter'],
				'typeDeclaration.return' => ['missingType.return'],
			],
			$registry->getSupersededBuiltInIdentifiers(),
		);
	}

	public function testOnlyEnabledRulesSuppressTheirBuiltInCounterparts(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			['missingType.parameter'],
			$registry->getSuppressedBuiltInIdentifiers(
				new CheckerConfig(useRules: ['typeDeclaration.parameter']),
			),
		);

		$this->assertSame(
			['missingType.return'],
			$registry->getSuppressedBuiltInIdentifiers(
				new CheckerConfig(useRules: ['typeDeclaration.return']),
			),
		);

		$this->assertSame(
			[],
			$registry->getSuppressedBuiltInIdentifiers(
				new CheckerConfig(useRules: ['phpdoc.method']),
			),
		);

		$this->assertSame(
			[],
			$registry->getSuppressedBuiltInIdentifiers(
				new CheckerConfig(useRules: []),
			),
		);
	}

	public function testBothTypeDeclarationRulesSuppressBothBuiltIns(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			['missingType.parameter', 'missingType.return'],
			$registry->getSuppressedBuiltInIdentifiers(
				new CheckerConfig(
					useRules: [
						'typeDeclaration.parameter',
						'typeDeclaration.return',
					],
				),
			),
		);
	}

	public function testUnknownRulesAreReported(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame(
			['does.not.exist', 'another.missing'],
			$registry->getUnknownRules([
				'phpdoc.method',
				'does.not.exist',
				'another.missing',
			]),
		);
	}

	public function testNoUnknownRulesForEmptyOrValidSelection(): void
	{
		$registry = new RuleRegistry();

		$this->assertSame([], $registry->getUnknownRules([]));
		$this->assertSame(
			[],
			$registry->getUnknownRules(array_keys($registry->all())),
		);
	}
}
