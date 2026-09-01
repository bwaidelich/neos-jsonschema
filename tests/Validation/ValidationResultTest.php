<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Validation;

use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\ValidationFailedException;
use Neos\JsonSchema\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationResult::class)]
#[CoversClass(ValidationFailedException::class)]
final class ValidationResultTest extends TestCase
{
    /**
     * Asking the result this question also consumes it, so `$schema->validate($v)->throwIfInvalid()` satisfies the
     * `#[\NoDiscard]` on `validate()` — which is what makes the one-liner honest rather than a discarded result.
     */
    public function test_throwIfInvalid_returnsForAValidValue(): void
    {
        $result = StringSchema::create(minLength: 1)->validate('ada');
        $result->throwIfInvalid();

        self::assertTrue($result->valid);
    }

    public function test_throwIfInvalid_throwsWithTheIssuesForAnInvalidValue(): void
    {
        $result = StringSchema::create(minLength: 1, pattern: '^[a-z]+$')->validate('');

        try {
            $result->throwIfInvalid();
            self::fail('expected the validation to be raised');
        } catch (ValidationFailedException $exception) {
            self::assertSame([IssueCode::TooShort->value], $exception->issues->codes());
            self::assertStringContainsString('Validation failed:', $exception->getMessage());
        }
    }
}
