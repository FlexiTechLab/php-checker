<?php

declare(strict_types=1);

namespace PhpChecker\Reporting;

final readonly class Violation
{
	public function __construct(
		public string $file,
		public int $line,
		public string $message,
		public ?string $identifier = null,
	) {}
}
