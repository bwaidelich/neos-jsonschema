<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\BooleanSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BooleanSchema::class)]
final class BooleanSchemaTest extends TestCase
{
    public function test_fully_fledged(): void
    {
        $schema = BooleanSchema::create(
            title: 'some title',
            description: 'some description',
            default: true,
            examples: [true, false],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            const: true,
        );
        self::assertJsonStringEqualsJsonString('{"type":"boolean","title":"some title","description":"some description","default":true,"examples":[true,false],"readOnly":true,"writeOnly":false,"deprecated":true,"const":true,"$comment":"some comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_wither(): void
    {
        $schema = BooleanSchema::create(
            title: 'some title',
            description: 'some description',
            default: true,
            examples: [true, false],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            const: true,
        );
        $schema = $schema->with(
            title: 'some changed title',
            description: 'some changed description',
            default: false,
            examples: [false, true],
            readOnly: false,
            writeOnly: true,
            deprecated: false,
            comment: 'some changed comment',
            const: false,
        );
        self::assertJsonStringEqualsJsonString('{"type":"boolean","title":"some changed title","description":"some changed description","default":false,"examples":[false,true],"readOnly":false,"writeOnly":true,"deprecated":false,"const":false,"$comment":"some changed comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

}
