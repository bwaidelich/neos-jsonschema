<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Validation;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ArrayItems;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\Issues;
use Neos\JsonSchema\Validation\Normalization;
use Neos\JsonSchema\Validation\UnsupportedKeywordException;
use Neos\JsonSchema\Validation\ValidationResult;
use Neos\JsonSchema\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The two steps the {@see Validator} adds around the {@see \Neos\JsonSchema\Validation\Assertions} it delegates the
 * verdict to: normalizing the input first, and handing the projected value back afterwards.
 */
#[CoversClass(Validator::class)]
#[CoversClass(ValidationResult::class)]
#[CoversClass(Normalization::class)]
final class NormalizationTest extends TestCase
{
    private static function contact(): ObjectSchema
    {
        return ObjectSchema::create(
            properties: ObjectProperties::create(
                name: StringSchema::create(minLength: 1, maxLength: 200),
                age: IntegerSchema::create(minimum: 0, maximum: 150),
            ),
            additionalProperties: false,
            required: ['name', 'age'],
        );
    }

    private static function contactNames(): ArraySchema
    {
        return ArraySchema::create(items: StringSchema::create(minLength: 1), minItems: 1);
    }

    /**
     * @return list<string>
     */
    private function codes(ValidationResult $result): array
    {
        return array_map(static fn($issue): string => $issue->code, $result->issues->toArray());
    }

