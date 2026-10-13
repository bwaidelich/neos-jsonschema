<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\StringSchema;

use function PHPStan\Testing\assertType;

final readonly class MemoizedSchemaTypes implements ProvidesSchema
{
    public static function schema(): StringSchema
    {
        static $schema = null;
        assertType('Neos\JsonSchema\StringSchema', $schema ??= StringSchema::create());

        /** @var StringSchema|int|null $annotated */
        static $annotated = null;
        assertType('int|Neos\JsonSchema\StringSchema', $annotated ??= StringSchema::create());

        return $schema ??= StringSchema::create();
    }

    public static function notASchemaMethod(): void
    {
        static $schema = null;
        assertType('mixed~null', $schema ??= StringSchema::create());
    }
}
