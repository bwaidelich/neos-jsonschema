<?php

declare(strict_types=1);

namespace Neos\JsonSchema\PHPStan;

use Neos\JsonSchema\ProvidesSchema;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp\Coalesce;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

/**
 * Types the memoization idiom `static $schema = null; return $schema ??= XSchema::create(...);` of a
 * {@see ProvidesSchema::schema()} implementation as the schema it builds.
 *
 * PHPStan knows nothing about the value a static variable holds from a previous call, so it would type the
 * expression as `mixed` and report the return type of `schema()`. The idiom assigns nothing but the built schema to
 * the variable, which makes that schema the type of the expression. A variable with a known type (e.g. annotated
 * via `@var`) is left to PHPStan.
 *
 * @internal registered via the extension.neon of this package
 */
final class MemoizedSchemaTypeResolver implements ExpressionTypeResolverExtension
{
    public function getType(Expr $expr, Scope $scope): Type|null
    {
        if (!$expr instanceof Coalesce || !$expr->var instanceof Variable || !is_string($expr->var->name)) {
            return null;
        }
        $function = $scope->getFunction();
        $classReflection = $scope->getClassReflection();
        if (
            $function === null
            || $classReflection === null
            || strtolower($function->getName()) !== 'schema'
            || !$classReflection->implementsInterface(ProvidesSchema::class)
        ) {
            return null;
        }
        if (!$scope->hasVariableType($expr->var->name)->yes() || !$scope->getVariableType($expr->var->name) instanceof MixedType) {
            return null;
        }
        return $scope->getType($expr->expr);
    }
}
