<?php

declare(strict_types=1);

namespace Neos\JsonSchema;

use Neos\JsonSchema\Validation\ProvidesValidation;
use stdClass;

/**
 * The schema that accepts anything: JSON Schema's *empty schema*, `{}`.
 *
 * @see https://json-schema.org/understanding-json-schema/basics#the-true-and-false-schemas
 */
final readonly class AnySchema implements Schema
{
    use ProvidesValidation;

    /**
     * @param array<mixed>|null $examples
     */
    private function __construct(
        public string|null $title,
        public string|null $description,
        public mixed $default,
        public array|null $examples,
        public bool|null $readOnly,
        public bool|null $writeOnly,
        public bool|null $deprecated,
        public string|null $comment,
    ) {}

    /**
     * @param array<mixed>|null $examples
     */
    public static function create(
        string|null $title = null,
        string|null $description = null,
        mixed $default = null,
        array|null $examples = null,
        bool|null $readOnly = null,
        bool|null $writeOnly = null,
        bool|null $deprecated = null,
        string|null $comment = null,
    ): self {
        return new self($title, $description, $default, $examples, $readOnly, $writeOnly, $deprecated, $comment);
    }

    public function jsonSerialize(): array|stdClass
    {
        /** @var array<string, mixed> $array */
        $array = array_filter(get_object_vars($this), static fn($v) => $v !== null);
        if ($this->comment !== null) {
            unset($array['comment']);
            $array['$comment'] = $this->comment;
        }

        // no annotations either: the empty schema itself, which has to encode as an object
        return $array === [] ? new stdClass() : $array;
    }
}
