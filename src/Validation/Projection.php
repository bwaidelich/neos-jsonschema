<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\NotSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\Schema;

/**
 * Reshapes a value onto the structure its schema describes — the same walk in both directions.
 *
 * **Why a direction exists at all.** PHP has one `array` type where JSON has two structures, so `[]` alone cannot
 * say whether it was an empty object or an empty list, and neither can the value holding it. The schema can, and
 * it is the only thing that can — which is why this is the last step of reading a value in ({@see Validator}) and
 * has to be a step of writing one back out as well. A producer that skipped it would emit `[]` for a member its
 * own published schema describes as an object.
 *
 * The two directions differ in exactly one place, {@see self::object()}, and only because they face opposite ways:
 *
 * | | {@see self::inbound()} | {@see self::outbound()} |
 * | --- | --- | --- |
 * | `stdClass` | unwrapped to an array, so consumers see one shape | left alone; `json_encode` wants the object |
 * | `[]` under an `ObjectSchema` | stays `[]` — PHP has nothing else to offer a consumer | becomes `{}` |
 * | keys the schema does not name | dropped under `additionalProperties: false` | always kept |
 *
 * That last row is a deliberate asymmetry: dropping inbound discards what a client sent that the document never
 * promised to read, while dropping outbound would discard what the producer's own code chose to emit — silently,
 * where the honest answer is to let it out and let a conformance check report the disagreement.
 *
 * Schema types with no single structural reading — {@see AllOfSchema}, {@see NotSchema},
 * {@see ReferenceSchema} — are handed back untouched in both directions: there is no honest way to pick *which*
 * branch's shape the result should take, and guessing would lose data.
 *
 * Reflection-free and pure: it neither knows nor creates PHP objects, bar `stdClass`.
 */
final readonly class Projection
{
    private function __construct(
        private bool $outbound,
    ) {}

    /**
     * The value as the schema describes it, for a consumer that maps it onto its own types.
     *
     * Only sound once the assertions have passed — {@see Validator} is where that order is kept.
     */
    public static function inbound(Schema $schema, mixed $value): mixed
    {
        return (new self(false))->project($schema, $value);
    }

    /**
     * The value as the schema describes it, for `json_encode()`.
     *
     * The caller supplies the schema the value is *published* under, which for a producer with a document is the
     * declared type rather than whatever the runtime value happens to be.
     */
    public static function outbound(Schema $schema, mixed $value): mixed
    {
        return (new self(true))->project($schema, $value);
    }

    private function project(Schema $schema, mixed $value): mixed
    {
        if ($schema instanceof ObjectSchema) {
            return $this->object($schema, $value);
        }
        if ($schema instanceof ArraySchema) {
            return $this->array($schema, $value);
        }
        if ($schema instanceof AnyOfSchema || $schema instanceof OneOfSchema) {
            return $this->branches($schema, $value);
        }
        return $value;
    }

    private function object(ObjectSchema $schema, mixed $value): mixed
    {
        if (!$this->outbound && is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if ($this->outbound && $value === []) {
            // the one thing the value cannot say about itself, and the schema just said it
            return new \stdClass();
        }
        if ($schema->properties === null) {
            // a free-form map: its members are described by nothing, so there is nothing here to shape
            return $value;
        }
        $projected = [];
        foreach ($schema->properties as $name => $propertySchema) {
            if (array_key_exists($name, $value)) {
                $projected[$name] = $this->project($propertySchema, $value[$name]);
            }
        }
        if (!$this->outbound && $schema->additionalProperties === false) {
            // the schema forbids them, so Assertions has already reported any that arrived
            return $projected;
        }
        return $projected + $value;
    }

    private function array(ArraySchema $schema, mixed $value): mixed
    {
        if (!is_array($value) || ($value !== [] && !array_is_list($value))) {
            return $value;
        }
        $projected = [];
        foreach ($value as $index => $item) {
            $itemSchema = $schema->itemSchema($index);
            $projected[] = $itemSchema instanceof Schema ? $this->project($itemSchema, $item) : $item;
        }
        return $projected;
    }

    /**
     * A union is shaped by the branch the value matches — the first one that does, since a value cannot be two
     * shapes at once, and since `[]` matching both an object branch and an array branch is the very ambiguity
     * being resolved here. Matching none leaves it untouched.
     */
    private function branches(AnyOfSchema|OneOfSchema $schema, mixed $value): mixed
    {
        foreach ($schema as $branch) {
            if (Assertions::check($branch, $value) === []) {
                return $this->project($branch, $value);
            }
        }
        return $value;
    }
}
