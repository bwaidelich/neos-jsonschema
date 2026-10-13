<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

final readonly class InconsistentSchema implements ProvidesSchema
{
    public static function schema(): ObjectSchema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                name: StringSchema::create(default: 'x', minLength: 5, maxLength: 3),
                code: StringSchema::create(pattern: '(unclosed'),
                count: IntegerSchema::create(minimum: 10, maximum: 1),
                tags: ArraySchema::create(items: StringSchema::create(examples: ['c'], enum: ['a', 'b'])),
                choice: AnyOfSchema::create(),
            ),
            required: ['name', 'missing'],
        );
    }
}
