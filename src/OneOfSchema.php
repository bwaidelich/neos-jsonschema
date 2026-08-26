<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use ArrayIterator;
use IteratorAggregate;
use Neos\JsonSchema\Support\Discriminator;
use Neos\JsonSchema\Validation\ProvidesValidation;
use Traversable;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/combining#oneof
 * @implements IteratorAggregate<Schema>
 */
final readonly class OneOfSchema implements IteratorAggregate, Schema
{
    use ProvidesValidation;

    /**
     * @param array<Schema> $items
     */
    private function __construct(
        private array $items,
        public Discriminator|null $discriminator,
    ) {}

    public static function create(Schema ...$items): self
    {
        return new self($items, null);
    }

    public function withDiscriminator(Discriminator $discriminator): self
    {
        return new self($this->items, $discriminator);
    }

    public function jsonSerialize(): array
    {
        $result = [
            'oneOf' => $this->items,
        ];
        if ($this->discriminator !== null) {
            $result['discriminator'] = $this->discriminator;
        }
        return $result;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
