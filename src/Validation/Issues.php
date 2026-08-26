<?php

declare(strict_types=1);

namespace Neos\JsonSchema\Validation;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * An immutable collection of {@see Issue}s.
 *
 * @implements IteratorAggregate<int, Issue>
 */
final readonly class Issues implements IteratorAggregate, Countable, Stringable
{
    /**
     * @var list<Issue>
     */
    public array $issues;

    public function __construct(Issue ...$issues)
    {
        $this->issues = array_values($issues);
    }

    public static function create(Issue ...$issues): self
    {
        return new self(...$issues);
    }

    public function withAppended(Issue $issue): self
    {
        return new self(...$this->issues, ...[$issue]);
    }

    public function merge(self $other): self
    {
        return new self(...$this->issues, ...$other->issues);
    }

    public function isEmpty(): bool
    {
        return $this->issues === [];
    }

    /**
     * @return list<Issue>
     */
    public function toArray(): array
    {
        return $this->issues;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn(Issue $issue): string => $issue->code, $this->issues);
    }

    public function count(): int
    {
        return count($this->issues);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->issues);
    }

    public function __toString(): string
    {
        return implode(', ', $this->issues);
    }
}
