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
 * never has to ask first. {@see self::unwrap()} is the way back: the substantive branch of that union, for a
 * consumer that has to know what a member *is* and does not care that it may also be absent.
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

    /**
     * The one substantive branch of a union, or the schema itself when it is not one.
     *
     * This undoes {@see self::wrap()}, and answers the same question for any union written by hand that happens
     * to have one non-null branch: a nullable member still *is* that member.
     *
     * A genuine multi-branch union is handed back as it is. Narrowing one means validating a value against its
     * branches, which is {@see Validation\Assertions}' job and needs a value; this is about the schema alone.
     *
     * @return ($schema is null ? null : Schema)
     */
    public static function unwrap(Schema|null $schema): Schema|null
    {
        if (!$schema instanceof AnyOfSchema && !$schema instanceof OneOfSchema) {
            return $schema;
        }
        $substantive = [];
        foreach ($schema as $branch) {
            if (!$branch instanceof NullSchema) {
                $substantive[] = $branch;
            }
        }
        return count($substantive) === 1 ? self::unwrap($substantive[0]) : $schema;
    }
}
