<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Validation;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ArrayItems;
use Neos\JsonSchema\Support\ObjectProperties;
use Neos\JsonSchema\Validation\Projection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The question neither a PHP value nor a PHP class can answer about itself: which of JSON's two structures an
 * `array` was. Inbound is exercised through {@see ValidatorTest} as well, since it is validation's third step;
 * these pin the walk itself, and the three places the two directions part company.
 */
#[CoversClass(Projection::class)]
final class ProjectionTest extends TestCase
{
    private function freeFormMap(): ObjectSchema
    {
        return ObjectSchema::create(additionalProperties: true);
    }

    public function testOutboundReadsAnEmptyArrayUnderAnObjectSchemaAsAnObject(): void
    {
        self::assertSame('{}', json_encode(Projection::outbound($this->freeFormMap(), [])));
    }

    /**
     * The regression guard for the direction that must *not* change: an empty list is still a list.
     */
    public function testAnEmptyArrayUnderAnArraySchemaStaysAList(): void
    {
        $schema = ArraySchema::create(items: StringSchema::create());

        self::assertSame('[]', json_encode(Projection::outbound($schema, [])));
        self::assertSame('[]', json_encode(Projection::inbound($schema, [])));
    }

    /**
     * Inbound has no `stdClass` to hand a consumer that expects PHP arrays throughout, so it leaves `[]` alone.
     */
    public function testInboundLeavesAnEmptyArrayAlone(): void
    {
        self::assertSame([], Projection::inbound($this->freeFormMap(), []));
    }

    public function testAFilledMapKeepsItsMembersInEitherDirection(): void
    {
        $value = ['b' => 1, 'a' => 2];

        self::assertSame($value, Projection::outbound($this->freeFormMap(), $value));
        self::assertSame($value, Projection::inbound($this->freeFormMap(), $value));
    }

    /**
     * Only members the schema *names* are descended into: the values of a free-form map are described by nothing,
     * so an empty array in there is left as it is rather than guessed at.
     */
    public function testMembersOfAFreeFormMapAreNotShaped(): void
    {
        $value = ['a' => [], 'b' => ['x']];

        self::assertSame($value, Projection::outbound($this->freeFormMap(), $value));
    }

    public function testTheSchemaDecidesTheKeyOrder(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(second: IntegerSchema::create(), first: IntegerSchema::create()),
        );

        self::assertSame(['second' => 2, 'first' => 1], Projection::outbound($schema, ['first' => 1, 'second' => 2]));
        self::assertSame(['second' => 2, 'first' => 1], Projection::inbound($schema, ['first' => 1, 'second' => 2]));
    }

    /**
     * The one asymmetry: a key the schema does not publish is dropped on the way in, because the document never
     * promised to read it — and kept on the way out, because the producer's own code chose to emit it, which is a
     * disagreement to report rather than to hide.
     */
    public function testUndeclaredKeysAreDroppedInboundAndKeptOutbound(): void
    {
        $schema = ObjectSchema::create(
            properties: ObjectProperties::create(declared: StringSchema::create()),
            additionalProperties: false,
        );
        $value = ['extra' => 'x', 'declared' => 'y'];

        self::assertSame(['declared' => 'y'], Projection::inbound($schema, $value));
        self::assertSame(['declared' => 'y', 'extra' => 'x'], Projection::outbound($schema, $value));
    }

    /**
     * Where the schema permits them, undeclared keys follow the declared ones in both directions.
     */
    public function testPermittedUndeclaredKeysFollowTheDeclaredOnes(): void
    {
        $schema = ObjectSchema::create(properties: ObjectProperties::create(declared: StringSchema::create()));
        $value = ['extra' => 'x', 'declared' => 'y'];

        self::assertSame(['declared' => 'y', 'extra' => 'x'], Projection::inbound($schema, $value));
        self::assertSame(['declared' => 'y', 'extra' => 'x'], Projection::outbound($schema, $value));
    }

    /**
     * Inbound accepts the shape a JSON decoder produces; outbound leaves it for `json_encode` to write.
     */
    public function testOnlyInboundUnwrapsStdClass(): void
    {
        $value = (object)['a' => 1];

        self::assertSame(['a' => 1], Projection::inbound($this->freeFormMap(), $value));
        self::assertSame($value, Projection::outbound($this->freeFormMap(), $value));
    }

    /**
     * A union is shaped by the branch the value matches, which is what tells an empty object member from an empty
     * list member when both are possible.
     */
    public function testAUnionIsShapedByTheBranchTheValueMatches(): void
    {
        $schema = OneOfSchema::create(
            ObjectSchema::create(
                properties: ObjectProperties::create(map: $this->freeFormMap()),
                required: ['map'],
            ),
            ObjectSchema::create(
                properties: ObjectProperties::create(list: ArraySchema::create(items: StringSchema::create())),
                required: ['list'],
            ),
        );

        self::assertSame('{"map":{}}', json_encode(Projection::outbound($schema, ['map' => []])));
        self::assertSame('{"list":[]}', json_encode(Projection::outbound($schema, ['list' => []])));
    }

    public function testItemsOfAListAreShapedToo(): void
    {
        $schema = ArraySchema::create(items: $this->freeFormMap());

        self::assertSame('[{},{"a":1}]', json_encode(Projection::outbound($schema, [[], ['a' => 1]])));
    }

    public function testPrefixItemsDescribeTheirPositions(): void
    {
        $schema = ArraySchema::create(
            prefixItems: ArrayItems::create($this->freeFormMap()),
            items: ArraySchema::create(items: StringSchema::create()),
        );

        self::assertSame('[{},[]]', json_encode(Projection::outbound($schema, [[], []])));
    }

    /**
     * Schema types with no single structural reading are handed back untouched in both directions: guessing which
     * branch's shape to take is what loses data.
     */
    public function testASchemaWithNoStructuralReadingChangesNothing(): void
    {
        $schema = AllOfSchema::create($this->freeFormMap());

        self::assertSame([], Projection::outbound($schema, []));
        self::assertSame([], Projection::inbound($schema, []));
    }
}
