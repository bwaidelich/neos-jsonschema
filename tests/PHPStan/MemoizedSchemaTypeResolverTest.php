<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan;

use Neos\JsonSchema\PHPStan\MemoizedSchemaTypeResolver;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(MemoizedSchemaTypeResolver::class)]
final class MemoizedSchemaTypeResolverTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function typeAssertions(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/Fixtures/MemoizedSchemaTypes.php');
    }

    #[DataProvider('typeAssertions')]
    public function testTheMemoizationIdiomIsTypedAsTheSchemaItBuilds(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }
}
