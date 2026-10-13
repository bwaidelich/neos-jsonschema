<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\StringSchema;

final readonly class Coordinates implements ProvidesSchema
{
    public static function schema(): ObjectSchema
    {
        static $schema = null;
        $schema = $schema ?? ObjectSchema::create(
            additionalProperties: StringSchema::create(minLength: 1),
        );
        return $schema;
    }
}
