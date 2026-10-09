# JSON Schema

> [!WARNING]
> At this stage, this package is **experimental** and subject to change!

PHP Classes to represent and validate against [JSON Schemas](https://json-schema.org/).

<!-- TOC -->
* [JSON Schema](#json-schema)
  * [Usage](#usage)
  * [Validating a value](#validating-a-value)
    * [Nullable values](#nullable-values)
    * [Unconstrained members](#unconstrained-members)
    * [Objects](#objects)
  * [Turning `Issues` into an RFC 9457 Problem Details response](#turning-issues-into-an-rfc-9457-problem-details-response)
  * [Static analysis with PHPStan](#static-analysis-with-phpstan)
  * [License](#license)
<!-- TOC -->

## Usage

This package can be installed via [composer](https://getcomposer.org):

```bash
composer require neos/jsonschema
```

With that, you can define a JSON Schema in PHP:

```php
$schema = ObjectSchema::create(
    title: 'Product',
    description: 'A product in the catalog',
    properties: ObjectProperties::create(
        id: StringSchema::create(
            title: 'ID',
            description: 'The unique identifier for a product',
            format: StringFormat::uuid,
        ),
        title: StringSchema::create(
            title: 'Product title',
            description: 'The name of the product',
        ),
        available: BooleanSchema::create(
            title: 'Whether the product is available',
        ),
        price: NumberSchema::create(
            title: 'Price',
            description: 'The price of the product',
            default: 0.0,
            minimum: 0.0,
        )
    )
);

$expected = <<<JSON
{
    "type": "object",
    "title": "Product",
    "description": "A product in the catalog",
    "properties": {
        "id": {
            "type": "string",
            "title": "ID",
            "description": "The unique identifier for a product",
            "format": "uuid"
        },
        "title": {
            "type": "string",
            "title": "Product title",
            "description": "The name of the product"
        },
        "available": {
            "type": "boolean",
            "title": "Whether the product is available"
        },
        "price": {
            "type": "number",
            "title": "Price",
            "description": "The price of the product",
            "default": 0,
            "minimum": 0
        }
    }
}
JSON;

assert(json_encode($schema, JSON_PRETTY_PRINT) === $expected);
```

## Validating a value

Every schema can validate a value against itself via `$schema->validate($value)`, which returns a `ValidationResult`
— either valid, or the aggregated, path-located issues:

```php
$schema = StringSchema::create(minLength: 3, pattern: '^[a-z]+$');

assert($schema->validate('abc')->valid === true);

$result = $schema->validate('ab1');
assert($result->valid === false);
assert($result->issues->codes() === ['invalid_pattern']);
```

A result is returned rather than thrown because invalid input is expected, not exceptional, and a caller handling a
request wants every issue at once. A caller *enforcing* an invariant is in the other position — a value object's
named constructor has nothing to report to and nothing to hand back — and asks for the exception instead:

```php
final readonly class Handle
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        self::schema()->validate($value)->throwIfInvalid();
        return new self($value);
    }

    public static function schema(): StringSchema
    {
        static $schema = null;
        return $schema ??= StringSchema::create(minLength: 1, pattern: '^[a-z]+$');
    }
}

assert(Handle::fromString('ada')->value === 'ada');

$caught = null;
try {
    Handle::fromString('Ada');
} catch (\Neos\JsonSchema\Validation\ValidationFailedException $exception) {
    $caught = $exception->issues->codes();
}
assert($caught === ['invalid_pattern']);
```

**Input must be JSON decoded to associative arrays** via `json_decode($json, true)`.

> [!WARNING]
> `\stdClass` can **not** be validated, as the following example shows:

```php
$schema = ObjectSchema::create(
    properties: ObjectProperties::create(
        title: StringSchema::create(),
        pages: IntegerSchema::create(),
    ),
    additionalProperties: false,
);

assert($schema->validate(['pages' => 42, 'title' => 'Dune'])->valid === true);
assert($schema->validate((object) ['title' => 'Dune', 'pages' => 42])->issues->codes() === ['invalid_type']);
```

If you want to model a *closed* object without allowing undeclared properties, set `additionalProperties: false`:

```php
$open = ObjectSchema::create(properties: ObjectProperties::create(title: StringSchema::create()));
assert($open->validate(['title' => 'Dune', 'isbn' => '978-0441013593'])->valid === true);

$closed = $open->with(additionalProperties: false);
$result = $closed->validate(['title' => 'Dune', 'isbn' => '978-0441013593']);

assert($result->valid === false);
assert($result->issues->codes() === ['unrecognized_keys']);
assert((string) $result->issues === '<root>: Unrecognized property: isbn (unrecognized_keys)');
```

### Nullable values

JSON Schema has no nullability flag: "this, or null" is a union with the null type. `Nullable::wrap()` builds it idempotently, so a schema that already accepts null is handed back untouched:

```php
$schema = Nullable::wrap(StringSchema::create(minLength: 1));

assert(json_encode($schema) === '{"anyOf":[{"type":"string","minLength":1},{"type":"null"}]}');
assert($schema->validate(null)->valid === true);
assert($schema->validate('Dune')->valid === true);
assert(Nullable::wrap($schema) === $schema);
```

`Nullable::unwrap()` is the way back, for a consumer that has to know what a member *is* and does not care that
it may also be absent:

```php
$string = StringSchema::create(minLength: 1);
assert(Nullable::unwrap(Nullable::wrap($string)) === $string);
assert(Nullable::unwrap($string) === $string);

$choice = Nullable::wrap(AnyOfSchema::create(StringSchema::create(), IntegerSchema::create()));
assert(Nullable::unwrap($choice) === $choice);
```


### Unconstrained members

`AnySchema` is JSON Schema's empty schema, `{}`: it accepts anything.

```php
$envelope = ObjectSchema::create(
    properties: ObjectProperties::create(
        id: StringSchema::create(),
        payload: AnySchema::create(description: 'Whatever the data source returned'),
    ),
    required: ['id', 'payload'],
);

assert(str_contains((string)json_encode($envelope), '"payload":{"description":"Whatever the data source returned"}'));
// with nothing to say about it either, it is the bare empty schema
assert(json_encode(AnySchema::create()) === '{}');

// nothing is ever invalid under it, and nothing is reshaped on the way through
assert(AnySchema::create()->validate(['anything' => [1, 2]])->valid === true);
```

## Objects

An object can expose its type to other packages by implementing
`Neos\JsonSchema\ProvidesSchema` (a `static schema(): Schema`) and validating via `self::schema()->validate($value)`.

```php
final class SomeValueObject implements ProvidesSchema
{

    private function __construct(
        public readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $validationResult = self::schema()->validate($value);
        if (!$validationResult->valid) {
            throw new \InvalidArgumentException(sprintf('Invalid value "%s": %s', $value, $validationResult->issues));
        }
        return new self($value);
    }

    public static function schema(): StringSchema
    {
        // note: memoized in a static variable, because readonly classes and enums cannot declare static properties
        static $schema = null;
        return $schema ??= StringSchema::create(minLength: 1, maxLength: 40, pattern: '^[a-z0-9\-]+$');
    }
}

$value = SomeValueObject::fromString('valid');
$exceptionMessage = '';
try {
    SomeValueObject::fromString('not valid');
} catch (\InvalidArgumentException $e) {
    $exceptionMessage = $e->getMessage();
}
assert($value->value === 'valid');
assert($exceptionMessage === 'Invalid value "not valid": <root>: Value does not match the pattern "^[a-z0-9\-]+$" (invalid_pattern)');
```

## Turning `Issues` into an RFC 9457 Problem Details response

Each Issue's `path`, `code` and `message` map directly onto the `errors` member of an
[RFC 9457](https://datatracker.ietf.org/doc/html/rfc9457) "Problem Details" response.
`Issue::pathAsJsonPointer()` renders the path as a [RFC 6901](https://datatracker.ietf.org/doc/html/rfc6901) JSON
Pointer (escaping `~` and `/` in the segments), which prefixed with `#` yields the URI fragment form such responses use:

```php
use Neos\JsonSchema\Validation\Issue;

$schema = ObjectSchema::create(
    properties: ObjectProperties::create(
        title: StringSchema::create(minLength: 3),
    ),
);

$result = $schema->validate(['title' => 'ab']);

$problem = [
    'type' => 'https://example.com/probs/validation-error',
    'title' => 'Your request parameters did not validate',
    'status' => 400,
    'instance' => '/products',
    'errors' => array_map(
        static fn(Issue $issue): array => [
            'pointer' => '#' . $issue->pathAsJsonPointer(),
            'code' => $issue->code,
            'detail' => $issue->message,
        ],
        $result->issues->toArray(),
    ),
];

$body = json_encode($problem, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$expected = <<<JSON
{
    "type": "https://example.com/probs/validation-error",
    "title": "Your request parameters did not validate",
    "status": 400,
    "instance": "/products",
    "errors": [
        {
            "pointer": "#/title",
            "code": "too_short",
            "detail": "Value must be at least 3 character(s) long"
        }
    ]
}
JSON;

assert($body === $expected);
```

That `$body` is the complete response payload - it just has to be sent with the media type and status
code the RFC prescribes:

```php (no test)
header('Content-Type: application/problem+json', true, 400);
echo $body;
```

With a PSR-7 stack the same payload becomes:

```php (no test)
$response = $responseFactory->createResponse(400)
    ->withHeader('Content-Type', 'application/problem+json');
$response->getBody()->write($body);
```

## Static analysis with PHPStan

This package ships a [PHPStan](https://phpstan.org) extension that detects, at design time, a schema that cannot be
right. With [phpstan/extension-installer](https://github.com/phpstan/extension-installer) it is enabled
automatically, otherwise include it in the `phpstan.neon` of your project:

```neon
includes:
  - vendor/neos/jsonschema/extension.neon
```

It reports:

| Identifier                                | Problem                                                                                                                                                               |
|-------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `jsonSchema.validate.incompatibleValue`   | A value passed to `validate()` that can *never* conform to the schema, e.g. an `int` passed to a `StringSchema`, or an array shape that lacks a required property     |
| `jsonSchema.schema.inconsistent.<kind>`   | A schema returned by `ProvidesSchema::schema()` that contradicts itself (`bounds`, `pattern`, `default`, `const`, `examples`, `enum`, `required` or `combinator`)    |
| `jsonSchema.schema.unavailable`           | A `ProvidesSchema::schema()` implementation that throws                                                                                                               |
| `jsonSchema.schema.notMemoized`           | The broken memoization idiom `static $schema; return $schema ?? …;` that rebuilds the schema on every call (`??=` was meant)                                          |

It also types the memoization idiom `static $schema = null; return $schema ??= XSchema::create(...);` inside
`ProvidesSchema::schema()` as the schema it builds. Without the extension, PHPStan types the static variable as `mixed`
and reports the return type of `schema()`, unless the variable is annotated with `/** @var XSchema|null $schema */`.

To know the actual schema, the extension **calls** `schema()` of every `ProvidesSchema` class during the analysis, so
those classes have to be autoloadable and their `schema()` free of side effects. Where the schema is not known
like that, only the static type of the schema (`StringSchema`, `ObjectSchema`, …) is taken into account.

Only values that can never be valid are reported: `mixed`, `int|string` or an array of unknown shape are not.

The extension does not compare a class's properties or constructor with its schema, because how they map onto each
other is a convention of the code consuming the schema, not of JSON Schema.

## License

MIT
