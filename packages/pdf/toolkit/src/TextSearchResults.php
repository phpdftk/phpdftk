<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

/**
 * Collection of text search matches across a PDF document.
 *
 * @implements \IteratorAggregate<int, TextMatch>
 */
final class TextSearchResults implements \IteratorAggregate, \Countable
{
    /** @param list<TextMatch> $matches */
    public function __construct(
        private readonly array $matches,
    ) {}

    public function count(): int
    {
        return count($this->matches);
    }

    /** @return list<TextMatch> */
    public function all(): array
    {
        return $this->matches;
    }

    public function first(): ?TextMatch
    {
        return $this->matches[0] ?? null;
    }

    /** @return \ArrayIterator<int, TextMatch> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->matches);
    }
}
