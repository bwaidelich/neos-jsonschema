<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Support;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Neos\JsonSchema\Schema;
use Traversable;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/object#properties
 * @implements IteratorAggregate<string, Schema>
 */
final class ObjectProperties implements IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, Schema> $properties
     */
    private function __construct(
        private readonly array $properties,
    ) {}

    public static function create(Schema ...$properties): self
    {
        if (array_keys($properties) !== array_filter(\array_keys($properties), '\is_string')) {
            throw new \InvalidArgumentException('Properties has to be a map with string keys', 1783413614);
        }
        /** @var array<string, Schema> $properties */
        return new self($properties);
    }

    /**
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->properties);
    }

    /**
     * The schema declared for a property, or `null` if it declares none.
     */
    public function get(string $name): Schema|null
    {
        return $this->properties[$name] ?? null;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->properties);
    }

    /**
     * @return object{string: Schema}
     */
    public function jsonSerialize(): object
    {
        return (object) $this->properties; // @phpstan-ignore return.type
    }
}
