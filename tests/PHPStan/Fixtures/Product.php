<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

final readonly class Product implements ProvidesSchema
{
    public static function schema(): ObjectSchema
    {
        static $schema = null;
        $schema = $schema ?? ObjectSchema::create(
            properties: ObjectProperties::create(
                title: StringSchema::create(minLength: 1),
                pages: IntegerSchema::create(minimum: 1),
                subtitle: Nullable::wrap(StringSchema::create()),
                tags: ArraySchema::create(items: StringSchema::create()),
            ),
            additionalProperties: false,
            required: ['title', 'pages'],
        );
        return $schema;
    }
}
