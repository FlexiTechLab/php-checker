<?php

declare(strict_types=1);

namespace PhpChecker\Tests\CLI;

use PhpChecker\CLI\Application;
use PHPUnit\Framework\TestCase;

final class CheckCommandTest extends TestCase
{
	public function testApplicationCanBeInstantiated(): void
	{
		$application = new Application();

		$this->assertInstanceOf(Application::class, $application);
	}
}
