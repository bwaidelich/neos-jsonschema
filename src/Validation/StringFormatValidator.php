<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

use Neos\JsonSchema\Support\StringFormat;

/**
 * Syntax checks for every {@see StringFormat} defined by the specification.
 *
 * These are *syntactic* assertions – they check the shape of the string, not whether the thing it names exists or
 * resolves. Where a fully faithful check would require data this package does not ship (the IDNA tables, a mail
 * server), the check is documented as an approximation on the respective method: it accepts every valid value and
 * rejects the malformed ones it can recognize, erring towards the former.
 * @internal used by {@see Validator}
 */
final class StringFormatValidator
{
    /**
     * Characters allowed in a URI (RFC 3986): unreserved / reserved (gen-delims + sub-delims) / "%" of pct-encoded
     */
    private const URI_CHARACTERS = 'A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%';

    /**
     * Non-ASCII characters an IRI (RFC 3987) additionally allows: ucschar / iprivate, i.e. anything beyond ASCII
     * that is not a control-, format-, surrogate- or unassigned code point
     */
    private const IRI_CHARACTERS = '^\x00-\x7F\p{C}';

    /**
     * A single label of a hostname (RFC 1123): 1-63 alphanumerics, inner hyphens allowed
     */
    private const HOSTNAME_LABEL = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';

    /**
     * The same, with unicode letters/digits allowed (see {@see self::isIdnHostname()})
     */
    private const IDN_HOSTNAME_LABEL = '[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?';

    /**
     * The reference token of a JSON Pointer (RFC 6901): any character but "~" and "/", or one of the two escapes
     */
    private const JSON_POINTER_TOKEN = '(?:[^~\/]|~[01])*';

    /**
     * dur-time of RFC 3339, Appendix A – the component order is fixed and at least one component is required
     */
    private const DURATION_TIME = '(?:\d+H(?:\d+M(?:\d+S)?)?|\d+M(?:\d+S)?|\d+S)';

    /**
     * duration of RFC 3339, Appendix A – either a number of weeks, or date and/or time components in descending
     * order of magnitude, none of which may be skipped in between
     */
    private const DURATION = '^P(?:\d+W|(?:\d+Y(?:\d+M(?:\d+D)?)?|\d+M(?:\d+D)?|\d+D)(?:T' . self::DURATION_TIME . ')?|T' . self::DURATION_TIME . ')$';

    public static function matches(StringFormat $format, string $value): bool
    {
        return match ($format) {
            StringFormat::date_time => self::isDateTime($value),
            StringFormat::time => self::isTime($value),
            StringFormat::date => self::isDate($value),
            StringFormat::duration => preg_match('/' . self::DURATION . '/', $value) === 1,
            StringFormat::email, StringFormat::idn_email => self::isEmail($value),
            StringFormat::hostname => preg_match('/^(?=.{1,253}\.?$)(?:' . self::HOSTNAME_LABEL . '\.)*' . self::HOSTNAME_LABEL . '\.?$/', $value) === 1,
            StringFormat::idn_hostname => self::isIdnHostname($value),
            StringFormat::ipv4 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            StringFormat::ipv6 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            StringFormat::uuid => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            StringFormat::uri => self::isUriReference($value, false) && self::hasScheme($value),
            StringFormat::uri_reference => self::isUriReference($value, false),
            StringFormat::iri => self::isUriReference($value, true) && self::hasScheme($value),
            StringFormat::iri_reference => self::isUriReference($value, true),
            StringFormat::uri_template => self::isUriTemplate($value),
            StringFormat::json_pointer => preg_match('/^(?:\/' . self::JSON_POINTER_TOKEN . ')*$/u', $value) === 1,
            StringFormat::relative_json_pointer => preg_match('/^(?:0|[1-9][0-9]*)(?:#|(?:\/' . self::JSON_POINTER_TOKEN . ')*)$/u', $value) === 1,
            StringFormat::regex => self::isRegex($value),
        };
    }

