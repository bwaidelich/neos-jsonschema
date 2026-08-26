<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllOfSchema::class)]
final class AllOfSchemaTest extends TestCase
{
    public function test_empty(): void
    {
        $schema = AllOfSchema::create();
        self::assertJsonStringEqualsJsonString('{"allOf": []}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_with_two_items(): void
    {
        $mockSchema1 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema1->expects($this->once())->method('jsonSerialize')->willReturn(['type' => 'mock1']);
        $mockSchema2 = $this->getMockBuilder(Schema::class)->getMock();
        $mockSchema2->expects($this->once())->method('jsonSerialize')->willReturn(['type' => 'mock2']);
        $schema = AllOfSchema::create($mockSchema1, $mockSchema2);
        self::assertJsonStringEqualsJsonString('{"allOf":[{"type":"mock1"},{"type":"mock2"}]}', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_is_iterable(): void
    {
        /** @var Schema&Stub $mockSchema1 */
        $mockSchema1 = $this->getStubBuilder(Schema::class)->getStub();
        /** @var Schema&Stub $mockSchema2 */
        $mockSchema2 = $this->getStubBuilder(Schema::class)->getStub();
        $schema = AllOfSchema::create($mockSchema1, $mockSchema2);
        self::assertSame([$mockSchema1, $mockSchema2], iterator_to_array($schema));
    }

}
