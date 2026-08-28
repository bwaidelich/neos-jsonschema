<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

/**
 * The canonical JSON Schema idiom for "this, or null": `anyOf: [<something>, {"type": "null"}]`.
 *
 * JSON Schema has no nullability flag – a nullable value is a union with the null type, which is why this is a
 * helper producing a {@see Schema} rather than a keyword on one. {@see Validation\Validator} recognises the idiom
 * and normalizes a value through the substantive branch, so a wrapped schema keeps reporting *why* a value is not
 * that branch instead of only "matched no branch".
 *
 * {@see self::wrap()} is idempotent: a schema that already accepts null is handed back untouched, so a caller
 * never has to ask first.
 */
final class Nullable
{
    private function __construct() {}

    public static function wrap(Schema $schema): Schema
    {
        if ($schema instanceof NullSchema) {
            return $schema;
        }
        if (!$schema instanceof AnyOfSchema) {
            return AnyOfSchema::create($schema, NullSchema::create());
        }
        $branches = iterator_to_array($schema, false);
        foreach ($branches as $branch) {
            if ($branch instanceof NullSchema) {
                return $schema;
            }
        }
        // `anyOf` is associative, so the null branch joins the existing ones instead of nesting them
        $branches[] = NullSchema::create();
        return AnyOfSchema::create(...$branches);
    }
}
