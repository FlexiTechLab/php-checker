<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI\Prompt;

use PhpChecker\CLI\Prompt\CheckboxItem;
use PhpChecker\CLI\Prompt\CheckboxItemFilter;
use PHPUnit\Framework\TestCase;

final class CheckboxItemFilterTest extends TestCase
{
	/**
	 * @return list<CheckboxItem>
	 */
	private function items(): array
	{
		return [
			new CheckboxItem(
				'phpdoc.method',
				'Require Method PHPDoc',
				'Requires PHPDoc documentation for methods.',
			),
			new CheckboxItem(
				'typeDeclaration.parameter',
				'Require Parameter Types',
				'Requires explicit parameter types.',
			),
			new CheckboxItem(
				'typeDeclaration.return',
				'Require Return Types',
				'Requires explicit return types.',
			),
		];
	}

	/**
	 * @param list<CheckboxItem> $items
	 *
	 * @return list<string>
	 */
	private function identifiers(array $items): array
	{
		return array_map(
			static fn (CheckboxItem $item): string => $item->identifier,
			$items,
		);
	}

	public function testEmptyQueryReturnsEveryItem(): void
	{
		$filtered = CheckboxItemFilter::filter($this->items(), '');

		$this->assertSame(
			[
				'phpdoc.method',
				'typeDeclaration.parameter',
				'typeDeclaration.return',
			],
			$this->identifiers($filtered),
		);
	}

	public function testMatchesByNameCaseInsensitively(): void
	{
		$filtered = CheckboxItemFilter::filter($this->items(), 'METHOD PHPDOC');

		$this->assertSame(['phpdoc.method'], $this->identifiers($filtered));
	}

	public function testMatchesByIdentifierCaseInsensitively(): void
	{
		$filtered = CheckboxItemFilter::filter(
			$this->items(),
			'TYPEDECLARATION.PARAMETER',
		);

		$this->assertSame(
			['typeDeclaration.parameter'],
			$this->identifiers($filtered),
		);
	}

	public function testMatchesByDescriptionCaseInsensitively(): void
	{
		$filtered = CheckboxItemFilter::filter($this->items(), 'EXPLICIT RETURN');

		$this->assertSame(
			['typeDeclaration.return'],
			$this->identifiers($filtered),
		);
	}

	public function testMatchesAcrossMultipleFields(): void
	{
		$filtered = CheckboxItemFilter::filter($this->items(), 'type');

		$this->assertSame(
			['typeDeclaration.parameter', 'typeDeclaration.return'],
			$this->identifiers($filtered),
		);
	}

	public function testReturnsEmptyListWhenNothingMatches(): void
	{
		$filtered = CheckboxItemFilter::filter($this->items(), 'zzzz');

		$this->assertSame([], $filtered);
	}
}