    /**
     * full-date of RFC 3339, section 5.6 – zero padded, and an existing day of the month
     */
    private static function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return false;
        }
        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /**
     * full-time of RFC 3339, section 5.6 – the time offset is *not* optional.
     *
     * Approximation: a leap second (":60") is accepted at any time, whereas RFC 3339 only allows it at the end of a
     * UTC day. Rejecting the others would require knowing which days actually had a leap second.
     */
    private static function isTime(string $value): bool
    {
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})(?:\.\d+)?([Zz]|[+-]\d{2}:\d{2})$/', $value, $matches) !== 1) {
            return false;
        }
        [, $hour, $minute, $second, $offset] = $matches;
        if ((int) $hour > 23 || (int) $minute > 59 || (int) $second > 60) {
            return false;
        }
        if ($offset === 'Z' || $offset === 'z') {
            return true;
        }
        // a numeric offset has the same hour/minute bounds
        return (int) substr($offset, 1, 2) <= 23 && (int) substr($offset, 4, 2) <= 59;
    }

    /**
     * date-time of RFC 3339, section 5.6 – full-date and full-time, separated by a (case insensitive) "T"
     */
    private static function isDateTime(string $value): bool
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[Tt](.+)$/', $value, $matches) !== 1) {
            return false;
        }
        return self::isDate($matches[1]) && self::isTime($matches[2]);
    }

    /**
     * Approximation of an addr-spec (RFC 5321, or RFC 6531 for the internationalized flavor): a non-empty local part,
     * "@", and a dotted domain. The exotic corners of the grammar – quoted local parts, comments, address literals –
     * are not recognized, and no attempt is made to verify that the domain exists.
     */
    private static function isEmail(string $value): bool
    {
        return preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/u', $value) === 1;
    }

    /**
     * Approximation of an internationalized hostname (RFC 5890): the hostname shape, with unicode letters and digits
     * allowed in the labels. The IDNA2008 rules that need the unicode tables – disallowed code points, the bidi rule,
     * the contextual rules for joiners – are not checked, so some strings that IDNA rejects pass here.
     */
    private static function isIdnHostname(string $value): bool
    {
        if (preg_match('/^(?=.{1,253}\.?$)(?:' . self::IDN_HOSTNAME_LABEL . '\.)*' . self::IDN_HOSTNAME_LABEL . '\.?$/u', $value) !== 1) {
            return false;
        }
        // an already encoded label ("xn--…") must not carry non-ASCII characters
        return preg_match('/(?:^|\.)xn--[^.]*[^\x00-\x7F]/ui', $value) !== 1;
    }

    /**
     * URI-reference of RFC 3986 (or IRI-reference of RFC 3987): the allowed character set, well-formed pct-encoding
     * and at most one fragment separator.
     *
     * Approximation: the component *grammar* is not enforced, so some malformed references built exclusively from
     * allowed characters (e.g. an authority with a non-numeric port) are accepted.
     */
    private static function isUriReference(string $value, bool $international): bool
    {
        $characters = '[' . self::URI_CHARACTERS . ']' . ($international ? '|[' . self::IRI_CHARACTERS . ']' : '');
        if (preg_match('/^(?:' . $characters . ')*$/u', $value) !== 1) {
            return false;
        }
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1) {
            return false;
        }
        return substr_count($value, '#') <= 1;
    }

    private static function hasScheme(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+\-.]*:/', $value) === 1;
    }

    /**
     * URI-Template of RFC 6570: literals (which exclude the ASCII characters the RFC lists as forbidden) and
     * expressions of an optional operator plus a comma separated list of varspecs.
     */
    private static function isUriTemplate(string $value): bool
    {
        $literal = '[^\x00-\x20\x7F"\'<>\\\\^`|{}%]|%[0-9A-Fa-f]{2}';
        $varchar = '(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})';
        $varspec = $varchar . '(?:\.?' . $varchar . ')*(?::[1-9][0-9]{0,3}|\*)?';
        $expression = '\{[+#.\/;?&=,!@|]?' . $varspec . '(?:,' . $varspec . ')*\}';
        return preg_match('/^(?:' . $literal . '|' . $expression . ')*$/u', $value) === 1;
    }

    /**
     * The specification asks for ECMA-262 regular expressions; this checks that the value compiles as the PCRE
     * pattern that the "pattern" keyword would run it as – the two dialects differ in some corners.
     */
    private static function isRegex(string $value): bool
    {
        return @preg_match('~' . str_replace('~', '\~', $value) . '~u', '') !== false;
    }
}
