<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/null
 */
final readonly class NullSchema implements Schema
{
    use ProvidesValidation;

    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }

    public function jsonSerialize(): array
    {
        return ['type' => 'null'];
    }
}
