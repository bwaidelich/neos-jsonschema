# JSON Schema

> [!WARNING]
> At this stage, this package is **experimental** and subject to change!

PHP Classes to represent *and validate against* JSON Schemas, see [JSON Schema](https://json-schema.org/).

The schema value objects live in the `Neos\JsonSchema` namespace, with their supporting value types in `Neos\JsonSchema\Support`; validation lives in `Neos\JsonSchema\Validation`.

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
(either valid, or the aggregated, path-located issues). Validation is *standard* JSON Schema — it does **not** coerce,
so the value must already be the right primitive type (that non-standard normalization lives in `neos/schematic`):

```php
$schema = StringSchema::create(minLength: 3, pattern: '^[a-z]+$');

assert($schema->validate('abc')->valid === true);

$result = $schema->validate('ab1');
assert($result->valid === false);
assert($result->issues->codes() === ['invalid_pattern']);
```

### Value Objects

A value object can expose its type to other packages – *without* depending on `neos/schematic` – by implementing
`Neos\JsonSchema\ProvidesSchema` (a `static schema(): Schema`) and validating via `self::schema()->validate($value)`.
`neos/schematic` bridges any such class into a coercion-capable schema via its `ProvidesSchemaMiddleware`.

```php
final class SomeValueObject implements ProvidesSchema
{

    private function __construct(
        public readonly string $value
    ) {
        $validationResult = self::schema()->validate($value);
        if (!$validationResult->valid) {
            throw new \InvalidArgumentException(sprintf('Invalid value "%s": %s', $value, $validationResult->issues));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function schema(): StringSchema
    {
        // note: memoized in a static variable, because readonly classes and enums cannot declare static properties
        static $schemaRuntimeCache = null;
        if ($schemaRuntimeCache === null) {
            $schemaRuntimeCache = StringSchema::create(minLength: 1, maxLength: 40, pattern: '^[a-z0-9\-]+$');
        }
        return $schemaRuntimeCache;
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

Each `Issue`'s `path`, `code` and `message` map directly onto the `errors` member of an
[RFC 9457](https://datatracker.ietf.org/doc/html/rfc9457) "Problem Details" response – no extra mapping layer needed.
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

That `$body` is the complete response payload – it just has to be sent with the media type and status
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