<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use JsonSerializable;
use Neos\JsonSchema\Validation\ValidationResult;

/**
 * @phpstan-sealed AllOfSchema|AnyOfSchema|ArraySchema|BooleanSchema|IntegerSchema|NotSchema|NullSchema|NumberSchema|ObjectSchema|OneOfSchema|ReferenceSchema|StringSchema
 */
interface Schema extends JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;

    /**
     * Validate a value against this schema. Thin sugar over {@see \Neos\JsonSchema\Validation\Validator}.
     *
     * @throws \Neos\JsonSchema\Validation\UnsupportedKeywordException if the schema uses an assertion the validator cannot enforce
     */
    #[\NoDiscard('inspect the ValidationResult; discarding it means the validation was pointless')]
    public function validate(mixed $value): ValidationResult;
}
