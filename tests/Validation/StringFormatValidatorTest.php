<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Tests\Validation;

use Neos\JsonSchema\Support\StringFormat;
use Neos\JsonSchema\Validation\StringFormatValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringFormatValidator::class)]
final class StringFormatValidatorTest extends TestCase
{
    /**
     * @return iterable<array{format: StringFormat, value: string}>
     */
    public static function valid_dataProvider(): iterable
    {
        yield ['format' => StringFormat::date_time, 'value' => '1963-06-19T08:30:06.283185Z'];
        yield ['format' => StringFormat::date_time, 'value' => '1963-06-19t08:30:06.283185z'];
        yield ['format' => StringFormat::date_time, 'value' => '1937-01-01T12:00:27.87+00:20'];
        yield ['format' => StringFormat::date_time, 'value' => '1998-12-31T23:59:60Z'];
        yield ['format' => StringFormat::time, 'value' => '08:30:06Z'];
        yield ['format' => StringFormat::time, 'value' => '08:30:06.283185+01:00'];
        yield ['format' => StringFormat::time, 'value' => '23:59:60Z'];
        yield ['format' => StringFormat::date, 'value' => '1963-06-19'];
        yield ['format' => StringFormat::date, 'value' => '2020-02-29'];
        yield ['format' => StringFormat::duration, 'value' => 'P4DT12H30M5S'];
        yield ['format' => StringFormat::duration, 'value' => 'PT1M'];
        yield ['format' => StringFormat::duration, 'value' => 'P4W'];
        yield ['format' => StringFormat::duration, 'value' => 'P1Y2M3D'];
        yield ['format' => StringFormat::email, 'value' => 'joe.bloggs@example.com'];
        yield ['format' => StringFormat::idn_email, 'value' => '실례@실례.테스트'];
        yield ['format' => StringFormat::hostname, 'value' => 'www.example.com'];
        yield ['format' => StringFormat::hostname, 'value' => 'xn--4gbwdl.xn--wgbh1c'];
        yield ['format' => StringFormat::idn_hostname, 'value' => '실례.테스트'];
        yield ['format' => StringFormat::idn_hostname, 'value' => 'example.com'];
        yield ['format' => StringFormat::ipv4, 'value' => '192.168.0.1'];
        yield ['format' => StringFormat::ipv6, 'value' => '::42:ff:1'];
        yield ['format' => StringFormat::uuid, 'value' => '2eb8aa08-aa98-11ea-b4aa-73b441d16380'];
        yield ['format' => StringFormat::uri, 'value' => 'http://foo.bar/?baz=qux#quux'];
        yield ['format' => StringFormat::uri, 'value' => 'http://example.com/%20path'];
        yield ['format' => StringFormat::uri, 'value' => 'urn:uuid:2eb8aa08-aa98-11ea-b4aa-73b441d16380'];
        yield ['format' => StringFormat::uri_reference, 'value' => '/abc'];
        yield ['format' => StringFormat::uri_reference, 'value' => '#/$defs/foo'];
        yield ['format' => StringFormat::uri_reference, 'value' => 'abc'];
        yield ['format' => StringFormat::iri, 'value' => 'http://ƒøø.ßår/?∂éœ=πîx#πîüx'];
        yield ['format' => StringFormat::iri_reference, 'value' => '#ƒrägmênt'];
        yield ['format' => StringFormat::iri_reference, 'value' => '/âbc'];
        yield ['format' => StringFormat::uri_template, 'value' => 'http://example.com/dictionary/{term:1}/{term}'];
        yield ['format' => StringFormat::uri_template, 'value' => 'http://example.com/search{?q,lang}'];
        yield ['format' => StringFormat::uri_template, 'value' => 'http://example.com/dictionary'];
        yield ['format' => StringFormat::json_pointer, 'value' => ''];
        yield ['format' => StringFormat::json_pointer, 'value' => '/foo/0/bar'];
        yield ['format' => StringFormat::json_pointer, 'value' => '/foo/~0/~1'];
        yield ['format' => StringFormat::json_pointer, 'value' => '/ '];
        yield ['format' => StringFormat::relative_json_pointer, 'value' => '0'];
        yield ['format' => StringFormat::relative_json_pointer, 'value' => '1/0'];
        yield ['format' => StringFormat::relative_json_pointer, 'value' => '2#'];
        yield ['format' => StringFormat::regex, 'value' => '^[a-z]+$'];
        yield ['format' => StringFormat::regex, 'value' => 'a~b'];
    }

