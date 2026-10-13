<?php

declare(strict_types=1);

namespace Neos\JsonSchema\PHPStan;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Reports a `$schema->validate($value)` call whose value can never conform to the schema, like an `int` handed to
 * a {@see StringSchema}.
 *
 * The schema is the actual one where the receiver is `SomeClass::schema()` of a
 * {@see \Neos\JsonSchema\ProvidesSchema} class; otherwise only the receiver's static schema class is known, which
 * still pins down the JSON type.
 *
 * @implements Rule<MethodCall>
 * @internal registered via the extension.neon of this package
 */
final readonly class IncompatibleValidateArgumentRule implements Rule
{
    public function __construct(
        private SchemaResolver $schemaResolver,
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->toLowerString() !== 'validate' || $node->isFirstClassCallable()) {
            return [];
        }
        $arguments = $node->getArgs();
        if (count($arguments) !== 1) {
            return [];
        }
        $receiverType = $scope->getType($node->var);
        if (!(new ObjectType(Schema::class))->isSuperTypeOf($receiverType)->yes()) {
            return [];
        }
        [$schema, $label] = $this->schemaOfReceiver($node->var, $receiverType->getObjectClassNames(), $scope);
        if ($schema === null) {
            return [];
        }
        $reason = ValueCompatibility::whyNeverValid($scope->getType($arguments[0]->value), $schema);
        if ($reason === null) {
            return [];
        }
        return [
            RuleErrorBuilder::message(sprintf('Value passed to %s->validate() can never be valid: %s.', $label, $reason))
                ->identifier('jsonSchema.validate.incompatibleValue')
                ->build(),
        ];
    }

    /**
     * @param list<string> $receiverClassNames
     * @return array{Schema|null, string}
     */
    private function schemaOfReceiver(Node\Expr $receiver, array $receiverClassNames, Scope $scope): array
    {
        if (
            $receiver instanceof StaticCall
            && $receiver->class instanceof Name
            && $receiver->name instanceof Identifier
            && $receiver->name->toLowerString() === 'schema'
        ) {
            $className = $scope->resolveName($receiver->class);
            $schema = $this->schemaResolver->resolve($className);
            if ($schema instanceof Schema) {
                return [$schema, sprintf('%s::schema()', $className)];
            }
        }
        if (count($receiverClassNames) !== 1) {
            return [null, ''];
        }
        // the bare schema of the receiver's class constrains nothing but the JSON type, which is all that is known
        $schema = match ($receiverClassNames[0]) {
            StringSchema::class => StringSchema::create(),
            IntegerSchema::class => IntegerSchema::create(),
            NumberSchema::class => NumberSchema::create(),
            BooleanSchema::class => BooleanSchema::create(),
            NullSchema::class => NullSchema::create(),
            ObjectSchema::class => ObjectSchema::create(),
            ArraySchema::class => ArraySchema::create(),
            default => null,
        };
        return [$schema, $receiverClassNames[0]];
    }
}
