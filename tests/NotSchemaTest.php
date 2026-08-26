<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\NotSchema;
use Neos\JsonSchema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotSchema::class)]
final class NotSchemaTest extends TestCase
{
    public function test(): void
    {
        $mockSchema1 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema1->expects($this->once())->method('jsonSerialize')->willReturn(['type' => 'mock1']);
        $schema = NotSchema::create($mockSchema1);
        self::assertJsonStringEqualsJsonString('{"not":{"type":"mock1"}}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

}
