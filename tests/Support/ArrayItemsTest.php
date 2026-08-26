<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Support;

use Neos\JsonSchema\Support\ArrayItems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayItems::class)]
final class ArrayItemsTest extends TestCase
{
    public function test_empty(): void
    {
        $arrayItems = ArrayItems::create();
        self::assertSame([], iterator_to_array($arrayItems));
    }



}
