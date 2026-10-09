<?php

declare(strict_types=1);

namespace PhpChecker\Tests\Support;

use PhpChecker\Support\TextWrapper;
use PHPUnit\Framework\TestCase;

final class TextWrapperTest extends TestCase
{
	public function testShortTextIsReturnedUnchanged(): void
	{
		$this->assertSame(
			['Short text.'],
			TextWrapper::wrap('Short text.', 80),
		);
	}

	public function testLongTextIsWrappedWithoutSplittingWords(): void
	{
		$this->assertSame(
			['the quick', 'brown fox', 'jumps'],
			TextWrapper::wrap('the quick brown fox jumps', 10),
		);
	}

	public function testAWordLongerThanTheWidthIsKeptIntact(): void
	{
		$this->assertSame(
			['supercalifragilistic'],
			TextWrapper::wrap('supercalifragilistic', 5),
		);
	}

	public function testExistingLineBreaksArePreserved(): void
	{
		$this->assertSame(
			['first', 'second'],
			TextWrapper::wrap("first\nsecond", 80),
		);
	}

	public function testNonPositiveWidthDoesNotFail(): void
	{
		$this->assertNotEmpty(TextWrapper::wrap('text', 0));
	}
}
