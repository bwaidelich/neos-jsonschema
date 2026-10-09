<?php

declare(strict_types=1);

namespace Neos\JsonSchema\PHPStan;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Validation\UnsupportedKeywordException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

/**
 * Decides whether a value of a statically known type can *never* conform to a schema.
 *
 * Every check is one-sided: it only answers "never" when no value of the type can conform, and stays silent
 * whenever PHPStan cannot rule a match out (`mixed`, `int|string`, an array of unknown shape, …). The answer
 * mirrors {@see \Neos\JsonSchema\Validation\Validator}: an object is a non-list array, a list a list array, an
 * integer an `int` (not an integral float).
 *
 * @internal only to be used by the PHPStan rules of this package
 */
final class ValueCompatibility
{
    private function __construct() {}

    /**
     * Why no value of the given type can conform to the schema, or `null` if one might.
     */
    public static function whyNeverValid(Type $type, Schema $schema): string|null
    {
        if ($type->isConstantScalarValue()->yes()) {
            return self::whyNoConstantIsValid($type, $schema);
        }
        return match (true) {
            $schema instanceof StringSchema => $type->isString()->no() ? self::expected('a string', $type) : null,
            $schema instanceof IntegerSchema => $type->isInteger()->no() ? self::expected('an integer', $type) : null,
            $schema instanceof NumberSchema => $type->isInteger()->no() && $type->isFloat()->no() ? self::expected('a number', $type) : null,
            $schema instanceof BooleanSchema => $type->isBoolean()->no() ? self::expected('a boolean', $type) : null,
            $schema instanceof NullSchema => $type->isNull()->no() ? self::expected('null', $type) : null,
            $schema instanceof ObjectSchema => self::whyNeverAnObject($type, $schema),
            $schema instanceof ArraySchema => self::whyNeverAList($type, $schema),
            $schema instanceof AllOfSchema => self::whyAnyBranchFails($type, $schema),
            $schema instanceof AnyOfSchema, $schema instanceof OneOfSchema => self::whyEveryBranchFails($type, $schema),
            // AnySchema accepts everything, NotSchema and ReferenceSchema cannot be decided on the type alone
            default => null,
        };
    }

    private static function whyNoConstantIsValid(Type $type, Schema $schema): string|null
    {
        $reasons = [];
        foreach ($type->getConstantScalarValues() as $value) {
            try {
                $result = $schema->validate($value);
            } catch (UnsupportedKeywordException) {
                return null;
            }
            if ($result->valid) {
                return null;
            }
            $issue = $result->issues->toArray()[0];
            $reasons[] = sprintf(
                '%s is invalid%s: %s',
                var_export($value, true),
                $issue->path === [] ? '' : sprintf(' at "%s"', $issue->pathAsString()),
                $issue->message,
            );
        }
        return $reasons === [] ? null : implode('; ', $reasons);
    }

    private static function whyNeverAnObject(Type $type, ObjectSchema $schema): string|null
    {
        if ($type->isArray()->no()) {
            return self::expected('an object (associative array)', $type);
        }
        if ($type->isList()->yes() && $type->isIterableAtLeastOnce()->yes()) {
            return self::expected('an object (associative array)', $type);
        }
        if (!$type->isConstantArray()->yes()) {
            return null;
        }
        $reasons = [];
        foreach ($type->getConstantArrays() as $arrayType) {
            $reason = self::whyShapeIsNeverAnObject($arrayType, $schema);
            if ($reason === null) {
                return null;
            }
            $reasons[] = $reason;
        }
        return $reasons === [] ? null : implode('; ', array_unique($reasons));
    }

