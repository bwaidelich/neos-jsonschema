<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use ArrayIterator;
use IteratorAggregate;
use Neos\JsonSchema\Validation\ProvidesValidation;
use Traversable;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/combining#anyOf
 * @implements IteratorAggregate<Schema>
 */
final readonly class AnyOfSchema implements IteratorAggregate, Schema
{
    use ProvidesValidation;

    /**
     * @param array<Schema> $items
     */
    private function __construct(private array $items) {}

    public static function create(Schema ...$items): self
    {
        return new self($items);
    }

    public function jsonSerialize(): array
    {
        return [
            'anyOf' => $this->items,
        ];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
