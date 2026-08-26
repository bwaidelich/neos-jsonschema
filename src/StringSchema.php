<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Support\StringFormat;
use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/string
 */
final readonly class StringSchema implements Schema
{
    use ProvidesValidation;

    /**
     * @param array<string>|null $examples
     * @param array<string>|null $enum
     */
    private function __construct(
        public string|null $title,
        public string|null $description,
        public string|null $default,
        public array|null $examples,
        public bool|null $readOnly,
        public bool|null $writeOnly,
        public bool|null $deprecated,
        public string|null $comment,
        public array|null $enum,
        public string|null $const,
        public int|null $minLength,
        public int|null $maxLength,
        public StringFormat|null $format,
        public string|null $pattern,
        public string|null $contentMediaType,
        public string|null $contentEncoding,
    ) {}

    /**
     * @param array<string>|null $examples
     * @param array<string>|null $enum
     */
    public static function create(
        string|null $title = null,
        string|null $description = null,
        string|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $enum = null,
        string|null $const = null,
        int|null $minLength = null,
        int|null $maxLength = null,
        StringFormat|null $format = null,
        string|null $pattern = null,
        string|null $contentMediaType = null,
        string|null $contentEncoding = null,
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
            $minLength,
            $maxLength,
            $format,
            $pattern,
            $contentMediaType,
            $contentEncoding,
        );
    }

    /**
     * @param array<string>|null $examples
     * @param array<string>|null $enum
     */
    public function with(
        string|null $title = null,
        string|null $description = null,
        string|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        array|null $enum = null,
        string|null $const = null,
        int|null $minLength = null,
        int|null $maxLength = null,
        StringFormat|null $format = null,
        string|null $pattern = null,
        string|null $contentMediaType = null,
        string|null $contentEncoding = null,
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
            $minLength ?? $this->minLength,
            $maxLength ?? $this->maxLength,
            $format ?? $this->format,
            $pattern ?? $this->pattern,
            $contentMediaType ?? $this->contentMediaType,
            $contentEncoding ?? $this->contentEncoding,
        );
    }


    public function jsonSerialize(): array
    {
        /** @var array<string, mixed> $array */
        $array = [
            'type' => 'string',
            ...array_filter(get_object_vars($this), static fn($v) => $v !== null),
        ];
        if ($this->comment) {
            unset($array['comment']);
            $array['$comment'] = $this->comment;
        }
        if ($this->format !== null) {
            $array['format'] = $this->format->value;
        }
        return $array;
    }
}
