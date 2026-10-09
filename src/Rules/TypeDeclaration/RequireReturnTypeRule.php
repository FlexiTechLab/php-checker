<?php

declare(strict_types=1);

namespace PhpChecker\Rules\TypeDeclaration;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires explicit return type declarations for functions and methods.
 *
 * @implements Rule<FunctionLike>
 */
final class RequireReturnTypeRule implements Rule
{
	public function getNodeType(): string
	{
		return FunctionLike::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node instanceof Function_ && !$node instanceof ClassMethod) {
			return [];
		}

		if ($node->getReturnType() !== null) {
			return [];
		}

		$callableName = $node instanceof ClassMethod
			? sprintf('Method %s()', $node->name->toString())
			: sprintf('Function %s()', $node->name->toString());

		return [
			RuleErrorBuilder::message(
				sprintf(
					'%s must declare a return type.',
					$callableName,
				),
			)
				->identifier('typeDeclaration.return')
				->line($node->getStartLine())
				->build(),
		];
	}
}
