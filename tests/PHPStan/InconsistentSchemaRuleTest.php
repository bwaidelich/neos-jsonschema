<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan;

use Neos\JsonSchema\PHPStan\InconsistentSchemaRule;
use Neos\JsonSchema\PHPStan\SchemaConsistency;
use Neos\JsonSchema\PHPStan\SchemaResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<InconsistentSchemaRule>
 */
#[CoversClass(InconsistentSchemaRule::class)]
#[CoversClass(SchemaConsistency::class)]
#[CoversClass(SchemaResolver::class)]
final class InconsistentSchemaRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new InconsistentSchemaRule(new SchemaResolver($this->createReflectionProvider()));
    }

    public function testAConsistentSchemaIsNotReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/Product.php', __DIR__ . '/Fixtures/Handle.php'], []);
    }

    public function testEveryDefectOfTheSchemaTreeIsReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/InconsistentSchema.php'], [
            ['Schema of Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\InconsistentSchema is inconsistent at "properties.choice": anyOf must contain at least one schema.', 17],
            ['Schema of Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\InconsistentSchema is inconsistent at "properties.code": pattern "(unclosed" is not a valid regular expression.', 17],
            ['Schema of Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\InconsistentSchema is inconsistent at "properties.count": no number lies between minimum (10) and maximum (1).', 17],
            ['Schema of Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\InconsistentSchema is inconsistent at "properties.name": default "x" does not conform to the schema: Value must be at least 5 character(s) long.', 17],
            ['Schema of Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\InconsistentSchema is inconsistent at "properties.name": minLength (5) is greater than maxLength (3).', 17],
            ['Schema of Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\InconsistentSchema is inconsistent at "properties.tags.items": examples[0] "c" does not conform to the schema: Value "c" is not one of the allowed values.', 17],
            ['Schema of Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\InconsistentSchema is inconsistent: required property "missing" is not declared in properties.', 17],
        ]);
    }

    public function testASchemaThatThrowsIsReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/ThrowingSchema.php'], [
            ['Neos\JsonSchema\Tests\PHPStan\Fixtures\ThrowingSchema::schema() cannot be resolved, it throws RuntimeException: not yet', 12],
        ]);
    }
}
