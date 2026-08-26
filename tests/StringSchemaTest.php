<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\StringFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringSchema::class)]
final class StringSchemaTest extends TestCase
{
    public function test_fully_fledged(): void
    {
        $schema = StringSchema::create(
            title: 'some title',
            description: 'some description',
            default: 'some default',
            examples: ['foo', 'bar'],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            enum: ['foo', 'bar', 'baz'],
            const: 'foo',
            minLength: 1,
            maxLength: 10,
            format: StringFormat::date,
            pattern: '^.*$',
            contentMediaType: 'application/json',
            contentEncoding: 'base64',
        );
        self::assertJsonStringEqualsJsonString('{"type":"string","title":"some title","description":"some description","default":"some default","examples":["foo","bar"],"readOnly":true,"writeOnly":false,"deprecated":true,"enum":["foo","bar","baz"],"const":"foo","minLength":1,"maxLength":10,"format":"date","pattern":"^.*$","contentMediaType":"application\/json","contentEncoding":"base64","$comment":"some comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_wither(): void
    {
        $schema = StringSchema::create(
            title: 'some title',
            description: 'some description',
            default: 'some default',
            examples: ['foo', 'bar'],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            enum: ['foo', 'bar', 'baz'],
            const: 'foo',
            minLength: 1,
            maxLength: 10,
            format: StringFormat::date,
            pattern: '^.*$',
            contentMediaType: 'application/json',
            contentEncoding: 'base64',
        );
        $schema = $schema->with(
            title: 'some changed title',
            description: 'some changed description',
            default: 'some changed default',
            examples: ['foo', 'changed'],
            readOnly: false,
            writeOnly: true,
            deprecated: false,
            comment: 'some changed comment',
            enum: ['foo', 'changed', 'baz'],
            const: 'changed',
            minLength: 2,
            maxLength: 11,
            format: StringFormat::email,
            pattern: '^.changed*$',
            contentMediaType: 'text/xml',
            contentEncoding: 'base32',
        );
        self::assertJsonStringEqualsJsonString('{"type":"string","title":"some changed title","description":"some changed description","default":"some changed default","examples":["foo","changed"],"readOnly":false,"writeOnly":true,"deprecated":false,"enum":["foo","changed","baz"],"const":"changed","minLength":2,"maxLength":11,"format":"email","pattern":"^.changed*$","contentMediaType":"text\/xml","contentEncoding":"base32","$comment":"some changed comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

}
