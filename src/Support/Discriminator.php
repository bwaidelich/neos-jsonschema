<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Support;

final class Discriminator
{
    /**
     * @param array<string,string>|null $mapping
     */
    public function __construct(
        public string $propertyName,
        public array|null $mapping,
    ) {}

}