    public function testAValidValueIsHandedBackAsPrimitives(): void
    {
        $result = self::contact()->validate(['name' => 'John Doe', 'age' => 45]);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'John Doe', 'age' => 45], $result->value());
    }

    public function testAggregatesAllViolations(): void
    {
        $result = self::contact()->validate(['name' => '', 'age' => 200]);

        self::assertFalse($result->valid);
        self::assertCount(2, $result->issues);
    }

    public function testReportsMissingRequiredProperty(): void
    {
        $result = self::contact()->validate(['name' => 'John Doe']);

        self::assertFalse($result->valid);
        self::assertContains(IssueCode::Required->value, $this->codes($result));
    }

    public function testRejectsUnrecognizedProperties(): void
    {
        $result = self::contact()->validate(['name' => 'John Doe', 'age' => 1, 'extra' => true]);

        self::assertFalse($result->valid);
        self::assertSame([IssueCode::UnrecognizedKeys->value], $this->codes($result));
    }

    public function testIssuePathPointsAtOffendingProperty(): void
    {
        $result = self::contact()->validate(['name' => 'John Doe', 'age' => 200]);

        self::assertFalse($result->valid);
        self::assertSame(['age'], $result->issues->toArray()[0]->path);
    }

    public function testNormalizesIntegerFromNumericString(): void
    {
        $result = self::contact()->validate(['name' => 'John Doe', 'age' => '45'], Normalization::Scalars);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'John Doe', 'age' => 45], $result->value());
    }

    public function testNormalizesNumberFromNumericString(): void
    {
        $result = NumberSchema::create()->validate('4.5', Normalization::Scalars);

        self::assertTrue($result->valid);
        self::assertSame(4.5, $result->value());
    }

    public function testNormalizesBooleanFromItsQueryParameterSpellings(): void
    {
        $schema = BooleanSchema::create();

        self::assertTrue($schema->validate('true', Normalization::Scalars)->value());
        self::assertTrue($schema->validate('1', Normalization::Scalars)->value());
        self::assertTrue($schema->validate(1, Normalization::Scalars)->value());
        self::assertFalse($schema->validate('false', Normalization::Scalars)->value());
        self::assertFalse($schema->validate('0', Normalization::Scalars)->value());
        self::assertFalse($schema->validate(0, Normalization::Scalars)->value());
        self::assertFalse($schema->validate('yes', Normalization::Scalars)->valid);
        self::assertFalse($schema->validate('', Normalization::Scalars)->valid);
    }

    public function testLeavesAStringItCannotNormalizeForTheAssertionsToReject(): void
    {
        $result = IntegerSchema::create()->validate('45.5', Normalization::Scalars);

        self::assertFalse($result->valid);
        self::assertSame([IssueCode::InvalidType->value], $this->codes($result));
    }

    public function testNormalizationIsOffByDefault(): void
    {
        $result = self::contact()->validate(['name' => 'John Doe', 'age' => '45']);

        self::assertFalse($result->valid);
        self::assertSame([IssueCode::InvalidType->value], $this->codes($result));
    }

    public function testProjectsWithoutNormalizing(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(name: StringSchema::create()),
            additionalProperties: false,
        );
        $result = $schema->validate(['name' => 'Ada']);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'Ada'], $result->value());
    }

    /**
     * A schema that permits extra keys means them: dropping what it explicitly allowed would silently eat data a
     * caller relies on. They are handed back after the declared ones, which keep the schema's order.
     */
    public function testKeepsUndeclaredKeysWhenTheSchemaPermitsThem(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(name: StringSchema::create()));
        $result = $schema->validate(['extra' => true, 'name' => 'Ada']);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'Ada', 'extra' => true], $result->value());
    }

    public function testKeepsUndeclaredKeysWhenAdditionalPropertiesIsExplicitlyAllowed(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(name: StringSchema::create()),
            additionalProperties: true,
        );
        $result = $schema->validate(['name' => 'Ada', 'extra' => ['nested' => 1]]);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'Ada', 'extra' => ['nested' => 1]], $result->value());
    }

    public function testKeepsUndeclaredKeysOfANestedObject(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(
                contact: ObjectSchema::create(properties: ObjectProperties::create(name: StringSchema::create())),
            ),
        );
        $result = $schema->validate(['contact' => ['name' => 'Ada', 'nickname' => 'Countess']]);

        self::assertTrue($result->valid);
        self::assertSame(['contact' => ['name' => 'Ada', 'nickname' => 'Countess']], $result->value());
    }

    /**
     * `additionalProperties: false` makes an extra key an issue, so projection never has one to drop – but a key
     * declared by the schema and absent from the input must still not be invented.
     */
    public function testProjectionOmitsDeclaredPropertiesTheInputDoesNotCarry(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(name: StringSchema::create(), age: IntegerSchema::create()),
            additionalProperties: false,
        );
        $result = $schema->validate(['age' => 36]);

        self::assertTrue($result->valid);
        self::assertSame(['age' => 36], $result->value());
    }

    public function testProjectsPropertiesIntoSchemaOrder(): void
    {
        $result = self::contact()->validate(['age' => 45, 'name' => 'John Doe']);

        self::assertTrue($result->valid);
        self::assertSame(['name', 'age'], array_keys((array) $result->value()));
    }

    public function testProjectsObjectInputIntoAnArray(): void
    {
        $result = self::contact()->validate((object) ['name' => 'John Doe', 'age' => 45]);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'John Doe', 'age' => 45], $result->value());
    }

    public function testHandsBackAList(): void
    {
        $result = self::contactNames()->validate(['Ada', 'Grace']);

        self::assertTrue($result->valid);
        self::assertSame(['Ada', 'Grace'], $result->value());
    }

    public function testNormalizesListItems(): void
    {
        $result = ArraySchema::create(items: IntegerSchema::create())->validate(['1', '2'], Normalization::Scalars);

        self::assertTrue($result->valid);
        self::assertSame([1, 2], $result->value());
    }

    public function testNormalizesTupleItemsViaPrefixItems(): void
    {
        $schema = ArraySchema::create(
            items: false,
            prefixItems: ArrayItems::create(StringSchema::create(), IntegerSchema::create()),
        );
        $result = $schema->validate(['Ada', '36'], Normalization::Scalars);

        self::assertTrue($result->valid);
        self::assertSame(['Ada', 36], $result->value());
    }

    public function testLeavesUnconstrainedListItemsUntouched(): void
    {
        $result = ArraySchema::create()->validate(['1', true, null], Normalization::Scalars);

        self::assertTrue($result->valid);
        self::assertSame(['1', true, null], $result->value());
    }

    public function testEmptyListViolatesMinItems(): void
    {
        $result = self::contactNames()->validate([]);

        self::assertFalse($result->valid);
        self::assertSame([IssueCode::TooFewItems->value], $this->codes($result));
    }

    public function testRejectsListWhereObjectExpected(): void
    {
        $result = self::contact()->validate(['John', 45]);

        self::assertFalse($result->valid);
        self::assertSame([IssueCode::InvalidType->value], $this->codes($result));
    }

    public function testDescendsIntoNestedObjects(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(contact: self::contact()),
            additionalProperties: false,
            required: ['contact'],
        );
        $result = $schema->validate(['contact' => ['name' => 'Ada', 'age' => '36']], Normalization::Scalars);

        self::assertTrue($result->valid);
        self::assertSame(['contact' => ['name' => 'Ada', 'age' => 36]], $result->value());
    }

    public function testNormalizesNullableValuesThroughTheirSubstantiveBranch(): void
    {
        $schema = AnyOfSchema::create(IntegerSchema::create(), NullSchema::create());

        self::assertTrue($schema->validate(null)->valid);
        self::assertNull($schema->validate(null)->value());
        self::assertSame(45, $schema->validate('45', Normalization::Scalars)->value());
    }

    /**
     * Matching neither branch, the nullable idiom still reports *why the value is not the substantive one* – which
     * it can only do if that branch's normalization was the one applied.
     */
    public function testReportsTheSubstantiveBranchIssuesOfANullableValue(): void
    {
        $schema = AnyOfSchema::create(self::contact(), NullSchema::create());
        $result = $schema->validate(['name' => 'Ada', 'age' => '36', 'extra' => true], Normalization::Scalars);

        self::assertFalse($result->valid);
        self::assertSame([IssueCode::UnrecognizedKeys->value], $this->codes($result));
    }

    public function testProjectsThroughTheMatchingBranchOfAOneOf(): void
    {
        $schema = OneOfSchema::create(
            self::contact(),
            ObjectSchema::create(properties: ObjectProperties::create(nickname: StringSchema::create()), additionalProperties: false),
        );
        $result = $schema->validate(['nickname' => 'Ada']);

        self::assertTrue($result->valid);
        self::assertSame(['nickname' => 'Ada'], $result->value());
    }

    public function testLeavesAllOfUnprojected(): void
    {
        $schema = AllOfSchema::create(
            ObjectSchema::create(properties: ObjectProperties::create(name: StringSchema::create()), required: ['name']),
        );
        $result = $schema->validate(['name' => 'Ada', 'extra' => true]);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'Ada', 'extra' => true], $result->value());
    }

    public function testKeepsUndeclaredKeysOfAnUnconstrainedObject(): void
    {
        $result = ObjectSchema::create()->validate(['name' => 'Ada']);

        self::assertTrue($result->valid);
        self::assertSame(['name' => 'Ada'], $result->value());
    }

    public function testUnsupportedKeywordsStillThrow(): void
    {
        $this->expectException(UnsupportedKeywordException::class);
        // the result is never produced: the call throws before it can return one
        $unreached = ReferenceSchema::create('#/$defs/contact')->validate([]);
    }

    public function testTheValueOfAnInvalidResultIsNotRetrievable(): void
    {
        $result = IntegerSchema::create()->validate('nope');

        $this->expectException(\RuntimeException::class);
        $result->value();
    }

    public function testAnInvalidResultRequiresAtLeastOneIssue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ValidationResult::invalid(new Issues());
    }

    public function testTheSchemaSugarDelegatesToTheValidator(): void
    {
        $schema = self::contact();

        self::assertEquals(
            Validator::validate($schema, ['name' => 'Ada', 'age' => '36'], Normalization::Scalars),
            $schema->validate(['name' => 'Ada', 'age' => '36'], Normalization::Scalars),
        );
    }
}
