<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\NullSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullSchema::class)]
final class NullSchemaTest extends TestCase
{
    public function test(): void
    {
        $schema = NullSchema::create();
        self::assertJsonStringEqualsJsonString('{"type":"null"}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

}
