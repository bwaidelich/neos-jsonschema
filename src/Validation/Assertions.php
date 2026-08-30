<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\AnySchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NotSchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\ReferenceSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;

/**
 * The *assertion* keywords of JSON Schema: "does this value conform to this schema?", answered as the list of every
 * way in which it does not.
 *
 * A reflection-free visitor over the concrete {@see Schema} types that reads their public constraint properties and
 * aggregates every violation as an {@see Issue}. It judges the value exactly as given – normalizing it first is the
 * {@see Validator}'s job, one layer up, and so is turning these issues into a {@see ValidationResult}.
 *
 * Strictness (see the neos/schematic ADR 0005): *annotation* keywords that never constrain validity are ignored;
 * an *assertion* keyword this class does not implement raises an {@see UnsupportedKeywordException} rather than
 * silently reporting a false "valid".
 *
 * @internal to be invoked via {@see Validator}
 */
final class Assertions
{
    /**
     * Every way in which the value fails to conform, empty when it conforms. Recurses into itself for the nodes of
     * a composite schema, which is why it carries the path the value sits at.
     *
     * @param list<string|int> $path the path the value sits at, for the issues this reports
     * @return list<Issue>
     */
    public static function check(Schema $schema, mixed $value, array $path = []): array
    {
        return match ($schema::class) {
            // the empty schema constrains nothing, so there is nothing it can fail
            AnySchema::class => [],
            StringSchema::class => self::validateString($schema, $value, $path),
            IntegerSchema::class => self::validateInteger($schema, $value, $path),
            NumberSchema::class => self::validateNumber($schema, $value, $path),
            BooleanSchema::class => self::validateBoolean($schema, $value, $path),
            NullSchema::class => self::validateNull($value, $path),
            ObjectSchema::class => self::validateObject($schema, $value, $path),
            ArraySchema::class => self::validateArray($schema, $value, $path),
            AllOfSchema::class => self::validateAllOf($schema, $value, $path),
            AnyOfSchema::class => self::validateAnyOf($schema, $value, $path),
            OneOfSchema::class => self::validateOneOf($schema, $value, $path),
            NotSchema::class => self::validateNot($schema, $value, $path),
            ReferenceSchema::class => throw new UnsupportedKeywordException(sprintf('Validation of "$ref" ("%s") is not supported', $schema->ref)),
        };
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateString(StringSchema $schema, mixed $value, array $path): array
    {
        // contentMediaType / contentEncoding are annotations by default (Draft 2019-09+), not assertions: ignored.
        if (!is_string($value)) {
            return [Issue::create($path, IssueCode::InvalidType, sprintf('Expected a string, got %s', get_debug_type($value)))];
        }
        if ($schema->enum !== null && !in_array($value, $schema->enum, true)) {
            return [Issue::create($path, IssueCode::InvalidEnumValue, sprintf('Value "%s" is not one of the allowed values', $value))];
        }
        if ($schema->const !== null && $value !== $schema->const) {
            return [Issue::create($path, IssueCode::InvalidConst, sprintf('Value must be "%s"', $schema->const))];
        }
        $length = mb_strlen($value);
        if ($schema->minLength !== null && $length < $schema->minLength) {
            return [Issue::create($path, IssueCode::TooShort, sprintf('Value must be at least %d character(s) long', $schema->minLength))];
        }
        if ($schema->maxLength !== null && $length > $schema->maxLength) {
            return [Issue::create($path, IssueCode::TooLong, sprintf('Value must be at most %d character(s) long', $schema->maxLength))];
        }
        if ($schema->pattern !== null && self::matchesPattern($schema->pattern, $value) === false) {
            return [Issue::create($path, IssueCode::InvalidPattern, sprintf('Value does not match the pattern "%s"', $schema->pattern))];
        }
        if ($schema->format !== null && !StringFormatValidator::matches($schema->format, $value)) {
            return [Issue::create($path, IssueCode::InvalidFormat, sprintf('Value is not a valid "%s"', $schema->format->value))];
        }
        return [];
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateInteger(IntegerSchema $schema, mixed $value, array $path): array
    {
        if (!is_int($value)) {
            return [Issue::create($path, IssueCode::InvalidType, sprintf('Expected an integer, got %s', get_debug_type($value)))];
        }
        if ($schema->enum !== null && !in_array($value, $schema->enum, true)) {
            return [Issue::create($path, IssueCode::InvalidEnumValue, sprintf('Value %d is not one of the allowed values', $value))];
        }
        if ($schema->const !== null && $value !== $schema->const) {
            return [Issue::create($path, IssueCode::InvalidConst, sprintf('Value must be %d', $schema->const))];
        }
        if ($schema->multipleOf !== null && $value % $schema->multipleOf !== 0) {
            return [Issue::create($path, IssueCode::NotMultipleOf, sprintf('Value must be a multiple of %d', $schema->multipleOf))];
        }
        return self::checkNumericBounds($value, $schema->minimum, $schema->exclusiveMinimum === true, $schema->maximum, $schema->exclusiveMaximum === true, $path);
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateNumber(NumberSchema $schema, mixed $value, array $path): array
    {
        if (!is_int($value) && !is_float($value)) {
            return [Issue::create($path, IssueCode::InvalidType, sprintf('Expected a number, got %s', get_debug_type($value)))];
        }
        if ($schema->enum !== null && !in_array($value, $schema->enum, false)) {
            return [Issue::create($path, IssueCode::InvalidEnumValue, sprintf('Value %s is not one of the allowed values', $value))];
        }
        if ($schema->const !== null && $value != $schema->const) {
            return [Issue::create($path, IssueCode::InvalidConst, sprintf('Value must be %s', $schema->const))];
        }
        if ($schema->multipleOf !== null && fmod((float) $value, (float) $schema->multipleOf) !== 0.0) {
            return [Issue::create($path, IssueCode::NotMultipleOf, sprintf('Value must be a multiple of %s', $schema->multipleOf))];
        }
        return self::checkNumericBounds($value, $schema->minimum, $schema->exclusiveMinimum === true, $schema->maximum, $schema->exclusiveMaximum === true, $path);
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateBoolean(BooleanSchema $schema, mixed $value, array $path): array
    {
        if (!is_bool($value)) {
            return [Issue::create($path, IssueCode::InvalidType, sprintf('Expected a boolean, got %s', get_debug_type($value)))];
        }
        if ($schema->const !== null && $value !== $schema->const) {
            return [Issue::create($path, IssueCode::InvalidConst, sprintf('Value must be %s', $schema->const ? 'true' : 'false'))];
        }
        return [];
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateNull(mixed $value, array $path): array
    {
        if ($value !== null) {
            return [Issue::create($path, IssueCode::InvalidType, sprintf('Expected null, got %s', get_debug_type($value)))];
        }
        return [];
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateObject(ObjectSchema $schema, mixed $value, array $path): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            return [Issue::create($path, IssueCode::InvalidType, sprintf('Expected an object, got %s', get_debug_type($value)))];
        }
        /** @var array<string, mixed> $value */
        $issues = [];
        $properties = [];
        if ($schema->properties !== null) {
            foreach ($schema->properties as $name => $propertySchema) {
                $properties[$name] = $propertySchema;
            }
        }
        $required = $schema->required ?? [];
        foreach ($properties as $name => $propertySchema) {
            if (array_key_exists($name, $value)) {
                $issues = [...$issues, ...self::check($propertySchema, $value[$name], [...$path, $name])];
            } elseif (in_array($name, $required, true)) {
                $issues[] = Issue::create([...$path, $name], IssueCode::Required, sprintf('Missing required property "%s"', $name));
            }
        }
        $extra = array_values(array_diff(array_keys($value), array_keys($properties)));
        if ($extra !== [] && $schema->additionalProperties === false) {
            $issues[] = Issue::create($path, IssueCode::UnrecognizedKeys, sprintf('Unrecognized propert%s: %s', count($extra) === 1 ? 'y' : 'ies', implode(', ', $extra)));
        }
        if ($schema->propertyNames !== null) {
            foreach (array_keys($value) as $key) {
                foreach (self::validateString($schema->propertyNames, (string) $key, [...$path, $key]) as $issue) {
                    $issues[] = Issue::create([...$path, $key], $issue->code, sprintf('Property name "%s" is invalid: %s', $key, $issue->message));
                }
            }
        }
        $propertyCount = count($value);
        if ($schema->minProperties !== null && $propertyCount < $schema->minProperties) {
            $issues[] = Issue::create($path, IssueCode::TooSmall, sprintf('Object must have at least %d propert%s', $schema->minProperties, $schema->minProperties === 1 ? 'y' : 'ies'));
        }
        if ($schema->maxProperties !== null && $propertyCount > $schema->maxProperties) {
            $issues[] = Issue::create($path, IssueCode::TooBig, sprintf('Object must have at most %d propert%s', $schema->maxProperties, $schema->maxProperties === 1 ? 'y' : 'ies'));
        }
        if ($schema->const !== null && $issues === [] && !self::deepEquals($schema->const, $value)) {
            $issues[] = Issue::create($path, IssueCode::InvalidConst, 'Value does not match the expected constant');
        }
        return $issues;
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateArray(ArraySchema $schema, mixed $value, array $path): array
    {
        if ($schema->unevaluatedItems !== null) {
            throw new UnsupportedKeywordException('Validation of "unevaluatedItems" is not supported');
        }
        if (!is_array($value) || ($value !== [] && !array_is_list($value))) {
            return [Issue::create($path, IssueCode::InvalidType, sprintf('Expected a list, got %s', get_debug_type($value)))];
        }
        /** @var list<mixed> $value */
        $issues = [];
        // Reads `prefixItems` and `items` directly rather than through ArraySchema::itemSchema(): this loop also
        // needs the prefix *length*, which is the limit an extra element exceeds.
        $prefix = $schema->prefixItems !== null ? iterator_to_array($schema->prefixItems, false) : [];
        foreach ($value as $index => $item) {
            if ($index < count($prefix)) {
                $issues = [...$issues, ...self::check($prefix[$index], $item, [...$path, $index])];
                continue;
            }
            if ($schema->items instanceof Schema) {
                $issues = [...$issues, ...self::check($schema->items, $item, [...$path, $index])];
            } elseif ($schema->items === false) {
                $issues[] = Issue::create([...$path, $index], IssueCode::TooManyItems, sprintf('List must not contain more than %d item(s)', count($prefix)));
            }
        }
        $count = count($value);
        if ($schema->minItems !== null && $count < $schema->minItems) {
            $issues[] = Issue::create($path, IssueCode::TooFewItems, sprintf('List must contain at least %d item(s)', $schema->minItems));
        }
        if ($schema->maxItems !== null && $count > $schema->maxItems) {
            $issues[] = Issue::create($path, IssueCode::TooManyItems, sprintf('List must contain at most %d item(s)', $schema->maxItems));
        }
        if ($schema->contains !== null) {
            $matches = 0;
            foreach ($value as $item) {
                if (self::check($schema->contains, $item, $path) === []) {
                    $matches++;
                }
            }
            $min = $schema->minContains ?? 1;
            $max = $schema->maxContains;
            if ($matches < $min) {
                $issues[] = Issue::create($path, IssueCode::ContainsMismatch, sprintf('List must contain at least %d item(s) matching the "contains" schema', $min));
            } elseif ($max !== null && $matches > $max) {
                $issues[] = Issue::create($path, IssueCode::ContainsMismatch, sprintf('List must contain at most %d item(s) matching the "contains" schema', $max));
            }
        }
        if ($schema->uniqueItems === true && $issues === [] && !self::allUnique($value)) {
            $issues[] = Issue::create($path, IssueCode::NotUnique, 'List items must be unique');
        }
        if ($schema->const !== null && $issues === [] && !self::deepEquals($schema->const, $value)) {
            $issues[] = Issue::create($path, IssueCode::InvalidConst, 'Value does not match the expected constant');
        }
        return $issues;
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateAllOf(AllOfSchema $schema, mixed $value, array $path): array
    {
        $issues = [];
        foreach ($schema as $branch) {
            $issues = [...$issues, ...self::check($branch, $value, $path)];
        }
        return $issues;
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateAnyOf(AnyOfSchema $schema, mixed $value, array $path): array
    {
        $substantiveBranches = [];
        foreach ($schema as $branch) {
            if (self::check($branch, $value, $path) === []) {
                return [];
            }
            if (!$branch instanceof NullSchema) {
                $substantiveBranches[] = $branch;
            }
        }
        if (count($substantiveBranches) === 1) {
            // The canonical "nullable" idiom: `anyOf: [<something>, {"type": "null"}]`. The value is not null (that
            // branch was tried), so there is exactly one thing it was meant to be — report *why it isn't that*
            // instead of the useless "matches nothing" summary. Genuine multi-branch unions keep the summary,
            // because there no single branch's issues are the answer.
            return self::check($substantiveBranches[0], $value, $path);
        }
        return [Issue::create($path, IssueCode::InvalidUnion, 'Value does not match any of the allowed schemas')];
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateOneOf(OneOfSchema $schema, mixed $value, array $path): array
    {
        if ($schema->discriminator !== null && is_array($value)) {
            return self::validateDiscriminatedOneOf($schema, $schema->discriminator->propertyName, $value, $path);
        }
        $matches = 0;
        foreach ($schema as $branch) {
            if (self::check($branch, $value, $path) === []) {
                $matches++;
            }
        }
        if ($matches === 1) {
            return [];
        }
        return [Issue::create($path, IssueCode::InvalidUnion, sprintf('Value must match exactly one of the allowed schemas, matched %d', $matches))];
    }

    /**
     * A `discriminator` exists precisely so a consumer does not have to try every branch: the named property picks
     * exactly one. Honouring it turns "matched 0 of 3" into the real reason the payload was rejected.
     *
     * Branches are correlated by the `const` on their own discriminator property, which keeps this pure JSON
     * Schema — the `mapping` is not consulted, since its values are references this package knows nothing about.
     *
     * @param string $propertyName the discriminator property, resolved by the caller
     * @param array<mixed> $value
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateDiscriminatedOneOf(OneOfSchema $schema, string $propertyName, array $value, array $path): array
    {
        $allowed = [];
        foreach ($schema as $branch) {
            $const = $branch instanceof ObjectSchema ? $branch->properties?->get($propertyName) : null;
            if (!$const instanceof StringSchema || $const->const === null) {
                // a branch that does not pin the discriminator cannot be selected by it — fall back to trying all
                return self::validateUndiscriminated($schema, $value, $path);
            }
            $allowed[$const->const] = $branch;
        }
        $discriminatorValue = $value[$propertyName] ?? null;
        if (!is_string($discriminatorValue) || !isset($allowed[$discriminatorValue])) {
            return [Issue::create(
                [...$path, $propertyName],
                IssueCode::InvalidEnumValue,
                sprintf('Discriminator "%s" must be one of "%s"', $propertyName, implode('", "', array_keys($allowed))),
            )];
        }
        return self::check($allowed[$discriminatorValue], $value, $path);
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateUndiscriminated(OneOfSchema $schema, mixed $value, array $path): array
    {
        $matches = 0;
        foreach ($schema as $branch) {
            if (self::check($branch, $value, $path) === []) {
                $matches++;
            }
        }
        if ($matches === 1) {
            return [];
        }
        return [Issue::create($path, IssueCode::InvalidUnion, sprintf('Value must match exactly one of the allowed schemas, matched %d', $matches))];
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function validateNot(NotSchema $schema, mixed $value, array $path): array
    {
        if (self::check($schema->schema, $value, $path) === []) {
            return [Issue::create($path, IssueCode::MustNotMatch, 'Value must not match the given schema')];
        }
        return [];
    }

    /**
     * @param list<string|int> $path
     * @return list<Issue>
     */
    private static function checkNumericBounds(int|float $value, int|float|null $minimum, bool $exclusiveMin, int|float|null $maximum, bool $exclusiveMax, array $path): array
    {
        if ($minimum !== null && ($exclusiveMin ? $value <= $minimum : $value < $minimum)) {
            return [Issue::create($path, IssueCode::TooSmall, sprintf('Value must be %s %s', $exclusiveMin ? 'greater than' : 'at least', $minimum))];
        }
        if ($maximum !== null && ($exclusiveMax ? $value >= $maximum : $value > $maximum)) {
            return [Issue::create($path, IssueCode::TooBig, sprintf('Value must be %s %s', $exclusiveMax ? 'less than' : 'at most', $maximum))];
        }
        return [];
    }

    /**
     * A JSON Schema `pattern` is an ECMA-262 regular expression *without* delimiters, so PCRE delimiters have to be
     * added around it. They cannot be escaped into the pattern (`str_replace('~', '\~', ...)` corrupts a pattern
     * that already escapes the delimiter itself, turning `\~` into `\\~`), so instead a delimiter the pattern does
     * not contain is picked. The control characters come first because an ECMA-262 pattern is written as source
     * text and practically never contains one raw.
     *
     * @throws UnsupportedKeywordException if the pattern cannot be delimited, or is not a valid regular expression
     */
    private static function matchesPattern(string $pattern, string $value): bool
    {
        $delimiter = null;
        foreach (["\x01", "\x02", '~', '#', '%', '!'] as $candidate) {
            if (!str_contains($pattern, $candidate)) {
                $delimiter = $candidate;
                break;
            }
        }
        if ($delimiter === null) {
            throw new UnsupportedKeywordException(sprintf('The pattern "%s" contains every candidate delimiter and cannot be enforced', $pattern));
        }
        $matches = @preg_match($delimiter . $pattern . $delimiter . 'u', $value);
        if ($matches === false) {
            throw new UnsupportedKeywordException(sprintf('The pattern "%s" is not a valid regular expression and cannot be enforced', $pattern));
        }
        return $matches === 1;
    }

    /**
     * @param list<mixed> $items
     */
    private static function allUnique(array $items): bool
    {
        $seen = [];
        foreach ($items as $item) {
            $key = serialize($item);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
        }
        return true;
    }

    private static function deepEquals(mixed $expected, mixed $actual): bool
    {
        return serialize($expected) === serialize($actual);
    }
}
