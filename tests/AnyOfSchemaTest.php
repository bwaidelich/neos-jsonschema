<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnyOfSchema::class)]
final class AnyOfSchemaTest extends TestCase
{
    public function test_empty(): void
    {
        $schema = AnyOfSchema::create();
        self::assertJsonStringEqualsJsonString('{"anyOf": []}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_with_two_items(): void
    {
        $mockSchema1 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema1->expects($this->atLeastOnce())->method('jsonSerialize')->willReturn(['type' => 'mock1']);
        $mockSchema2 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema2->expects($this->atLeastOnce())->method('jsonSerialize')->willReturn(['type' => 'mock2']);
        $schema = AnyOfSchema::create($mockSchema1, $mockSchema2);
        self::assertJsonStringEqualsJsonString('{"anyOf":[{"type":"mock1"},{"type":"mock2"}]}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_is_iterable(): void
    {
        /** @var Schema&Stub $mockSchema1 */
        $mockSchema1 = $this->getStubBuilder(Schema::class)->getStub();
        /** @var Schema&Stub $mockSchema2 */
        $mockSchema2 = $this->getStubBuilder(Schema::class)->getStub();
        $schema = AnyOfSchema::create($mockSchema1, $mockSchema2);
        self::assertSame([$mockSchema1, $mockSchema2], iterator_to_array($schema));
    }

}
