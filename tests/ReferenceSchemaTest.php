<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\ReferenceSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceSchema::class)]
final class ReferenceSchemaTest extends TestCase
{
    public function test(): void
    {
        $schema = ReferenceSchema::create('some-ref');
        self::assertJsonStringEqualsJsonString('{"$ref": "some-ref"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }
}
