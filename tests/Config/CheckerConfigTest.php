<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Config;

use PhpChecker\Config\CheckerConfig;
use PHPUnit\Framework\TestCase;

final class CheckerConfigTest extends TestCase
{
	public function testEmptyRuleSelectionRunsNoRules(): void
	{
		$config = new CheckerConfig(useRules: []);

		$this->assertFalse($config->shouldUseRule('phpdoc.method'));
		$this->assertFalse($config->shouldUseRule('typeDeclaration.return'));
	}

	public function testSelectedRulesAreUsed(): void
	{
		$config = new CheckerConfig(useRules: ['phpdoc.method']);

		$this->assertTrue($config->shouldUseRule('phpdoc.method'));
		$this->assertFalse($config->shouldUseRule('typeDeclaration.return'));
	}

	public function testSkippedRuleIsNeverUsedEvenWhenSelected(): void
	{
		$config = new CheckerConfig(
			useRules: ['phpdoc.method'],
			skipRules: ['phpdoc.method'],
		);

		$this->assertFalse($config->shouldUseRule('phpdoc.method'));
	}

	public function testSkipDoesNotEnableUnselectedRules(): void
	{
		$config = new CheckerConfig(
			useRules: ['phpdoc.method'],
			skipRules: ['typeDeclaration.return'],
		);

		$this->assertFalse($config->shouldUseRule('typeDeclaration.return'));
	}

	public function testBuiltInAnalysisIsDisabledByDefault(): void
	{
		$config = new CheckerConfig();

		$this->assertNull($config->getLevel());
	}

	public function testBuiltInLevelCanBeOptedIntoExplicitly(): void
	{
		$config = new CheckerConfig(level: 8);

		$this->assertSame(8, $config->getLevel());
	}
}
