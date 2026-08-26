<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/numeric#number
 */
final readonly class NumberSchema implements Schema
{
    use ProvidesValidation;

    /**
     * @param array<int|float>|null $examples
     * @param array<int|float>|null $enum
     */
    private function __construct(
        public string|null $title,
        public string|null $description,
        public int|float|null $default,
        public array|null $examples,
        public bool|null $readOnly,
        public bool|null $writeOnly,
        public bool|null $deprecated,
        public string|null $comment,
        public array|null $enum,
        public int|float|null $const,
        public int|float|null $multipleOf,
        public int|float|null $minimum,
        public bool|null $exclusiveMinimum,
        public int|float|null $maximum,
        public bool|null $exclusiveMaximum,
    ) {}

    /**
     * @param array<int|float>|null $examples
     * @param array<int|float>|null $enum
     */
    public static function create(
        string|null $title = null,
        string|null $description = null,
        int|float|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $enum = null,
        int|float|null $const = null,
        int|float|null $multipleOf = null,
        int|float|null $minimum = null,
        bool|null $exclusiveMinimum = null,
        int|float|null $maximum = null,
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
     * @param array<int|float>|null $examples
     * @param array<int|float>|null $enum
     */
    public function with(
        string|null $title = null,
        string|null $description = null,
        int|float|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $enum = null,
        int|float|null $const = null,
        int|float|null $multipleOf = null,
        int|float|null $minimum = null,
        bool|null $exclusiveMinimum = null,
        int|float|null $maximum = null,
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
            'type' => 'number',
            ...array_filter(get_object_vars($this), static fn($v) => $v !== null),
        ];
        if ($this->comment) {
            unset($array['comment']);
            $array['$comment'] = $this->comment;
        }
        return $array;
    }
}
