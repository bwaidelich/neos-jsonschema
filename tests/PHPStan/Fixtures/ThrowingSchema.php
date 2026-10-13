<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;

final readonly class ThrowingSchema implements ProvidesSchema
{
    public static function schema(): Schema
    {
        throw new \RuntimeException('not yet');
    }
}
