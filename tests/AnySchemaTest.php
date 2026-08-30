<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\AnySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\Support\ObjectProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnySchema::class)]
final class AnySchemaTest extends TestCase
{
    /**
     * `{}` rather than `[]`: an empty PHP array would encode as a list, and a list is not a schema.
     */
    public function testTheBareSchemaEncodesAsAnEmptyObject(): void
    {
        self::assertSame('{}', json_encode(AnySchema::create(), JSON_THROW_ON_ERROR));
    }

    public function testItEncodesAsAnEmptyObjectWhereItIsUsedToo(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(payload: AnySchema::create()));

        self::assertJsonStringEqualsJsonString(
            '{"type":"object","properties":{"payload":{}}}',
            json_encode($schema, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * A member nothing constrains can still be documented — which is the reason to reach for this rather than
     * leaving the member out of `properties` altogether.
     */
    public function testTheAnnotationKeywordsAreKept(): void
    {
        $schema = AnySchema::create(description: 'Whatever the plugin put here', deprecated: true, comment: 'not ours');

        self::assertJsonStringEqualsJsonString(
            '{"description":"Whatever the plugin put here","deprecated":true,"$comment":"not ours"}',
            json_encode($schema, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The whole point: nothing is ever invalid, and nothing is reshaped on the way through — an empty array
     * stays a list, because this schema does not claim to know that it was meant to be an object.
     */
    #[DataProvider('anyValueProvider')]
    public function testEveryValueIsValidAndComesBackUnchanged(mixed $value): void
    {
        $result = AnySchema::create()->validate($value);

        self::assertTrue($result->valid);
        self::assertSame($value, $result->value());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function anyValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['a'];
        yield 'integer' => [1];
        yield 'boolean' => [true];
        yield 'empty array' => [[]];
        yield 'list' => [[1, 2]];
        yield 'map' => [['a' => 1]];
        yield 'nested' => [['a' => ['b' => []]]];
    }
}
