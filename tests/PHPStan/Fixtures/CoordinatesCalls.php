<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan\Fixtures;

final class CoordinatesCalls
{
    public function run(int $int, string $string): void
    {
        $_ = Coordinates::schema()->validate(['language' => 'de']);
        $_ = Coordinates::schema()->validate(['language' => $string]);
        $_ = Coordinates::schema()->validate(['language' => $int]);
        $_ = Coordinates::schema()->validate(['language' => '']);
    }
}
