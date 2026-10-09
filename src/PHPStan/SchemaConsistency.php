<?php

declare(strict_types=1);

namespace Neos\JsonSchema\PHPStan;

use Neos\JsonSchema\AllOfSchema;
use Neos\JsonSchema\AnyOfSchema;
use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\IntegerSchema;
use Neos\JsonSchema\NotSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Validation\Issue;
use Neos\JsonSchema\Validation\UnsupportedKeywordException;

/**
 * Finds the defects of a schema that make it contradict itself: bounds no value can satisfy, a pattern that does
 * not compile, an annotated value (`default`, `const`, `examples`, `enum`) the schema itself rejects, a `required`
 * property it does not declare, or an empty combinator. Walks the whole tree, so a nested schema is covered too.
 *
 * @internal only to be used by the PHPStan rules of this package
 */
final class SchemaConsistency
{
    private function __construct() {}

    /**
     * @return list<array{kind: string, path: string, message: string}>
     */
    public static function defectsOf(Schema $schema): array
    {
        return self::check($schema, []);
    }

    /**
     * @param list<string> $path
     * @return list<array{kind: string, path: string, message: string}>
     */
    private static function check(Schema $schema, array $path): array
    {
        $defects = [];
        $add = static function (string $kind, string $message) use (&$defects, $path): void {
            $defects[] = ['kind' => $kind, 'path' => implode('.', $path), 'message' => $message];
        };
        $children = [];

        if ($schema instanceof StringSchema) {
            self::checkBounds($add, 'minLength', $schema->minLength, 'maxLength', $schema->maxLength);
            if ($schema->pattern !== null && self::patternIsInvalid($schema->pattern)) {
                $add('pattern', sprintf('pattern "%s" is not a valid regular expression', $schema->pattern));
            }
        } elseif ($schema instanceof IntegerSchema || $schema instanceof NumberSchema) {
            if ($schema->minimum !== null && $schema->maximum !== null) {
                $exclusive = $schema->exclusiveMinimum === true || $schema->exclusiveMaximum === true;
                if ($schema->minimum > $schema->maximum || ($exclusive && $schema->minimum == $schema->maximum)) {
                    $add('bounds', sprintf('no number lies between minimum (%s) and maximum (%s)', $schema->minimum, $schema->maximum));
                }
            }
            if ($schema->multipleOf !== null && $schema->multipleOf <= 0) {
                $add('bounds', sprintf('multipleOf (%s) must be greater than 0', $schema->multipleOf));
            }
        } elseif ($schema instanceof ArraySchema) {
            self::checkBounds($add, 'minItems', $schema->minItems, 'maxItems', $schema->maxItems);
            self::checkBounds($add, 'minContains', $schema->minContains, 'maxContains', $schema->maxContains);
            if ($schema->items instanceof Schema) {
                $children['items'] = $schema->items;
            }
            foreach ($schema->prefixItems !== null ? iterator_to_array($schema->prefixItems, false) : [] as $index => $item) {
                $children['prefixItems.' . $index] = $item;
            }
            if ($schema->unevaluatedItems instanceof Schema) {
                $children['unevaluatedItems'] = $schema->unevaluatedItems;
            }
            if ($schema->contains !== null) {
                $children['contains'] = $schema->contains;
            }
        } elseif ($schema instanceof ObjectSchema) {
            self::checkBounds($add, 'minProperties', $schema->minProperties, 'maxProperties', $schema->maxProperties);
            $declared = $schema->properties?->names() ?? [];
            foreach ($schema->required ?? [] as $name) {
                if (!in_array($name, $declared, true)) {
                    $add('required', sprintf('required property "%s" is not declared in properties', $name));
                }
            }
            foreach ($schema->properties ?? [] as $name => $property) {
                $children['properties.' . $name] = $property;
            }
            if ($schema->propertyNames !== null) {
                $children['propertyNames'] = $schema->propertyNames;
            }
        } elseif ($schema instanceof AllOfSchema || $schema instanceof AnyOfSchema || $schema instanceof OneOfSchema) {
            $keyword = match (true) {
                $schema instanceof AllOfSchema => 'allOf',
                $schema instanceof AnyOfSchema => 'anyOf',
                default => 'oneOf',
            };
            $branches = iterator_to_array($schema, false);
            if ($branches === []) {
                $add('combinator', sprintf('%s must contain at least one schema', $keyword));
            }
            foreach ($branches as $index => $branch) {
                $children[$keyword . '.' . $index] = $branch;
            }
        } elseif ($schema instanceof NotSchema) {
            $children['not'] = $schema->schema;
        }

        self::checkAnnotatedValues($add, $schema);

        foreach ($children as $segment => $child) {
            $defects = [...$defects, ...self::check($child, [...$path, (string) $segment])];
        }
        return $defects;
    }

    /**
     * @param \Closure(string, string): void $add
     */
    private static function checkBounds(\Closure $add, string $minName, int|null $min, string $maxName, int|null $max): void
    {
        foreach ([$minName => $min, $maxName => $max] as $name => $value) {
            if ($value !== null && $value < 0) {
                $add('bounds', sprintf('%s (%d) must not be negative', $name, $value));
            }
        }
        if ($min !== null && $max !== null && $min > $max) {
            $add('bounds', sprintf('%s (%d) is greater than %s (%d)', $minName, $min, $maxName, $max));
        }
    }

    /**
     * Every value the schema itself states as conforming has to conform to it.
     *
     * @param \Closure(string, string): void $add
     */
    private static function checkAnnotatedValues(\Closure $add, Schema $schema): void
    {
        $candidates = [];
        foreach (['default', 'const'] as $keyword) {
            if (property_exists($schema, $keyword) && $schema->{$keyword} !== null) {
                $candidates[] = [$keyword, $keyword, $schema->{$keyword}];
            }
        }
        foreach (['examples', 'enum'] as $keyword) {
            if (property_exists($schema, $keyword) && is_array($schema->{$keyword})) {
                foreach ($schema->{$keyword} as $index => $value) {
                    $candidates[] = [$keyword, sprintf('%s[%s]', $keyword, $index), $value];
                }
            }
        }
        foreach ($candidates as [$kind, $label, $value]) {
            try {
                $result = $schema->validate($value);
            } catch (UnsupportedKeywordException) {
                // the schema cannot be enforced (e.g. a "$ref" or an invalid pattern, reported on its own)
                continue;
            }
            if (!$result->valid) {
                $add($kind, sprintf('%s %s does not conform to the schema: %s', $label, json_encode($value) ?: var_export($value, true), implode(', ', array_map(
                    static fn(Issue $issue): string => $issue->path === [] ? $issue->message : sprintf('%s: %s', $issue->pathAsString(), $issue->message),
                    $result->issues->toArray(),
                ))));
            }
        }
    }

    private static function patternIsInvalid(string $pattern): bool
    {
        // the validator compiles the pattern the way it enforces it, and refuses one it cannot compile
        try {
            $_ = StringSchema::create(pattern: $pattern)->validate('');
        } catch (UnsupportedKeywordException) {
            return true;
        }
        return false;
    }
}
