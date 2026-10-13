<?php

declare(strict_types=1);

namespace Neos\JsonSchema\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports the broken memoization idiom `static $schema; return $schema ?? XSchema::create(...);` in a
 * {@see \Neos\JsonSchema\ProvidesSchema::schema()} implementation: the static variable is never assigned, so the
 * schema is rebuilt on every call. `return $schema ??= XSchema::create(...);` is what was meant.
 *
 * @implements Rule<InClassMethodNode>
 * @internal registered via the extension.neon of this package
 */
final readonly class NotMemoizedSchemaRule implements Rule
{
    public function __construct(
        private SchemaResolver $schemaResolver,
    ) {}

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $method = $node->getOriginalNode();
        if ($method->name->toLowerString() !== 'schema' || !$method->isStatic() || $method->stmts === null) {
            return [];
        }
        if (!$this->schemaResolver->providesSchema($node->getClassReflection()->getName())) {
            return [];
        }
        $finder = new NodeFinder();
        $staticNames = [];
        foreach ($finder->findInstanceOf($method->stmts, Static_::class) as $static) {
            foreach ($static->vars as $staticVar) {
                if (is_string($staticVar->var->name)) {
                    $staticNames[] = $staticVar->var->name;
                }
            }
        }
        $errors = [];
        foreach ($finder->findInstanceOf($method->stmts, Return_::class) as $return) {
            if (!$return->expr instanceof Coalesce || !$return->expr->left instanceof Variable) {
                continue;
            }
            $name = $return->expr->left->name;
            if (!is_string($name) || !in_array($name, $staticNames, true) || self::isAssigned($finder, $method->stmts, $name)) {
                continue;
            }
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Static variable $%1$s in %2$s::schema() is never assigned, so the schema is rebuilt on every call. Did you mean "return $%1$s ??= …"?',
                $name,
                $node->getClassReflection()->getDisplayName(),
            ))
                ->line($return->getStartLine())
                ->identifier('jsonSchema.schema.notMemoized')
                ->build();
        }
        return $errors;
    }

    /**
     * @param array<Node> $statements
     */
    private static function isAssigned(NodeFinder $finder, array $statements, string $name): bool
    {
        return $finder->findFirst($statements, static fn(Node $node): bool => ($node instanceof Assign || $node instanceof AssignRef || $node instanceof CoalesceAssign)
            && $node->var instanceof Variable
            && $node->var->name === $name) !== null;
    }
}
