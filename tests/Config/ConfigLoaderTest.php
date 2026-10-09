<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Config;

use PhpChecker\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigLoaderTest extends TestCase
{
	private string $projectRoot;

	private ConfigLoader $configLoader;

	protected function setUp(): void
	{
		$this->configLoader = new ConfigLoader();
		$this->projectRoot = sys_get_temp_dir()
			. '/php-checker-config-'
			. bin2hex(random_bytes(6));

		mkdir($this->projectRoot);
	}

	protected function tearDown(): void
	{
		$configPath = $this->configLoader->getConfigPath($this->projectRoot);

		if (is_file($configPath)) {
			unlink($configPath);
		}

		if (is_dir($this->projectRoot)) {
			rmdir($this->projectRoot);
		}
	}

	public function testConfigPathUsesPhpCheckerJson(): void
	{
		$this->assertSame(
			$this->projectRoot . '/php-checker.json',
			$this->configLoader->getConfigPath($this->projectRoot),
		);
	}

	public function testHasConfigIsFalseBeforeSaving(): void
	{
		$this->assertFalse(
			$this->configLoader->hasConfig($this->projectRoot),
		);
	}

	public function testSaveAndLoadRoundTrip(): void
	{
		$this->configLoader->save($this->projectRoot, [
			'phpdoc.method',
			'typeDeclaration.return',
		]);

		$this->assertTrue(
			$this->configLoader->hasConfig($this->projectRoot),
		);

		$this->assertSame(
			[
				'version' => 1,
				'enabledRules' => [
					'phpdoc.method',
					'typeDeclaration.return',
				],
			],
			$this->configLoader->load($this->projectRoot),
		);
	}

	public function testSavePersistsAnEmptyRuleSelection(): void
	{
		$this->configLoader->save($this->projectRoot, []);

		$this->assertSame(
			[
				'version' => 1,
				'enabledRules' => [],
			],
			$this->configLoader->load($this->projectRoot),
		);
	}

	public function testSaveRemovesDuplicateRules(): void
	{
		$this->configLoader->save($this->projectRoot, [
			'phpdoc.method',
			'phpdoc.method',
		]);

		$this->assertSame(
			['phpdoc.method'],
			$this->configLoader->load($this->projectRoot)['enabledRules'],
		);
	}

	public function testLoadMissingConfigurationThrows(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Configuration file not found');

		$this->configLoader->load($this->projectRoot);
	}

	public function testLoadInvalidJsonThrows(): void
	{
		file_put_contents(
			$this->configLoader->getConfigPath($this->projectRoot),
			'{not valid json',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Invalid JSON in configuration file');

		$this->configLoader->load($this->projectRoot);
	}

	public function testLoadUnsupportedVersionThrows(): void
	{
		file_put_contents(
			$this->configLoader->getConfigPath($this->projectRoot),
			'{"version":2,"enabledRules":[]}',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unsupported configuration version');

		$this->configLoader->load($this->projectRoot);
	}

	public function testLoadMissingEnabledRulesThrows(): void
	{
		file_put_contents(
			$this->configLoader->getConfigPath($this->projectRoot),
			'{"version":1}',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('"enabledRules" must be an array');

		$this->configLoader->load($this->projectRoot);
	}

	public function testLoadNonStringRuleThrows(): void
	{
		file_put_contents(
			$this->configLoader->getConfigPath($this->projectRoot),
			'{"version":1,"enabledRules":[123]}',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Invalid rule identifier');

		$this->configLoader->load($this->projectRoot);
	}
}
