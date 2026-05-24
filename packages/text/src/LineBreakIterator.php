<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * Lazy iterable view over the {@see LineBreakOpportunity}s in a string.
 * Built by {@see LineBreaker::breakOpportunities}.
 *
 * The iterator is single-pass — callers re-invoke `breakOpportunities` to
 * restart. Two iterations may yield slightly different boundaries if the
 * locale rules change in between (rare in practice).
 *
 * @implements \IteratorAggregate<int, LineBreakOpportunity>
 */
final class LineBreakIterator implements \IteratorAggregate
{
    public function __construct(
        private readonly string $text,
        private readonly string $locale,
    ) {}

    /**
     * @return \Generator<int, LineBreakOpportunity>
     */
    public function getIterator(): \Generator
    {
        $iter = \IntlBreakIterator::createLineInstance($this->locale);
        if ($iter === null) {
            return;
        }
        $iter->setText($this->text);
        $iter->first();
        $offset = $iter->next();
        while ($offset !== \IntlBreakIterator::DONE) {
            // Status >= LINE_HARD = mandatory break point (line terminator).
            $status = $iter->getRuleStatus();
            $kind = $status >= \IntlBreakIterator::LINE_HARD
                ? LineBreakKind::Mandatory
                : LineBreakKind::Allowed;
            yield new LineBreakOpportunity($offset, $kind);
            $offset = $iter->next();
        }
    }
}
