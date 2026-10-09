<?php

declare(strict_types=1);

namespace PhpChecker\Config;

use JsonException;
use RuntimeException;

final class ConfigLoader
{
	private const CONFIG_FILE = 'php-checker.json';

	private const CONFIG_VERSION = 1;

	public function getConfigPath(string $projectRoot): string
	{
		return rtrim($projectRoot, DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. self::CONFIG_FILE;
	}

	public function hasConfig(string $projectRoot): bool
	{
		return is_file($this->getConfigPath($projectRoot));
	}

	/**
	 * @return array{
	 *     version: int,
	 *     enabledRules: list<string>
	 * }
	 */
	public function load(string $projectRoot): array
	{
		$path = $this->getConfigPath($projectRoot);

		if (! is_file($path)) {
			throw new RuntimeException(
				sprintf(
					'Configuration file not found: %s. Run "bin/php-checker rules:configure" to create it.',
					$path,
				),
			);
		}

		$contents = file_get_contents($path);

		if ($contents === false) {
			throw new RuntimeException(
				sprintf('Unable to read configuration file: %s', $path),
			);
		}

		try {
			$config = json_decode(
				$contents,
				true,
				512,
				JSON_THROW_ON_ERROR,
			);
		} catch (JsonException $exception) {
			throw new RuntimeException(
				sprintf(
					'Invalid JSON in configuration file: %s (%s)',
					$path,
					$exception->getMessage(),
				),
				previous: $exception,
			);
		}

		if (! is_array($config)) {
			throw new RuntimeException(
				sprintf(
					'Invalid configuration in %s: expected a JSON object.',
					$path,
				),
			);
		}

		if (($config['version'] ?? null) !== self::CONFIG_VERSION) {
			throw new RuntimeException(
				sprintf(
					'Unsupported configuration version in %s: expected version %d.',
					$path,
					self::CONFIG_VERSION,
				),
			);
		}

		if (
			! isset($config['enabledRules'])
			|| ! is_array($config['enabledRules'])
		) {
			throw new RuntimeException(
				sprintf(
					'Invalid configuration in %s: "enabledRules" must be an array of rule identifiers.',
					$path,
				),
			);
		}

		foreach ($config['enabledRules'] as $rule) {
			if (! is_string($rule) || $rule === '') {
				throw new RuntimeException(
					sprintf(
						'Invalid rule identifier in %s: every rule must be a non-empty string.',
						$path,
					),
				);
			}
		}

		return [
			'version' => self::CONFIG_VERSION,
			'enabledRules' => array_values(
				array_unique($config['enabledRules']),
			),
		];
	}

	/**
	 * @param list<string> $enabledRules
	 */
	public function save(string $projectRoot, array $enabledRules): void
	{
		$path = $this->getConfigPath($projectRoot);

		$config = [
			'version' => self::CONFIG_VERSION,
			'enabledRules' => array_values(
				array_unique($enabledRules),
			),
		];

		try {
			$json = json_encode(
				$config,
				JSON_PRETTY_PRINT
					| JSON_UNESCAPED_SLASHES
					| JSON_THROW_ON_ERROR,
			);
		} catch (JsonException $exception) {
			throw new RuntimeException(
				'Unable to encode the checker configuration.',
				previous: $exception,
			);
		}

		if (file_put_contents($path, $json . PHP_EOL) === false) {
			throw new RuntimeException(
				sprintf('Unable to write configuration file: %s', $path),
			);
		}
	}
}
