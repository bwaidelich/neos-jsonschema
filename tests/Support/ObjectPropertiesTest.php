<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Support;

use Neos\JsonSchema\Support\ObjectProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectProperties::class)]
final class ObjectPropertiesTest extends TestCase
{
    public function test_empty(): void
    {
        $objectProperties = ObjectProperties::create();
        self::assertSame([], iterator_to_array($objectProperties));
    }

}
