<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Support;

/**
 * The formats defined by the JSON Schema specification (Draft 2020-12, section 7.3) – complete.
 *
 * The case names use underscores because hyphens are not allowed in PHP identifiers; the backing value is the
 * spelling from the specification ("date-time", …) and is what gets serialized.
 *
 * @see https://json-schema.org/understanding-json-schema/reference/string#built-in-formats
 * @see https://json-schema.org/draft/2020-12/draft-bhutton-json-schema-validation-01#rfc.section.7.3
 */
enum StringFormat: string
{
    // 7.3.1 Dates, Times, and Duration
    case date_time = 'date-time';
    case time = 'time';
    case date = 'date';
    case duration = 'duration';

    // 7.3.2 Email Addresses
    case email = 'email';
    case idn_email = 'idn-email';

    // 7.3.3 Hostnames
    case hostname = 'hostname';
    case idn_hostname = 'idn-hostname';

    // 7.3.4 IP Addresses
    case ipv4 = 'ipv4';
    case ipv6 = 'ipv6';

    // 7.3.5 Resource Identifiers
    case uuid = 'uuid';
    case uri = 'uri';
    case uri_reference = 'uri-reference';
    case iri = 'iri';
    case iri_reference = 'iri-reference';

    // 7.3.6 URI Template
    case uri_template = 'uri-template';

    // 7.3.7 JSON Pointers
    case json_pointer = 'json-pointer';
    case relative_json_pointer = 'relative-json-pointer';

    // 7.3.8 Regular Expressions
    case regex = 'regex';
}
