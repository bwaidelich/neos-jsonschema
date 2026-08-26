<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

/**
 * The outcome of validating a value against a schema: *either* valid, *or* the aggregated {@see Issues}.
 * Returned instead of throwing, since invalid input is expected, not exceptional.
 *
 * (An {@see UnsupportedKeywordException} – a schema the {@see Validator} cannot enforce – *is* thrown, because it
 * is a programming/representation error, not invalid input.)
 */
final readonly class ValidationResult
{
    private function __construct(
        public bool $valid,
        public Issues $issues,
    ) {}

    public static function valid(): self
    {
        return new self(true, new Issues());
    }

    public static function invalid(Issues|Issue ...$issues): self
    {
        $flat = [];
        foreach ($issues as $issue) {
            if ($issue instanceof Issues) {
                $flat = [...$flat, ...$issue->toArray()];
            } else {
                $flat[] = $issue;
            }
        }
        if ($flat === []) {
            throw new \InvalidArgumentException('An invalid ValidationResult requires at least one issue', 1751884802);
        }
        return new self(false, new Issues(...$flat));
    }
}
