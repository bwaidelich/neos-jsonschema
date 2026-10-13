<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\StringSchema;

final readonly class Handle implements ProvidesSchema
{
    private function __construct(
        public string $value,
    ) {}

    public static function fromString(string $value): self
    {
        self::schema()->validate($value)->throwIfInvalid();
        return new self($value);
    }

    public static function schema(): StringSchema
    {
        static $schema = null;
        return $schema ??= StringSchema::create(minLength: 1, pattern: '^[a-z]+$');
    }
}
