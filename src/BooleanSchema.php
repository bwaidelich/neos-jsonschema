<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/boolean
 */
final readonly class BooleanSchema implements Schema
{
    use ProvidesValidation;

    /**
     * @param array<bool>|null $examples
     */
    private function __construct(
        public string|null $title,
        public string|null $description,
        public bool|null $default,
        public array|null $examples,
        public bool|null $readOnly,
        public bool|null $writeOnly,
        public bool|null $deprecated,
        public string|null $comment,
        public bool|null $const,
    ) {}

    /**
     * @param array<bool>|null $examples
     */
    public static function create(
        string|null $title = null,
        string|null $description = null,
        bool|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        bool|null $const = null,
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
        );
    }

    /**
     * @param array<bool>|null $examples
     */
    public function with(
        string|null $title = null,
        string|null $description = null,
        bool|null $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
        bool|null $const = null,
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
        );
    }



    public function jsonSerialize(): array
    {
        /** @var array<string, mixed> $array */
        $array = [
            'type' => 'boolean',
            ...array_filter(get_object_vars($this), static fn($v) => $v !== null),
        ];
        if ($this->comment) {
            unset($array['comment']);
            $array['$comment'] = $this->comment;
        }
        return $array;
    }
}
