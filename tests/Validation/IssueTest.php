<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Validation;

use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\IssueCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Issue::class)]
final class IssueTest extends TestCase
{
    /**
     * @return iterable<string, array{path: list<string|int>, expected: string}>
     */
    public static function pathAsJsonPointer_dataProvider(): iterable
    {
        yield 'root' => ['path' => [], 'expected' => ''];
        yield 'property' => ['path' => ['title'], 'expected' => '/title'];
        yield 'nested property and array index' => ['path' => ['items', 0, 'name'], 'expected' => '/items/0/name'];
        yield 'empty segment' => ['path' => [''], 'expected' => '/'];
        yield 'segment containing a slash' => ['path' => ['a/b'], 'expected' => '/a~1b'];
        yield 'segment containing a tilde' => ['path' => ['m~n'], 'expected' => '/m~0n'];
        yield 'segment containing an escape sequence' => ['path' => ['~1'], 'expected' => '/~01'];
        yield 'segment containing both special characters' => ['path' => ['~/'], 'expected' => '/~0~1'];
    }

    /**
     * @param list<string|int> $path
     */
    #[DataProvider('pathAsJsonPointer_dataProvider')]
    public function test_pathAsJsonPointer(array $path, string $expected): void
    {
        $issue = Issue::create($path, IssueCode::TooShort, 'Some message');
        self::assertSame($expected, $issue->pathAsJsonPointer());
    }
}
