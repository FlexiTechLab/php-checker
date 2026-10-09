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
 * Requires explicit type declarations for function and method parameters.
 *
 * @implements Rule<FunctionLike>
 */
final class RequireParameterTypeRule implements Rule
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

		$callableName = $node instanceof ClassMethod
			? sprintf('Method %s()', $node->name->toString())
			: sprintf('Function %s()', $node->name->toString());

		$errors = [];

		foreach ($node->getParams() as $parameter) {
			if ($parameter->type !== null) {
				continue;
			}

			$parameterName = $parameter->var instanceof Node\Expr\Variable
				&& is_string($parameter->var->name)
				? '$' . $parameter->var->name
				: 'parameter';

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'%s parameter %s must declare a type.',
					$callableName,
					$parameterName,
				),
			)
				->identifier('typeDeclaration.parameter')
				->line($parameter->getStartLine())
				->build();
		}

		return $errors;
	}
}