    private static function whyShapeIsNeverAnObject(ConstantArrayType $arrayType, ObjectSchema $schema): string|null
    {
        if ($arrayType->isList()->yes() && $arrayType->isIterableAtLeastOnce()->yes()) {
            return self::expected('an object (associative array)', $arrayType);
        }
        /** @var array<string, array{Type, bool}> $members name => [value type, whether the key is optional] */
        $members = [];
        foreach ($arrayType->getKeyTypes() as $index => $keyType) {
            $members[(string) $keyType->getValue()] = [$arrayType->getValueTypes()[$index], $arrayType->isOptionalKey($index)];
        }
        foreach ($schema->required ?? [] as $name) {
            if (!array_key_exists($name, $members)) {
                return sprintf('required property "%s" is missing', $name);
            }
        }
        foreach ($members as $name => [$valueType, $optional]) {
            $name = (string) $name;
            if ($optional) {
                // an optional key may as well be absent, which no property schema can object to
                continue;
            }
            $propertySchema = $schema->properties?->get($name);
            if ($propertySchema === null) {
                if ($schema->additionalProperties === false) {
                    return sprintf('property "%s" is not declared and additional properties are not allowed', $name);
                }
                continue;
            }
            $reason = self::whyNeverValid($valueType, $propertySchema);
            if ($reason !== null) {
                return sprintf('property "%s": %s', $name, $reason);
            }
        }
        return null;
    }

    private static function whyNeverAList(Type $type, ArraySchema $schema): string|null
    {
        if ($type->isArray()->no()) {
            return self::expected('a list', $type);
        }
        if ($type->isList()->no() && $type->isIterableAtLeastOnce()->yes()) {
            return self::expected('a list', $type);
        }
        if ($type->isConstantArray()->yes()) {
            $reasons = [];
            foreach ($type->getConstantArrays() as $arrayType) {
                $reason = self::whyShapeIsNeverAList($arrayType, $schema);
                if ($reason === null) {
                    return null;
                }
                $reasons[] = $reason;
            }
            return $reasons === [] ? null : implode('; ', array_unique($reasons));
        }
        if ($schema->prefixItems === null && $schema->items instanceof Schema && $type->isIterableAtLeastOnce()->yes()) {
            $reason = self::whyNeverValid($type->getIterableValueType(), $schema->items);
            return $reason === null ? null : sprintf('items: %s', $reason);
        }
        return null;
    }

    private static function whyShapeIsNeverAList(ConstantArrayType $arrayType, ArraySchema $schema): string|null
    {
        if ($arrayType->isList()->no() && $arrayType->isIterableAtLeastOnce()->yes()) {
            return self::expected('a list', $arrayType);
        }
        $prefix = $schema->prefixItems !== null ? iterator_to_array($schema->prefixItems, false) : [];
        foreach ($arrayType->getValueTypes() as $index => $valueType) {
            if ($arrayType->isOptionalKey($index)) {
                continue;
            }
            $itemSchema = $prefix[$index] ?? $schema->items;
            if ($itemSchema === false) {
                return sprintf('item %d is not allowed, the list must not contain more than %d item(s)', $index, count($prefix));
            }
            if ($itemSchema === null) {
                continue;
            }
            $reason = self::whyNeverValid($valueType, $itemSchema);
            if ($reason !== null) {
                return sprintf('item %d: %s', $index, $reason);
            }
        }
        return null;
    }

    private static function whyAnyBranchFails(Type $type, AllOfSchema $schema): string|null
    {
        foreach ($schema as $branch) {
            $reason = self::whyNeverValid($type, $branch);
            if ($reason !== null) {
                return $reason;
            }
        }
        return null;
    }

    /**
     * @param AnyOfSchema|OneOfSchema $schema
     */
    private static function whyEveryBranchFails(Type $type, Schema&\IteratorAggregate $schema): string|null
    {
        $reasons = [];
        foreach ($schema as $branch) {
            $reason = self::whyNeverValid($type, $branch);
            if ($reason === null) {
                return null;
            }
            $reasons[] = $reason;
        }
        if ($reasons === []) {
            // an empty union is a defect of the schema itself, reported by InconsistentSchemaRule
            return null;
        }
        return count($reasons) === 1 ? $reasons[0] : sprintf('matches none of the branches (%s)', implode('; ', array_unique($reasons)));
    }

    private static function expected(string $expected, Type $actual): string
    {
        return sprintf('expected %s, got %s', $expected, $actual->describe(VerbosityLevel::value()));
    }
}
