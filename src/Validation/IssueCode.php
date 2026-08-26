<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

/**
 * The canonical vocabulary of validation failure kinds. A {@see Issue} carries the *string* value (so that
 * consumers – and the neos/schematic pipeline, which adds its own phase-2 codes – can share one Issue type);
 * match against this enum via {@see self::tryFrom()}.
 */
enum IssueCode: string
{
    case InvalidType = 'invalid_type';
    case Required = 'required';
    case UnrecognizedKeys = 'unrecognized_keys';
    case TooSmall = 'too_small';
    case TooBig = 'too_big';
    case TooShort = 'too_short';
    case TooLong = 'too_long';
    case InvalidPattern = 'invalid_pattern';
    case InvalidFormat = 'invalid_format';
    case NotMultipleOf = 'not_multiple_of';
    case InvalidEnumValue = 'invalid_enum_value';
    case InvalidConst = 'invalid_const';
    case TooFewItems = 'too_few_items';
    case TooManyItems = 'too_many_items';
    case NotUnique = 'not_unique';
    /** anyOf/oneOf: the value matched none of the branches (or, for oneOf, not exactly one) */
    case InvalidUnion = 'invalid_union';
    /** not: the value matched a schema it must not match */
    case MustNotMatch = 'must_not_match';
    /** contains: no (or too few / too many) items matched the "contains" schema */
    case ContainsMismatch = 'contains_mismatch';
}
