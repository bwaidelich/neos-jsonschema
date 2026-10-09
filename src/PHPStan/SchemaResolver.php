<?php

declare(strict_types=1);

namespace Neos\JsonSchema\PHPStan;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Hands the analysis the actual schema a {@see ProvidesSchema} class declares, by calling `schema()`.
 *
 * Evaluating the schema expression statically would have to re-implement PHP for anything beyond a literal
 * `XSchema::create(...)` (`with()` chains, {@see \Neos\JsonSchema\Nullable}, another class's schema), while the
 * contract of {@see ProvidesSchema} already asks for a pure, class-level value. The class has to be autoloadable
 * from the analysed project, as it is for any PHPStan run that relies on runtime reflection.
 *
 * @internal only to be used by the PHPStan rules of this package
 */
final class SchemaResolver
{
    /**
     * @var array<class-string, Schema|\Throwable|null>
     */
    private array $resolved = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    /**
     * Whether PHPStan knows the class as one that provides a schema of its own.
     */
    public function providesSchema(string $className): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }
        $classReflection = $this->reflectionProvider->getClass($className);
        return !$classReflection->isInterface() && $classReflection->implementsInterface(ProvidesSchema::class);
    }

    /**
     * The schema of the given class, the {@see \Throwable} its `schema()` threw, or `null` if it cannot be
     * determined (it does not provide a schema, or it is not autoloadable at analysis time).
     */
    public function resolve(string $className): Schema|\Throwable|null
    {
        if (!$this->providesSchema($className) || !class_exists($className) && !enum_exists($className)) {
            return null;
        }
        if (!array_key_exists($className, $this->resolved)) {
            $this->resolved[$className] = $this->call($className);
        }
        return $this->resolved[$className];
    }

    /**
     * @param class-string $className
     */
    private function call(string $className): Schema|\Throwable|null
    {
        if (!is_subclass_of($className, ProvidesSchema::class)) {
            return null;
        }
        try {
            return $className::schema();
        } catch (\Throwable $exception) {
            return $exception;
        }
    }
}