    /**
     * @return iterable<array{format: StringFormat, value: string}>
     */
    public static function invalid_dataProvider(): iterable
    {
        yield ['format' => StringFormat::date_time, 'value' => '1963-06-19T08:30:06'];         // no offset
        yield ['format' => StringFormat::date_time, 'value' => '1963-06-19 08:30:06Z'];        // space separator
        yield ['format' => StringFormat::date_time, 'value' => '1990-02-31T15:59:59Z'];        // no such day
        yield ['format' => StringFormat::date_time, 'value' => '1963-6-19T08:30:06Z'];         // not zero padded
        yield ['format' => StringFormat::date_time, 'value' => '06/19/1963 08:30:06 PST'];
        yield ['format' => StringFormat::time, 'value' => '08:30:06'];                         // no offset
        yield ['format' => StringFormat::time, 'value' => '24:00:00Z'];
        yield ['format' => StringFormat::time, 'value' => '08:30:61Z'];
        yield ['format' => StringFormat::time, 'value' => '08:30:06+24:00'];
        yield ['format' => StringFormat::date, 'value' => '2021-02-30'];
        yield ['format' => StringFormat::date, 'value' => '2021-13-01'];
        yield ['format' => StringFormat::date, 'value' => '06/19/1963'];
        yield ['format' => StringFormat::duration, 'value' => 'P'];                            // no component
        yield ['format' => StringFormat::duration, 'value' => 'PT'];
        yield ['format' => StringFormat::duration, 'value' => '1D'];                           // no "P"
        yield ['format' => StringFormat::duration, 'value' => 'P1D2H'];                        // hours need a "T"
        yield ['format' => StringFormat::duration, 'value' => 'P2D1Y'];                        // wrong order
        yield ['format' => StringFormat::duration, 'value' => 'P1W2D'];                        // weeks do not combine
        yield ['format' => StringFormat::email, 'value' => 'not-an-email'];
        yield ['format' => StringFormat::idn_email, 'value' => '2962'];
        yield ['format' => StringFormat::hostname, 'value' => '-a-host-name-that-starts-with-'];
        yield ['format' => StringFormat::hostname, 'value' => 'not_a_valid_host_name'];
        yield ['format' => StringFormat::hostname, 'value' => '실례.테스트'];                     // not encoded
        yield ['format' => StringFormat::idn_hostname, 'value' => '-> $1.00 <-'];
        yield ['format' => StringFormat::idn_hostname, 'value' => 'xn--실례.테스트'];              // encoded label, unencoded content
        yield ['format' => StringFormat::ipv4, 'value' => '127.0.0.0.1'];
        yield ['format' => StringFormat::ipv4, 'value' => '127.000.000.001'];                  // leading zeroes
        yield ['format' => StringFormat::ipv4, 'value' => '::1'];
        yield ['format' => StringFormat::ipv6, 'value' => '12345::'];
        yield ['format' => StringFormat::ipv6, 'value' => '127.0.0.1'];
        yield ['format' => StringFormat::uuid, 'value' => '2eb8aa08-aa98-11ea-b4aa-73b441d1638'];
        yield ['format' => StringFormat::uri, 'value' => '//foo.bar/?baz=qux#quux'];           // no scheme
        yield ['format' => StringFormat::uri, 'value' => 'abc'];
        yield ['format' => StringFormat::uri, 'value' => '\\\\WINDOWS\\fileshare'];
        yield ['format' => StringFormat::uri, 'value' => 'http://foo.bar/?baz=qux#quux#corge']; // two fragments
        yield ['format' => StringFormat::uri, 'value' => 'http://example.com/%2'];             // truncated pct-encoding
        yield ['format' => StringFormat::uri_reference, 'value' => '\\\\WINDOWS\\fileshare'];
        yield ['format' => StringFormat::uri_reference, 'value' => 'a b'];
        yield ['format' => StringFormat::iri, 'value' => 'ƒøø.ßår'];                           // no scheme
        yield ['format' => StringFormat::iri_reference, 'value' => '\\\\WINDOWS\\fileshare'];
        yield ['format' => StringFormat::uri_template, 'value' => 'http://example.com/dictionary/{term:1}/{term'];
        yield ['format' => StringFormat::uri_template, 'value' => 'http://example.com/dictionary/}{'];
        yield ['format' => StringFormat::json_pointer, 'value' => 'foo/bar'];                  // no leading "/"
        yield ['format' => StringFormat::json_pointer, 'value' => '/foo/~2'];                  // unknown escape
        yield ['format' => StringFormat::json_pointer, 'value' => '/foo~'];
        yield ['format' => StringFormat::relative_json_pointer, 'value' => '/foo/bar'];        // no leading integer
        yield ['format' => StringFormat::relative_json_pointer, 'value' => '01/foo'];          // leading zero
        yield ['format' => StringFormat::relative_json_pointer, 'value' => '1#/foo'];          // "#" is terminal
        yield ['format' => StringFormat::regex, 'value' => '^(abc]'];
        yield ['format' => StringFormat::regex, 'value' => 'a{2,1}'];
    }

    #[DataProvider('valid_dataProvider')]
    public function test_matches_accepts_valid_values(StringFormat $format, string $value): void
    {
        self::assertTrue(StringFormatValidator::matches($format, $value), sprintf('Expected "%s" to be a valid "%s"', $value, $format->value));
    }

    #[DataProvider('invalid_dataProvider')]
    public function test_matches_rejects_invalid_values(StringFormat $format, string $value): void
    {
        self::assertFalse(StringFormatValidator::matches($format, $value), sprintf('Expected "%s" not to be a valid "%s"', $value, $format->value));
    }

    public function test_every_format_of_the_specification_is_supported(): void
    {
        foreach (StringFormat::cases() as $format) {
            StringFormatValidator::matches($format, 'some value');
        }
        self::assertCount(19, StringFormat::cases());
    }

    /**
     * @return iterable<array{format: StringFormat}>
     */
    public static function coverage_dataProvider(): iterable
    {
        foreach (StringFormat::cases() as $format) {
            yield $format->value => ['format' => $format];
        }
    }

    #[DataProvider('coverage_dataProvider')]
    public function test_every_format_has_a_valid_and_an_invalid_example(StringFormat $format): void
    {
        self::assertTrue(self::hasExample($format, self::valid_dataProvider()), sprintf('No valid example for the "%s" format', $format->value));
        self::assertTrue(self::hasExample($format, self::invalid_dataProvider()), sprintf('No invalid example for the "%s" format', $format->value));
    }

    /**
     * @param iterable<array{format: StringFormat, value: string}> $cases
     */
    private static function hasExample(StringFormat $format, iterable $cases): bool
    {
        foreach ($cases as $case) {
            if ($case['format'] === $format) {
                return true;
            }
        }
        return false;
    }
}
