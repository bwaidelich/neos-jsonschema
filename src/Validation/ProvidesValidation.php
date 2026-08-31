<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

use Neos\JsonSchema\Schema;

/**
 * Provides the thin {@see \Neos\JsonSchema\Schema::validate()} sugar: `$schema->validate($value)` delegates to the
 * {@see Validator}. Used by every concrete schema type so the algorithm stays in one place while the
 * fluent call site lives on the value object.
 *
 * @phpstan-require-implements \Neos\JsonSchema\Schema
 * @internal used by {@see Schema implementation}
 */
trait ProvidesValidation
{
    #[\NoDiscard('inspect the ValidationResult; discarding it means the validation was pointless')]
    public function validate(mixed $value): ValidationResult
    {
        return Validator::validate($this, $value);
    }
}
