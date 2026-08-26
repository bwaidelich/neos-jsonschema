<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Support\ObjectProperties;
use Neos\JsonSchema\Validation\ProvidesValidation;

/**
 * @see https://json-schema.org/understanding-json-schema/reference/object
 */
final readonly class ObjectSchema implements Schema
{
    use ProvidesValidation;

    /**
     * @param array<mixed>|null $default
     * @param array<array<mixed>>|null $examples
     * @param array<mixed>|null $const
     * @param array<string>|null $required
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
        public ObjectProperties|null $properties,
        // TODO add patternProperties
        public bool|null $additionalProperties,
        // TODO add unevaluatedProperties
        public array|null $required,
        public StringSchema|null $propertyNames,
        public int|null $minProperties,
        public int|null $maxProperties,
        // TODO add dependentRequired https://json-schema.org/understanding-json-schema/reference/conditionals
        // TODO add dependentSchemas https://json-schema.org/understanding-json-schema/reference/conditionals
        // TODO add if-then-else https://json-schema.org/understanding-json-schema/reference/conditionals#ifthenelse
    ) {
        if ($this->required === []) {
            throw new \InvalidArgumentException('The "required" property must be null or a non-empty array', 1783413696);
        }
    }

    /**
     * @param array<mixed>|null $default
     * @param array<array<mixed>>|null $examples
     * @param array<mixed>|null $const
     * @param array<string>|null $required
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
        ObjectProperties|null $properties = null,
        bool|null $additionalProperties = null,
        array|null $required = null,
        StringSchema|null $propertyNames = null,
        int|null $minProperties = null,
        int|null $maxProperties = null,
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
            $properties,
            $additionalProperties,
            $required,
            $propertyNames,
            $minProperties,
            $maxProperties,
        );
    }

    /**
     * @param array<mixed>|null $default
     * @param array<array<mixed>>|null $examples
     * @param array<mixed>|null $const
     * @param array<string>|null $required
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
        ObjectProperties|null $properties = null,
        bool|null $additionalProperties = null,
        array|null $required = null,
        StringSchema|null $propertyNames = null,
        int|null $minProperties = null,
        int|null $maxProperties = null,
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
            $properties ?? $this->properties,
            $additionalProperties ?? $this->additionalProperties,
            $required ?? $this->required,
            $propertyNames ?? $this->propertyNames,
            $minProperties ?? $this->minProperties,
            $maxProperties ?? $this->maxProperties,
        );
    }



    public function jsonSerialize(): array
    {
        /** @var array<string, mixed> $array */
        $array = [
            'type' => 'object',
            ...array_filter(get_object_vars($this), static fn($v) => $v !== null),
        ];
        if ($this->comment !== null) {
            unset($array['comment']);
            $array['$comment'] = $this->comment;
        }
        return $array;
    }
}
