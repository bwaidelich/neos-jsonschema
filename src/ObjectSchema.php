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

    /**
     * The same schema with a `description` for each of the named properties, keyed by property name:
     * `$schema->withPropertyDescriptions(title: 'The title as printed on the cover')`.
     *
     * A description replaces whatever the property's schema describes itself as, since it is the more specific of
     * the two: the same `Slug` may be "the post's address" in one place and "the author's handle" in another.
     *
     * A nullable property (see {@see Nullable}) is described on its substantive branch, which is what a reader looks
     * at to learn what the property is. Anything else that has no `description` of its own – a genuine union, a
     * `$ref` – fails loud rather than dropping the description, and so does a property this schema does not declare.
     */
    public function withPropertyDescriptions(string ...$descriptions): self
    {
        $properties = $this->properties === null ? [] : iterator_to_array($this->properties);
        foreach ($descriptions as $name => $description) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Property descriptions have to be a map with string keys', 1791456494);
            }
            if (!isset($properties[$name])) {
                throw new \InvalidArgumentException(sprintf('Cannot describe property "%s": the object schema declares no such property', $name), 1791456495);
            }
            $properties[$name] = self::describe($properties[$name], $description)
                ?? throw new \InvalidArgumentException(sprintf('Cannot describe property "%s": its schema has no `description` of its own', $name), 1791456496);
        }
        return $this->with(properties: ObjectProperties::create(...$properties));
    }

    private static function describe(Schema $schema, string $description): Schema|null
    {
        if (
            $schema instanceof StringSchema
            || $schema instanceof IntegerSchema
            || $schema instanceof NumberSchema
            || $schema instanceof BooleanSchema
            || $schema instanceof ArraySchema
            || $schema instanceof ObjectSchema
        ) {
            return $schema->with(description: $description);
        }
        if ($schema instanceof AnySchema) {
            return AnySchema::create($schema->title, $description, $schema->default, $schema->examples, $schema->readOnly, $schema->writeOnly, $schema->deprecated, $schema->comment);
        }
        // only the `anyOf` that Nullable::wrap() builds, so that wrapping the described branch again restores it
        if (!$schema instanceof AnyOfSchema) {
            return null;
        }
        $substantive = Nullable::unwrap($schema);
        if ($substantive === $schema) {
            return null;
        }
        $described = self::describe($substantive, $description);
        return $described === null ? null : Nullable::wrap($described);
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
