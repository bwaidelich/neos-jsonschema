<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

/**
 * The outcome of validating a value against a schema: *either* valid – carrying the {@see self::value()} the schema
 * read – *or* the aggregated {@see Issues}. Returned instead of throwing, since invalid input is expected, not
 * exceptional.
 *
 * A caller that only asked the yes/no question reads {@see self::$valid} and ignores the value.
 *
 * (An {@see UnsupportedKeywordException} – a schema the {@see Validator} cannot enforce – *is* thrown, because it
 * is a programming/representation error, not invalid input.)
 */
final readonly class ValidationResult
{
    private function __construct(
        public bool $valid,
        private mixed $value,
        public Issues $issues,
    ) {}

    public static function valid(mixed $value = null): self
    {
        return new self(true, $value, new Issues());
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
        return new self(false, null, new Issues(...$flat));
    }

    /**
     * What the schema read: the input normalized to the schema's primitive types (see {@see Normalization}) and
     * projected onto its structure – so *not* identical to the input the caller passed in.
     *
     * @throws \RuntimeException if this result is invalid
     */
    public function value(): mixed
    {
        if (!$this->valid) {
            throw new \RuntimeException('Cannot retrieve the value of an invalid ValidationResult', 1751884801);
        }
        return $this->value;
    }
}
