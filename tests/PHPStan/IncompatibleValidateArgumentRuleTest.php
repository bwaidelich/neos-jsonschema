<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan;

use Neos\JsonSchema\PHPStan\IncompatibleValidateArgumentRule;
use Neos\JsonSchema\PHPStan\SchemaResolver;
use Neos\JsonSchema\PHPStan\ValueCompatibility;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<IncompatibleValidateArgumentRule>
 */
#[CoversClass(IncompatibleValidateArgumentRule::class)]
#[CoversClass(ValueCompatibility::class)]
#[CoversClass(SchemaResolver::class)]
final class IncompatibleValidateArgumentRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new IncompatibleValidateArgumentRule(new SchemaResolver($this->createReflectionProvider()));
    }

    public function testAValueOfTheWrongTypeForTheSchemaIsReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/NodeAggregateId.php'], [
            [
                'Value passed to Neos\JsonSchema\Tests\PHPStan\Fixtures\NodeAggregateId::schema()->validate() can never be valid: expected a string, got int.',
                15,
            ],
        ]);
    }

    public function testAMatchingValueIsNotReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/Handle.php'], []);
    }

    public function testOnlyValuesThatCanNeverBeValidAreReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/ValidateCalls.php'], [
            [
                'Value passed to Neos\\JsonSchema\\StringSchema->validate() can never be valid: 123 is invalid: Expected a string, got int.',
                20,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Handle::schema()->validate() can never be valid: \'Ada\' is invalid: Value does not match the pattern "^[a-z]+$".',
                27,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Product::schema()->validate() can never be valid: required property "pages" is missing.',
                31,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Product::schema()->validate() can never be valid: property "pages": \'many\' is invalid: Expected an integer, got string.',
                32,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Product::schema()->validate() can never be valid: property "isbn" is not declared and additional properties are not allowed.',
                33,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Product::schema()->validate() can never be valid: property "subtitle": matches none of the branches (expected a string, got int; expected null, got int).',
                35,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Product::schema()->validate() can never be valid: expected an object (associative array), got array{\'Dune\', 412}.',
                37,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Product::schema()->validate() can never be valid: property "tags": items: expected a string, got int.',
                38,
            ],
            [
                'Value passed to Neos\\JsonSchema\\Tests\\PHPStan\\Fixtures\\Product::schema()->validate() can never be valid: property "tags": item 1: 42 is invalid: Expected a string, got int.',
                39,
            ],
        ]);
    }
}
