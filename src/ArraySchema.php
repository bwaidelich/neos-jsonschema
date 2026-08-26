<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Support\ArrayItems;
use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/array
 */
final readonly class ArraySchema implements Schema
{
    use ProvidesValidation;

    /**
     * @param array<mixed>|null $default
     * @param array<array<mixed>>|null $examples
     * @param array<mixed>|null $const
     */
    private function __construct(
        public string|null $title,
        public string|null $description,
        public array|null $default,
        public array|null $examples,
        public bool|null $readOnly,
        public bool|null $writeOnly,
        public bool|null $deprecated,
        public string|null $comment,
        public array|null $const,
        public Schema|false|null $items,
        public ArrayItems|null $prefixItems,
        public ArrayItems|false|Schema|null $unevaluatedItems,
        public Schema|null $contains,
        public int|null $minContains,
        public int|null $maxContains,
        public int|null $minItems,
        public int|null $maxItems,
        public bool|null $uniqueItems,
    ) {
        if ($this->contains === null) {
            if ($this->minContains !== null) {
                throw new \InvalidArgumentException('"minContains" can only be used if "contains" is defined', 1783413242);
            }
            if ($this->maxContains !== null) {
                throw new \InvalidArgumentException('"maxContains" can only be used if "contains" is defined', 1783413243);
            }
        }
    }

    /**
     * @param array<mixed>|null $default
     * @param array<array<mixed>>|null $examples
     * @param array<mixed>|null $const
     */
    public static function create(
        string|null $title = null,
        string|null $description = null,
        array|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $const = null,
        Schema|false|null $items = null,
        ArrayItems|null $prefixItems = null,
        ArrayItems|false|Schema|null $unevaluatedItems = null,
        Schema|null $contains = null,
        int|null $minContains = null,
        int|null $maxContains = null,
        int|null $minItems = null,
        int|null $maxItems = null,
        bool|null $uniqueItems = null,
    ): self {
        return new self(
            $title,
            $description,
            $default,
            $examples,
            $readOnly,
            $writeOnly,
            $deprecated,
            $comment,
            $const,
            $items,
            $prefixItems,
            $unevaluatedItems,
            $contains,
            $minContains,
            $maxContains,
            $minItems,
            $maxItems,
            $uniqueItems,
        );
    }

    /**
     * @param array<mixed>|null $default
     * @param array<array<mixed>>|null $examples
     * @param array<mixed>|null $const
     */
    public function with(
        string|null $title = null,
        string|null $description = null,
        array|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $const = null,
        Schema|false|null $items = null,
        ArrayItems|null $prefixItems = null,
        ArrayItems|false|Schema|null $unevaluatedItems = null,
        Schema|null $contains = null,
        int|null $minContains = null,
        int|null $maxContains = null,
        int|null $minItems = null,
        int|null $maxItems = null,
        bool|null $uniqueItems = null,
    ): self {
        return new self(
            $title ?? $this->title,
            $description ?? $this->description,
            $default ?? $this->default,
            $examples ?? $this->examples,
            $readOnly ?? $this->readOnly,
            $writeOnly ?? $this->writeOnly,
            $deprecated ?? $this->deprecated,
            $comment ?? $this->comment,
            $const ?? $this->const,
            $items ?? $this->items,
            $prefixItems ?? $this->prefixItems,
            $unevaluatedItems ?? $this->unevaluatedItems,
            $contains ?? $this->contains,
            $minContains ?? $this->minContains,
            $maxContains ?? $this->maxContains,
            $minItems ?? $this->minItems,
            $maxItems ?? $this->maxItems,
            $uniqueItems ?? $this->uniqueItems,
        );
    }


    public function jsonSerialize(): array
    {
        /** @var array<string, mixed> $array */
        $array = [
            'type' => 'array',
            ...array_filter(get_object_vars($this), static fn($v) => $v !== null),
        ];
        if ($this->comment) {
            unset($array['comment']);
            $array['$comment'] = $this->comment;
        }
        return $array;
    }
}
