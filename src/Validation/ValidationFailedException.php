<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

/**
 * Thrown by {@see ValidationResult::throwIfInvalid()} — the one place this package raises *invalid input* rather
 * than reporting it.
 *
 * Validation returns a {@see ValidationResult} because invalid input is expected, not exceptional, and a caller
 * handling a request wants every issue at once. A caller enforcing an invariant is in the other position: there is
 * nothing to report to and nothing to hand back, so it asks for the exception instead. The {@see self::$issues}
 * come along, so what was wrong is still readable — with the paths validation found them at.
 */
final class ValidationFailedException extends \RuntimeException
{
    private function __construct(
        string $message,
        public readonly Issues $issues,
    ) {
        parent::__construct($message, 1751884803);
    }

    public static function forIssues(Issues $issues): self
    {
        if ($issues->isEmpty()) {
            throw new \InvalidArgumentException('A ValidationFailedException requires at least one issue', 1751884804);
        }
        return new self(sprintf('Validation failed: %s', $issues), $issues);
    }
}
