<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\PHPStan;

use Neos\JsonSchema\PHPStan\NotMemoizedSchemaRule;
use Neos\JsonSchema\PHPStan\SchemaResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<NotMemoizedSchemaRule>
 */
#[CoversClass(NotMemoizedSchemaRule::class)]
final class NotMemoizedSchemaRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NotMemoizedSchemaRule(new SchemaResolver($this->createReflectionProvider()));
    }

    public function testAStaticVariableThatIsNeverAssignedIsReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/NodeAggregateId.php'], [
            ['Static variable $schema in Neos\JsonSchema\Tests\PHPStan\Fixtures\NodeAggregateId::schema() is never assigned, so the schema is rebuilt on every call. Did you mean "return $schema ??= …"?', 21],
        ]);
    }

    public function testAMemoizedSchemaIsNotReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/Handle.php', __DIR__ . '/Fixtures/Product.php'], []);
    }
}
