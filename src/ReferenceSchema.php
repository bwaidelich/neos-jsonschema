<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/structuring#dollarref
 */
final readonly class ReferenceSchema implements Schema
{
    use ProvidesValidation;

    private function __construct(
        public string $ref,
    ) {}

    public static function create(string $ref): self
    {
        return new self($ref);
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return ['$ref' => $this->ref];
    }
}
