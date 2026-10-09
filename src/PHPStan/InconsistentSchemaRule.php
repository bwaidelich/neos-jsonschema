<?php

declare(strict_types=1);

namespace Neos\JsonSchema\PHPStan;

use Neos\JsonSchema\Schema;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports a `schema()` of a {@see \Neos\JsonSchema\ProvidesSchema} class that throws, or that returns a schema
 * contradicting itself (see {@see SchemaConsistency}).
 *
 * @implements Rule<InClassMethodNode>
 * @internal registered via the extension.neon of this package
 */
final readonly class InconsistentSchemaRule implements Rule
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
        if ($method->name->toLowerString() !== 'schema' || !$method->isStatic() || $method->isAbstract()) {
            return [];
        }
        $className = $node->getClassReflection()->getName();
        if (!$this->schemaResolver->providesSchema($className)) {
            return [];
        }
        $schema = $this->schemaResolver->resolve($className);
        if ($schema instanceof \Throwable) {
            return [
                RuleErrorBuilder::message(sprintf('%s::schema() cannot be resolved, it throws %s: %s', $className, $schema::class, $schema->getMessage()))
                    ->identifier('jsonSchema.schema.unavailable')
                    ->build(),
            ];
        }
        if (!$schema instanceof Schema) {
            return [];
        }
        $errors = [];
        foreach (SchemaConsistency::defectsOf($schema) as $defect) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Schema of %s is inconsistent%s: %s.',
                $className,
                $defect['path'] === '' ? '' : sprintf(' at "%s"', $defect['path']),
                $defect['message'],
            ))
                ->identifier('jsonSchema.schema.inconsistent.' . $defect['kind'])
                ->build();
        }
        return $errors;
    }
}
