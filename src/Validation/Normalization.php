<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

/**
 * How lenient {@see \Neos\JsonSchema\Schema::validate()} is about the *type* of the scalars it is given.
 *
 * Both modes check the schema's assertions and project the value onto its structure – they differ only in whether
 * a scalar that is merely *spelled* like the declared type is accepted:
 *
 * | Step                                             | {@see self::None} | {@see self::Scalars} |
 * | ------------------------------------------------ | ----------------- | -------------------- |
 * | validate against the schema                       | yes               | yes                  |
 * | project – reshape to the schema's structure       | yes               | yes                  |
 * | normalize scalars (`"45"` -> `45`, `"true"` -> `true`) | no          | yes                  |
 *
 * Pick per input source: a decoded JSON body carries real types, so `"45"` for an integer property is a client
 * error and {@see self::None} – the default – is right; a path / query / header parameter is *always* a string, so
 * it needs {@see self::Scalars}.
 *
 * @api
 */
enum Normalization
{
    /**
     * Strict: the value must already be the JSON primitive the schema declares.
     */
    case None;

    /**
     * Lenient: a scalar spelled like the declared type is converted to it before validation – a numeric string
     * for an integer / number schema (`"45"` -> `45`, `"4.5"` -> `4.5`), and `"true"` / `"false"` / `"1"` / `"0"`
     * (or the integers `1` / `0`) for a boolean schema.
     *
     * The boolean spellings are the ones OpenAPI's `form` style produces for a query parameter; anything else –
     * `"yes"`, `"on"`, the empty string – stays what it is, and the Validator rejects it.
     */
    case Scalars;
}
