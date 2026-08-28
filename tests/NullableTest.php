<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Validation\Normalization;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Nullable::class)]
final class NullableTest extends TestCase
{
    public function testWrapsASchemaInTheCanonicalNullableIdiom(): void
    {
        $schema = Nullable::wrap(StringSchema::create(minLength: 1));

        self::assertJsonStringEqualsJsonString(
            '{"anyOf":[{"type":"string","minLength":1},{"type":"null"}]}',
            json_encode($schema, JSON_THROW_ON_ERROR),
        );
    }

    public function testTheWrappedSchemaAcceptsNullAndItsOwnShape(): void
    {
        $schema = Nullable::wrap(IntegerSchema::create(minimum: 1));

        self::assertTrue($schema->validate(null)->valid);
        self::assertNull($schema->validate(null)->value());
        self::assertSame(45, $schema->validate('45', Normalization::Scalars)->value());
        self::assertFalse($schema->validate(0)->valid);
        self::assertFalse($schema->validate('nope')->valid);
    }

    /**
     * Wrapping twice must not nest, so that a caller can hand any schema over without first asking whether it is
     * already nullable.
     */
    public function testASchemaThatAlreadyAcceptsNullIsHandedBackUntouched(): void
    {
        $nullable = Nullable::wrap(StringSchema::create());

        self::assertSame($nullable, Nullable::wrap($nullable));

        $null = NullSchema::create();
        self::assertSame($null, Nullable::wrap($null));
    }

    /**
     * `anyOf` is associative, so a null branch joins the existing ones rather than wrapping them.
     */
    public function testAnExistingAnyOfGainsANullBranchInPlace(): void
    {
        $schema = Nullable::wrap(AnyOfSchema::create(StringSchema::create(), IntegerSchema::create()));

        self::assertJsonStringEqualsJsonString(
            '{"anyOf":[{"type":"string"},{"type":"integer"},{"type":"null"}]}',
            json_encode($schema, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * `oneOf` is not: flattening it would make a value matching one branch *and* null a union of two, so the
     * exclusive choice is kept intact inside the nullable wrapper.
     */
    public function testAOneOfIsWrappedRatherThanFlattened(): void
    {
        $schema = Nullable::wrap(OneOfSchema::create(StringSchema::create(), IntegerSchema::create()));

        self::assertJsonStringEqualsJsonString(
            '{"anyOf":[{"oneOf":[{"type":"string"},{"type":"integer"}]},{"type":"null"}]}',
            json_encode($schema, JSON_THROW_ON_ERROR),
        );
        self::assertTrue($schema->validate(null)->valid);
        self::assertTrue($schema->validate('Ada')->valid);
    }
}
