<?php

declare(strict_types=1);

namespace PhpChecker\Rules\Documentation;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
final class RequireMethodPhpDocRule implements Rule
{
	public function getNodeType(): string
	{
		return ClassMethod::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($node->getDocComment() !== null) {
			return [];
		}

		$methodName = $node->name->toString();

		return [
			RuleErrorBuilder::message(
				sprintf(
					'Method %s() must have PHPDoc.',
					$methodName
				)
			)->line($node->getStartLine())->build(),
		];
	}
}
