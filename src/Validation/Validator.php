<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

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

/**
 * Validates a value against a schema, and hands back what it read: three steps, in order.
 *
 * 1. *normalize* – depending on {@see Normalization}, convert scalars that are merely spelled like the declared
 *    type (`"45"` -> `45`, `"true"` -> `true`). Off by default;
 * 2. *check* – delegated in full to the {@see Assertions} visitor, so constraint logic lives in exactly one place;
 * 3. *project* – reshape the value to the schema's structure, which is {@see Projection} (inbound): `stdClass` to
 *    array, object properties in the order the schema declares them, followed by the undeclared keys the schema
 *    permits (`additionalProperties: false` makes those an issue in step 2, so there are none left to carry).
 *
 * A valid {@see ValidationResult} therefore carries a {@see ValidationResult::value()}: the input as the schema
 * describes it, ready for a consumer that maps it onto its own types. A caller that only asked the yes/no question
 * reads {@see ValidationResult::$valid} and ignores it.
 *
 * Reflection-free and pure: it neither knows nor creates PHP objects (bar unwrapping `stdClass` input).
 *
 * Schema types with no single structural reading – {@see AllOfSchema}, {@see \Neos\JsonSchema\NotSchema},
 * {@see \Neos\JsonSchema\ReferenceSchema} – are left untouched by steps 1 and 3: there is no honest way to pick
 * *which* branch's shape the result should take, and silently guessing would lose data. They are still checked
 * (and `$ref` still raises {@see UnsupportedKeywordException} there).
 *
 * Step 3 is the only step a *producer* also needs, which is why it lives in {@see Projection} rather than here:
 * writing a value out faces the same ambiguity reading one in does, from the other side.
 *
 * @internal to be invoked via {@see Schema::validate()}
 */
final class Validator
{
    #[\NoDiscard('inspect the ValidationResult; discarding it means the validation was pointless')]
    public static function validate(Schema $schema, mixed $input, Normalization $normalization = Normalization::None): ValidationResult
    {
        $normalized = $normalization === Normalization::Scalars ? self::normalize($schema, $input) : $input;
        $issues = Assertions::check($schema, $normalized);
        if ($issues !== []) {
            return ValidationResult::invalid(...$issues);
        }
        return ValidationResult::valid(Projection::inbound($schema, $normalized));
    }

    /**
     * Best-effort scalar type normalization only (no constraint checks): produces the value the Validator then
     * judges. Values it cannot normalize are passed through unchanged, so {@see Assertions} reports the precise
     * violation instead of this method guessing.
     */
    private static function normalize(Schema $schema, mixed $input): mixed
    {
        if ($schema instanceof IntegerSchema) {
            return is_string($input) && preg_match('/^-?\d+$/', $input) === 1 ? (int) $input : $input;
        }
        if ($schema instanceof NumberSchema) {
            return is_string($input) && is_numeric($input) ? $input + 0 : $input;
        }
        if ($schema instanceof BooleanSchema) {
            return match ($input) {
                'true', '1', 1 => true,
                'false', '0', 0 => false,
                default => $input,
            };
        }
        if ($schema instanceof ObjectSchema) {
            return self::normalizeObject($schema, $input);
        }
        if ($schema instanceof ArraySchema) {
            return self::normalizeArray($schema, $input);
        }
        if ($schema instanceof AnyOfSchema || $schema instanceof OneOfSchema) {
            return self::normalizeBranches($schema, $input);
        }
        if ($schema instanceof AllOfSchema) {
            foreach ($schema as $branch) {
                $input = self::normalize($branch, $input);
            }
            return $input;
        }
        return $input;
    }

    private static function normalizeObject(ObjectSchema $schema, mixed $input): mixed
    {
        if (is_object($input)) {
            $input = get_object_vars($input);
        }
        if (!is_array($input) || ($input !== [] && array_is_list($input)) || $schema->properties === null) {
            return $input;
        }
        $normalized = [];
        foreach ($input as $key => $value) {
            $propertySchema = is_string($key) ? $schema->properties->get($key) : null;
            $normalized[$key] = $propertySchema === null ? $value : self::normalize($propertySchema, $value);
        }
        return $normalized;
    }

    private static function normalizeArray(ArraySchema $schema, mixed $input): mixed
    {
        if (!is_array($input) || ($input !== [] && !array_is_list($input))) {
            return $input;
        }
        $normalized = [];
        foreach ($input as $index => $item) {
            $itemSchema = $schema->itemSchema($index);
            $normalized[] = $itemSchema instanceof Schema ? self::normalize($itemSchema, $item) : $item;
        }
        return $normalized;
    }

    /**
     * A union is normalized through the branch the normalized value ends up matching – the first one that does,
     * since a value cannot be two shapes at once.
     *
     * When it matches none, the canonical "nullable" idiom `anyOf: [<something>, {"type": "null"}]` still has an
     * answer: the value was meant to be that one substantive branch, so normalize through it and let the Validator
     * report why it is not. A genuine multi-branch union has no such answer and is passed through untouched. This
     * mirrors how {@see Assertions} picks the issues it reports for the same two cases.
     */
    private static function normalizeBranches(AnyOfSchema|OneOfSchema $schema, mixed $input): mixed
    {
        $substantiveBranches = [];
        foreach ($schema as $branch) {
            $normalized = self::normalize($branch, $input);
            if (Assertions::check($branch, $normalized) === []) {
                return $normalized;
            }
            if (!$branch instanceof NullSchema) {
                $substantiveBranches[] = $branch;
            }
        }
        if (count($substantiveBranches) === 1) {
            return self::normalize($substantiveBranches[0], $input);
        }
        return $input;
    }
}
