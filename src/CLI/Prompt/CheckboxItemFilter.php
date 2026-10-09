<?php

declare(strict_types=1);

namespace PhpChecker\CLI\Prompt;

/**
 * Case-insensitive filtering of checkbox items.
 *
 * Kept separate from {@see CheckboxList} so the matching rules can be
 * exercised on their own and the renderer stays free of search logic.
 */
final class CheckboxItemFilter
{
	/**
	 * Returns the items that match the query.
	 *
	 * An empty query matches everything. Matching ignores case and looks at
	 * the human-readable name, the stable identifier and the description.
	 *
	 * @param list<CheckboxItem> $items
	 *
	 * @return list<CheckboxItem>
	 */
	public static function filter(array $items, string $query): array
	{
		if ($query === '') {
			return $items;
		}

		$needle = strtolower($query);

		$matching = [];

		foreach ($items as $item) {
			if (
				str_contains(strtolower($item->name), $needle)
				|| str_contains(strtolower($item->identifier), $needle)
				|| str_contains(strtolower($item->description), $needle)
			) {
				$matching[] = $item;
			}
		}

		return $matching;
	}
}
