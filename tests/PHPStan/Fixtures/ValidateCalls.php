<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;

final class ValidateCalls
{
    /**
     * @param non-empty-list<int> $ints
     * @param array{title: string, pages?: int} $partial
     */
    public function run(mixed $mixed, int|string $intOrString, string|null $nullableString, int $int, array $ints, array $partial, Schema $unknown): void
    {
        // the receiver's class alone pins down the JSON type
        $_ = StringSchema::create()->validate(123);
        $_ = StringSchema::create()->validate($mixed);
        $_ = StringSchema::create()->validate($intOrString);
        $_ = $unknown->validate(123);

        // constants are validated against the actual schema
        $_ = Handle::schema()->validate('ada');
        $_ = Handle::schema()->validate('Ada');

        // object shapes
        $_ = Product::schema()->validate(['title' => 'Dune', 'pages' => 412]);
        $_ = Product::schema()->validate(['title' => 'Dune']);
        $_ = Product::schema()->validate(['title' => 'Dune', 'pages' => 'many']);
        $_ = Product::schema()->validate(['title' => 'Dune', 'pages' => 412, 'isbn' => '978-0441013593']);
        $_ = Product::schema()->validate(['title' => 'Dune', 'pages' => 412, 'subtitle' => $nullableString]);
        $_ = Product::schema()->validate(['title' => 'Dune', 'pages' => 412, 'subtitle' => $int]);
        $_ = Product::schema()->validate($partial);
        $_ = Product::schema()->validate(['Dune', 412]);
        $_ = Product::schema()->validate(['title' => 'Dune', 'pages' => 412, 'tags' => $ints]);
        $_ = Product::schema()->validate(['title' => 'Dune', 'pages' => 412, 'tags' => ['sci-fi', 42]]);

        // an inline schema is only known by its static type, which `Schema` says nothing about
        $_ = Nullable::wrap(StringSchema::create())->validate($int);
    }
}
