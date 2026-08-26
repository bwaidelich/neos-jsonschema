<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/numeric#integer
 */
final readonly class IntegerSchema implements Schema
{
    use ProvidesValidation;

    /**
     * @param array<int>|null $examples
     * @param array<int>|null $enum
     */
    private function __construct(
        public string|null $title,
        public string|null $description,
        public int|null $default,
        public array|null $examples,
        public bool|null $readOnly,
        public bool|null $writeOnly,
        public bool|null $deprecated,
        public string|null $comment,
        public array|null $enum,
        public int|null $const,
        public int|null $multipleOf,
        public int|null $minimum,
        public bool|null $exclusiveMinimum,
        public int|null $maximum,
        public bool|null $exclusiveMaximum,
    ) {}

    /**
     * @param array<int>|null $examples
     * @param array<int>|null $enum
     */
    public static function create(
        string|null $title = null,
        string|null $description = null,
        int|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $enum = null,
        int|null $const = null,
        int|null $multipleOf = null,
        int|null $minimum = null,
        bool|null $exclusiveMinimum = null,
        int|null $maximum = null,
        bool|null $exclusiveMaximum = null,
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
            $enum,
            $const,
            $multipleOf,
            $minimum,
            $exclusiveMinimum,
            $maximum,
            $exclusiveMaximum,
        );
    }

    /**
     * @param array<int>|null $examples
     * @param array<int>|null $enum
     */
    public function with(
        string|null $title = null,
        string|null $description = null,
        int|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $enum = null,
        int|null $const = null,
        int|null $multipleOf = null,
        int|null $minimum = null,
        bool|null $exclusiveMinimum = null,
        int|null $maximum = null,
        bool|null $exclusiveMaximum = null,
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
            $enum ?? $this->enum,
            $const ?? $this->const,
            $multipleOf ?? $this->multipleOf,
            $minimum ?? $this->minimum,
            $exclusiveMinimum ?? $this->exclusiveMinimum,
            $maximum ?? $this->maximum,
            $exclusiveMaximum ?? $this->exclusiveMaximum,
        );
    }

    public function jsonSerialize(): array
    {
        /** @var array<string, mixed> $array */
        $array = [
            'type' => 'integer',
            ...array_filter(get_object_vars($this), static fn($v) => $v !== null),
        ];
        if ($this->comment) {
            unset($array['comment']);
            $array['$comment'] = $this->comment;
        }
        return $array;
    }
}
