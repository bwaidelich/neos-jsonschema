<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

/**
 * Thrown by the {@see Validator} when it meets an *assertion* keyword it does not (yet) implement – e.g.
 * `unevaluatedItems`, or a `$ref` it cannot resolve.
 *
 * The {@see Validator} is deliberately strict here: silently skipping a recognised assertion would report a
 * false "valid". *Annotation* keywords that never constrain validity (e.g. `contentMediaType`, `title`) are
 * ignored, not thrown on.
 */
final class UnsupportedKeywordException extends \RuntimeException {}
