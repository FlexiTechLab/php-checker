<?php

declare(strict_types=1);

namespace PhpChecker\Rules\TypeSafety;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Disallows explicit native mixed types in function and method signatures.
 *
 * @implements Rule<FunctionLike>
 */
final class DisallowMixedTypeRule implements Rule
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
			if (!$this->isExplicitMixed($parameter->type)) {
				continue;
			}

			$parameterName = $parameter->var instanceof Node\Expr\Variable
				&& is_string($parameter->var->name)
				? '$' . $parameter->var->name
				: 'parameter';

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'%s parameter %s must not use mixed.',
					$callableName,
					$parameterName,
				),
			)
				->identifier('typeSafety.disallowMixed')
				->line($parameter->getStartLine())
				->build();
		}

		if ($this->isExplicitMixed($node->getReturnType())) {
			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'%s must not declare mixed as its return type.',
					$callableName,
				),
			)
				->identifier('typeSafety.disallowMixed')
				->line($node->getStartLine())
				->build();
		}

		return $errors;
	}

	private function isExplicitMixed(?Node $type): bool
	{
		return $type instanceof Identifier
			&& strtolower($type->toString()) === 'mixed';
	}
}
