<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use JsonSerializable;
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
     * Does this value conform to this schema? The {@see ValidationResult} says so, and lists every way in which it
     * does not. Thin sugar over {@see \Neos\JsonSchema\Validation\Validator}.
     *
     * The value is judged exactly as given: nothing is converted, and nothing is handed back but the verdict. A
     * value is expected as JSON decoded to associative arrays – `json_decode($json, true)`.
     *
     * @throws \Neos\JsonSchema\Validation\UnsupportedKeywordException if the schema uses an assertion the validator cannot enforce
     */
    #[\NoDiscard('inspect the ValidationResult; discarding it means the validation was pointless')]
    public function validate(mixed $value): ValidationResult;
}
