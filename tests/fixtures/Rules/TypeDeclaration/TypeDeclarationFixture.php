<?php

declare(strict_types=1);

function missingParameter($id): string
{
	return $id;
}

function mixedTypes(mixed $value): mixed
{
	return $value;
}

function missingReturnType(int $id)
{
	return (string) $id;
}

function validFunction(int $id): string
{
	return (string) $id;
}

class TypeDeclarationFixture
{
	public function missingMethodParameter($id): int
	{
		return $id;
	}

	public function missingMethodReturn(int $id)
	{
		return $id;
	}

	public function validMethod(int $id): int
	{
		return $id;
	}
}
