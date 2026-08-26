<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

/**
 * Implemented by a class (typically a value object) that owns a validation constraint and wants to expose its type.
 *
 * The class can then self-validate via `self::schema()->validate($value)` and – crucially – any other package can
 * read its type through `$class::schema()` while depending on **neos/jsonschema only**, never on neos/schematic.
 * neos/schematic bridges any such class into a coercible/instantiable schema via a dedicated generation Middleware.
 *
 * `schema()` is static because a value object's schema is class-level (identical for every instance); implementations
 * should memoize the (often many-argument) Schema in a `static $schemaRuntimeCache = null;` variable declared inside
 * `schema()` itself, building it on first call and returning it thereafter.
 *
 * A static *property* is the more obvious place, but it is unavailable in the two shapes value objects most commonly
 * take: in a `readonly` class the modifier propagates to static properties, and neither resulting form is legal – a
 * readonly property may not have a default value, and a static property may not be readonly. Enums, in turn, must not
 * declare properties at all. Memoizing inside the method keeps `final readonly class` and `enum` available to
 * implementors, and scopes the cache so that nothing but `schema()` can reach it.
 */
interface ProvidesSchema
{
    public static function schema(): Schema;
}
