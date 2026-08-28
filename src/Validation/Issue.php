<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

use Stringable;

/**
 * A single, path-located validation failure: a machine-readable {@see IssueCode} (stored as its string value),
 * a human message, and the path to the offending value.
 */
final readonly class Issue implements Stringable
{
    /**
     * @param list<string|int> $path The path to the offending value (property names / array indices), empty = root
     */
    private function __construct(
        public array $path,
        public string $code,
        public string $message,
    ) {}

    /**
     * @param list<string|int> $path
     */
    public static function create(array $path, IssueCode|string $code, string $message): self
    {
        return new self($path, $code instanceof IssueCode ? $code->value : $code, $message);
    }

    public function pathAsString(): string
    {
        if ($this->path === []) {
            return '<root>';
        }
        return implode('.', array_map(strval(...), $this->path));
    }

    /**
     * The path as a JSON Pointer (RFC 6901), e.g. "/items/0/name" – the root path being the empty string.
     *
     * Prefix the result with "#" for the URI fragment form that error formats like RFC 9457 ("Problem Details")
     * use, turning the root path into "#".
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6901
     */
    public function pathAsJsonPointer(): string
    {
        // "~" has to be escaped before "/" so that the "~1" introduced by the latter is not escaped again
        return implode('', array_map(
            static fn(string|int $segment): string => '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $segment),
            $this->path,
        ));
    }

    public function __toString(): string
    {
        return sprintf('%s: %s (%s)', $this->pathAsString(), $this->message, $this->code);
    }
}
