<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\StringSchema;

final class NodeAggregateId implements ProvidesSchema
{
    public function __construct(
        public readonly int $value,
    ) {
        self::schema()->validate($value)->throwIfInvalid();
    }

    public static function schema(): StringSchema
    {
        static $schema;
        return $schema ?? StringSchema::create(minLength: 1, maxLength: 64, pattern: '^[a-z0-9-]+$');
    }
}
