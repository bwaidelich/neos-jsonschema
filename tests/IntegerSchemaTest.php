<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\IntegerSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntegerSchema::class)]
final class IntegerSchemaTest extends TestCase
{
    public function test_fully_fledged(): void
    {
        $schema = IntegerSchema::create(
            title: 'some title',
            description: 'some description',
            default: 123,
            examples: [321, 222],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            enum: [123,321,222],
            const: 321,
            multipleOf: 2,
            minimum: 3,
            exclusiveMinimum: true,
            maximum: 10,
            exclusiveMaximum: true,
        );
        self::assertJsonStringEqualsJsonString('{"type":"integer","title":"some title","description":"some description","default":123,"examples":[321,222],"readOnly":true,"writeOnly":false,"deprecated":true,"enum":[123,321,222],"const":321,"multipleOf":2,"minimum":3,"exclusiveMinimum":true,"maximum":10,"exclusiveMaximum":true,"$comment":"some comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_wither(): void
    {
        $schema = IntegerSchema::create(
            title: 'some title',
            description: 'some description',
            default: 123,
            examples: [321, 222],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            enum: [123,321,222],
            const: 321,
            multipleOf: 2,
            minimum: 3,
            exclusiveMinimum: true,
            maximum: 10,
            exclusiveMaximum: true,
        );
        $schema = $schema->with(
            title: 'some changed title',
            description: 'some changed description',
            default: 321,
            examples: [333, 124],
            readOnly: false,
            writeOnly: true,
            deprecated: false,
            comment: 'some changed comment',
            enum: [333,124,222],
            const: 123,
            multipleOf: 3,
            minimum: 4,
            exclusiveMinimum: false,
            maximum: 12,
            exclusiveMaximum: false,
        );
        self::assertJsonStringEqualsJsonString('{"type":"integer","title":"some changed title","description":"some changed description","default":321,"examples":[333,124],"readOnly":false,"writeOnly":true,"deprecated":false,"enum":[333,124,222],"const":123,"multipleOf":3,"minimum":4,"exclusiveMinimum":false,"maximum":12,"exclusiveMaximum":false,"$comment":"some changed comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

}
