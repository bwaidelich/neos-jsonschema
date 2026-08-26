<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/combining#not
 */
final readonly class NotSchema implements Schema
{
    use ProvidesValidation;

    private function __construct(
        public Schema $schema,
    ) {}

    public static function create(Schema $schema): self
    {
        return new self($schema);
    }

    public function jsonSerialize(): array
    {
        return [
            'not' => $this->schema,
        ];
    }
}
