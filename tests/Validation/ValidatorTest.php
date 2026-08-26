<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Validation;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NotSchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ArrayItems;
use Neos\JsonSchema\Support\Discriminator;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\JsonSchema\Support\StringFormat;
use Neos\JsonSchema\Validation\IssueCode;
use Neos\JsonSchema\Validation\UnsupportedKeywordException;
use Neos\JsonSchema\Validation\ValidationResult;
use Neos\JsonSchema\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function codes(ValidationResult $result): array
    {
        return array_map(static fn($issue): string => $issue->code, $result->issues->toArray());
    }

    public function testValidStringPassesFormatPatternAndLength(): void
    {
        $schema = StringSchema::create(minLength: 3, maxLength: 5, pattern: '^[a-z]+$');
        self::assertTrue($schema->validate('abcd')->valid);
    }

    public function testStringDoesNotCoerceType(): void
    {
        // the Validator, unlike the Coercer, must NOT accept a numeric string as an integer
        $result = IntegerSchema::create()->validate('30');
        self::assertFalse($result->valid);
        self::assertSame([IssueCode::InvalidType->value], $this->codes($result));
    }

    public function testStringPatternViolation(): void
    {
        $result = StringSchema::create(pattern: '^[a-z]+$')->validate('ABC');
        self::assertSame([IssueCode::InvalidPattern->value], $this->codes($result));
    }

    public function testStringFormatViolation(): void
    {
        $result = StringSchema::create(format: StringFormat::email)->validate('not-an-email');
        self::assertSame([IssueCode::InvalidFormat->value], $this->codes($result));
    }

    public function testContentKeywordsAreIgnoredAsAnnotations(): void
    {
        $schema = StringSchema::create(contentMediaType: 'application/json', contentEncoding: 'base64');
        self::assertTrue($schema->validate('anything')->valid);
    }

    public function testIntegerBounds(): void
    {
        $schema = IntegerSchema::create(minimum: 1, maximum: 10);
        self::assertTrue($schema->validate(5)->valid);
        self::assertSame([IssueCode::TooSmall->value], $this->codes($schema->validate(0)));
        self::assertSame([IssueCode::TooBig->value], $this->codes($schema->validate(11)));
    }

    public function testObjectRequiredAndAdditionalProperties(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(name: StringSchema::create()),
            required: ['name'],
            additionalProperties: false,
        );
        self::assertTrue($schema->validate(['name' => 'Ada'])->valid);
        self::assertSame([IssueCode::Required->value], $this->codes($schema->validate([])));
        self::assertSame([IssueCode::UnrecognizedKeys->value], $this->codes($schema->validate(['name' => 'Ada', 'extra' => 1])));
    }

    public function testObjectPropertyNames(): void
    {
        $schema = ObjectSchema::create(propertyNames: StringSchema::create(pattern: '^[a-z]+$'));
        self::assertTrue($schema->validate(['abc' => 1])->valid);
        self::assertSame([IssueCode::InvalidPattern->value], $this->codes($schema->validate(['ABC' => 1])));
    }

    public function testArrayItemsAndConstraints(): void
    {
        $schema = ArraySchema::create(items: StringSchema::create(), minItems: 1, uniqueItems: true);
        self::assertTrue($schema->validate(['a', 'b'])->valid);
        self::assertSame([IssueCode::TooFewItems->value], $this->codes($schema->validate([])));
        self::assertSame([IssueCode::NotUnique->value], $this->codes($schema->validate(['a', 'a'])));
        self::assertSame([IssueCode::InvalidType->value], $this->codes($schema->validate([1])));
    }

    public function testArrayPrefixItemsTupleValidation(): void
    {
        $schema = ArraySchema::create(
            items: false,
            prefixItems: ArrayItems::create(StringSchema::create(), IntegerSchema::create()),
        );
        self::assertTrue($schema->validate(['a', 1])->valid);
        self::assertSame([IssueCode::InvalidType->value], $this->codes($schema->validate(['a', 'b'])));
        self::assertSame([IssueCode::TooManyItems->value], $this->codes($schema->validate(['a', 1, 'extra'])));
    }

    public function testArrayContains(): void
    {
        $schema = ArraySchema::create(items: IntegerSchema::create(), contains: IntegerSchema::create(minimum: 5));
        self::assertTrue($schema->validate([1, 2, 6])->valid);
        self::assertSame([IssueCode::ContainsMismatch->value], $this->codes($schema->validate([1, 2, 3])));
    }

    public function testAnyOf(): void
    {
        $schema = AnyOfSchema::create(StringSchema::create(), IntegerSchema::create());
        self::assertTrue($schema->validate('a')->valid);
        self::assertTrue($schema->validate(3)->valid);
        self::assertSame([IssueCode::InvalidUnion->value], $this->codes($schema->validate(true)));
    }

    /**
     * `anyOf: [<something>, {"type": "null"}]` is the canonical way to spell "nullable". When the value is not
     * null there is exactly one branch it was meant to match, so the precise issue from that branch is far more
     * useful than the generic "matches nothing" summary.
     */
    public function testAnyOfWithASingleNonNullBranchReportsThatBranchesIssues(): void
    {
        $schema = AnyOfSchema::create(StringSchema::create(minLength: 3), NullSchema::create());

        self::assertTrue($schema->validate(null)->valid);
        self::assertTrue($schema->validate('abc')->valid);
        self::assertSame([IssueCode::TooShort->value], $this->codes($schema->validate('a')));
        self::assertSame([IssueCode::InvalidType->value], $this->codes($schema->validate(42)));
    }

    public function testAnyOfWithSeveralNonNullBranchesKeepsTheUnionSummary(): void
    {
        $schema = AnyOfSchema::create(StringSchema::create(), IntegerSchema::create(), NullSchema::create());

        self::assertTrue($schema->validate(null)->valid);
        self::assertSame([IssueCode::InvalidUnion->value], $this->codes($schema->validate(true)));
    }

    public function testOneOfRequiresExactlyOne(): void
    {
        // both branches match the value 3 -> oneOf fails (not exactly one)
        $schema = OneOfSchema::create(IntegerSchema::create(), IntegerSchema::create());
        self::assertSame([IssueCode::InvalidUnion->value], $this->codes($schema->validate(3)));
        // exactly one branch matches
        $stringOrInt = OneOfSchema::create(StringSchema::create(), IntegerSchema::create());
        self::assertTrue($stringOrInt->validate('a')->valid);
    }

    /**
     * A `discriminator` exists so a consumer need not try every branch. Honouring it turns "matched 0 of 2" into
     * the actual reason the payload was rejected. Branches are correlated by the `const` on their own
     * discriminator property, so this stays pure JSON Schema — the `mapping` is never consulted.
     */
    public function testDiscriminatedOneOfReportsTheSelectedBranchesIssues(): void
    {
        $schema = $this->discriminatedShape();

        self::assertTrue($schema->validate(['type' => 'circle', 'radius' => 5])->valid);
        // the tag selects the circle branch, so the failure is *its* failure
        self::assertSame([IssueCode::TooBig->value], $this->codes($schema->validate(['type' => 'circle', 'radius' => 500])));
        self::assertSame([IssueCode::Required->value], $this->codes($schema->validate(['type' => 'rectangle'])));
    }

    public function testDiscriminatedOneOfRejectsAnUnknownOrMissingTag(): void
    {
        $schema = $this->discriminatedShape();

        $issues = $schema->validate(['type' => 'triangle'])->issues->toArray();
        self::assertSame(IssueCode::InvalidEnumValue->value, $issues[0]->code);
        self::assertSame('type', $issues[0]->pathAsString());
        self::assertSame('Discriminator "type" must be one of "circle", "rectangle"', $issues[0]->message);

        self::assertSame([IssueCode::InvalidEnumValue->value], $this->codes($schema->validate(['radius' => 5])));
    }

    /**
     * A discriminator whose branches do not pin the tag cannot be resolved by it, so the generic behaviour stands.
     */
    public function testADiscriminatorWithoutConstBranchesFallsBackToTryingEveryBranch(): void
    {
        $schema = OneOfSchema::create(
            ObjectSchema::create(properties: ObjectProperties::create(radius: IntegerSchema::create()), required: ['radius']),
            StringSchema::create(),
        )->withDiscriminator(new Discriminator('type', null));

        // no branch pins `type`, so the discriminator cannot select one and every branch is tried as usual
        self::assertTrue($schema->validate(['radius' => 5])->valid);
        self::assertSame([IssueCode::InvalidUnion->value], $this->codes($schema->validate(['nope' => true])));
    }

    private function discriminatedShape(): OneOfSchema
    {
        return OneOfSchema::create(
            ObjectSchema::create(
                properties: ObjectProperties::create(
                    type: StringSchema::create(const: 'circle'),
                    radius: IntegerSchema::create(maximum: 150),
                ),
                additionalProperties: false,
                required: ['type', 'radius'],
            ),
            ObjectSchema::create(
                properties: ObjectProperties::create(
                    type: StringSchema::create(const: 'rectangle'),
                    width: IntegerSchema::create(),
                ),
                additionalProperties: false,
                required: ['type', 'width'],
            ),
        )->withDiscriminator(new Discriminator('type', null));
    }

    public function testAllOf(): void
    {
        $schema = AllOfSchema::create(IntegerSchema::create(minimum: 1), IntegerSchema::create(maximum: 10));
        self::assertTrue($schema->validate(5)->valid);
        self::assertSame([IssueCode::TooBig->value], $this->codes($schema->validate(11)));
    }

    public function testNot(): void
    {
        $schema = NotSchema::create(StringSchema::create());
        self::assertTrue($schema->validate(3)->valid);
        self::assertSame([IssueCode::MustNotMatch->value], $this->codes($schema->validate('a')));
    }

    public function testFailLoudOnReference(): void
    {
        $this->expectException(UnsupportedKeywordException::class);
        ReferenceSchema::create('#/$defs/foo')->validate('x');
    }

    public function testFailLoudOnUnevaluatedItems(): void
    {
        $schema = ArraySchema::create(items: StringSchema::create(), unevaluatedItems: false);
        $this->expectException(UnsupportedKeywordException::class);
        $schema->validate(['a']);
    }
}
