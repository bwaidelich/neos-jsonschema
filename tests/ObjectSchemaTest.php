<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\AnySchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectSchema::class)]
#[CoversClass(ObjectProperties::class)]
#[CoversClass(StringSchema::class)]
#[CoversClass(Nullable::class)]
final class ObjectSchemaTest extends TestCase
{
    public function test_fully_fledged(): void
    {
        $mockSchema1 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema1->expects($this->once())->method('jsonSerialize')->willReturn(['type' => 'mock1']);
        $mockSchema2 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema2->expects($this->once())->method('jsonSerialize')->willReturn(['type' => 'mock2']);
        $schema = ObjectSchema::create(
            title: 'some title',
            description: 'some description',
            default: ['foo' => 'bar'],
            examples: [['bar' => 'baz'], ['foos' => 'bars']],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            const: ['constant' => 'value'],
            properties: ObjectProperties::create(...['prop1' => $mockSchema1, 'prop2' => $mockSchema2]),
            additionalProperties: true,
            required: ['prop1'],
            propertyNames: StringSchema::create(pattern: '^[A-Za-z_][A-Za-z0-9_]*$'),
            minProperties: 1,
            maxProperties: 2,
        );
        self::assertJsonStringEqualsJsonString('{"type":"object","title":"some title","description":"some description","default":{"foo":"bar"},"examples":[{"bar":"baz"},{"foos":"bars"}],"readOnly":true,"writeOnly":false,"deprecated":true,"const":{"constant":"value"},"properties":{"prop1":{"type":"mock1"},"prop2":{"type":"mock2"}},"additionalProperties":true,"required":["prop1"],"propertyNames":{"type":"string","pattern":"^[A-Za-z_][A-Za-z0-9_]*$"},"minProperties":1,"maxProperties":2,"$comment":"some comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_wither(): void
    {
        $mockSchema1 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema1->expects($this->once())->method('jsonSerialize')->willReturn(['type' => 'mock1']);
        $mockSchema2 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema2->expects($this->once())->method('jsonSerialize')->willReturn(['type' => 'mock2']);
        $schema = ObjectSchema::create(
            title: 'some title',
            description: 'some description',
            default: ['foo' => 'bar'],
            examples: [['bar' => 'baz'], ['foos' => 'bars']],
            readOnly: true,
            writeOnly: false,
            deprecated: true,
            comment: 'some comment',
            const: ['constant' => 'value'],
            properties: ObjectProperties::create(...['prop1' => $mockSchema1, 'prop2' => $mockSchema2]),
            additionalProperties: true,
            required: ['prop1'],
            propertyNames: StringSchema::create(pattern: '^[A-Za-z_][A-Za-z0-9_]*$'),
            minProperties: 1,
            maxProperties: 2,
        );
        $schema = $schema->with(
            title: 'some changed title',
            description: 'some changed description',
            default: ['foo' => 'bar2'],
            examples: [['bar2' => 'baz'], ['foos' => 'bars changed']],
            readOnly: false,
            writeOnly: true,
            deprecated: false,
            comment: 'some changed comment',
            const: ['constant' => 'value changed'],
            properties: ObjectProperties::create(...['prop1' => $mockSchema2, 'propX' => $mockSchema1]),
            additionalProperties: false,
            required: ['propX'],
            propertyNames: StringSchema::create(pattern: '^.*$'),
            minProperties: 2,
            maxProperties: 3,
        );
        self::assertJsonStringEqualsJsonString('{"type":"object","title":"some changed title","description":"some changed description","default":{"foo":"bar2"},"examples":[{"bar2":"baz"},{"foos":"bars changed"}],"readOnly":false,"writeOnly":true,"deprecated":false,"const":{"constant":"value changed"},"properties":{"prop1":{"type":"mock2"},"propX":{"type":"mock1"}},"additionalProperties":false,"required":["propX"],"propertyNames":{"type":"string","pattern":"^.*$"},"minProperties":2,"maxProperties":3,"$comment":"some changed comment"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_noProperties(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create());
        self::assertJsonStringEqualsJsonString('{"type":"object","properties":{}}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_withPropertyDescriptions_describes_the_named_properties(): void
    {
        $schema = ObjectSchema::create(
            description: 'A book',
            properties: ObjectProperties::create(
                title: StringSchema::create(description: 'Some string', minLength: 1),
                pages: IntegerSchema::create(),
                isbn: StringSchema::create(),
            ),
            required: ['title'],
        );
        $described = $schema->withPropertyDescriptions(title: 'As printed on the cover', pages: 'Number of printed pages');
        self::assertJsonStringEqualsJsonString('{"type":"object","description":"A book","properties":{"title":{"type":"string","description":"As printed on the cover","minLength":1},"pages":{"type":"integer","description":"Number of printed pages"},"isbn":{"type":"string"}},"required":["title"]}', json_encode($described, JSON_THROW_ON_ERROR));
        // the original schema is left untouched
        self::assertJsonStringEqualsJsonString('{"type":"object","description":"A book","properties":{"title":{"type":"string","description":"Some string","minLength":1},"pages":{"type":"integer"},"isbn":{"type":"string"}},"required":["title"]}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_withPropertyDescriptions_describes_the_substantive_branch_of_a_nullable_property(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(subtitle: Nullable::wrap(StringSchema::create())));
        $described = $schema->withPropertyDescriptions(subtitle: 'Below the title, if any');
        self::assertJsonStringEqualsJsonString('{"type":"object","properties":{"subtitle":{"anyOf":[{"type":"string","description":"Below the title, if any"},{"type":"null"}]}}}', json_encode($described, JSON_THROW_ON_ERROR));
    }

    public function test_withPropertyDescriptions_describes_an_unconstrained_property(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(payload: AnySchema::create(title: 'Payload')));
        $described = $schema->withPropertyDescriptions(payload: 'Whatever the data source returned');
        self::assertJsonStringEqualsJsonString('{"type":"object","properties":{"payload":{"title":"Payload","description":"Whatever the data source returned"}}}', json_encode($described, JSON_THROW_ON_ERROR));
    }

    public function test_withPropertyDescriptions_rejects_a_genuine_union(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(id: AnyOfSchema::create(StringSchema::create(), IntegerSchema::create())));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791456496);
        $schema->withPropertyDescriptions(id: 'The identifier');
    }

    public function test_withPropertyDescriptions_rejects_a_reference(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(author: ReferenceSchema::create('#/$defs/author')));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791456496);
        $schema->withPropertyDescriptions(author: 'Who wrote it');
    }

    public function test_withPropertyDescriptions_rejects_an_undeclared_property(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(title: StringSchema::create()));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791456495);
        $schema->withPropertyDescriptions(titel: 'Typo');
    }

    public function test_withPropertyDescriptions_rejects_any_name_on_a_schema_without_properties(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791456495);
        ObjectSchema::create()->withPropertyDescriptions(title: 'The title');
    }

    public function test_withPropertyDescriptions_rejects_positional_arguments(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(title: StringSchema::create()));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791456494);
        $schema->withPropertyDescriptions('The title');
    }
}
