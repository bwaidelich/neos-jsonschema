<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use JsonSerializable;
use Neos\JsonSchema\Validation\Normalization;
use Neos\JsonSchema\Validation\ValidationResult;

/**
 * @phpstan-sealed AllOfSchema|AnyOfSchema|AnySchema|ArraySchema|BooleanSchema|IntegerSchema|NotSchema|NullSchema|NumberSchema|ObjectSchema|OneOfSchema|ReferenceSchema|StringSchema
 */
interface Schema extends JsonSerializable
{
    /**
     * A `stdClass` only for {@see AnySchema} with no annotations set: the empty schema `{}`, which an empty PHP
     * array cannot encode as.
     *
     * @return array<string, mixed>|\stdClass
     */
    public function jsonSerialize(): array|\stdClass;

    /**
     * Validate a value against this schema, and read it: the {@see ValidationResult} of a valid value carries the
     * value as the schema describes it – normalized (see {@see Normalization}) and projected onto the schema's
     * structure. Thin sugar over {@see \Neos\JsonSchema\Validation\Validator}.
     *
     * @param Normalization $normalization whether a scalar merely *spelled* like the declared type is accepted –
     *                                     needed for input that is always string-typed, such as a query parameter
     * @throws \Neos\JsonSchema\Validation\UnsupportedKeywordException if the schema uses an assertion the validator cannot enforce
     */
    #[\NoDiscard('inspect the ValidationResult; discarding it means the validation was pointless')]
    public function validate(mixed $value, Normalization $normalization = Normalization::None): ValidationResult;
}
