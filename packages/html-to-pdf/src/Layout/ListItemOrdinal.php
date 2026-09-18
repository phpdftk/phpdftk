<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Html\Dom\Element;

/**
 * Resolves the ordinal a `display: list-item` element's `::marker`
 * counts with — HTML 5 §4.4.5 `<ol start>` / `<ol reversed>` /
 * `<li value>`.
 *
 * Shared by the two places that need it: {@see \Phpdftk\HtmlToPdf\Box\BoxGenerator},
 * which materialises the marker as inline content for
 * `list-style-position: inside`, and {@see \Phpdftk\HtmlToPdf\Painter\Painter},
 * which paints it beside the principal box for `outside`. Keeping one
 * implementation is what stops the two positions from numbering the
 * same list differently.
 */
final class ListItemOrdinal
{
    /**
     * The 1-based (or author-chosen — `start` / `value` may be zero or
     * negative) ordinal for `$item`.
     *
     * HTML 5 §4.4.5.2: `<li value="N">` sets the explicit ordinal AND
     * resets the count for the siblings that follow it, so the walk has
     * to run left-to-right from the parent's first child rather than
     * counting backwards from `$item`.
     */
    public static function of(Element $item): int
    {
        $parent = $item->parentNode;
        if (!$parent instanceof Element) {
            return 1;
        }
        // HTML 5 §4.4.5.3: `<ol start="N">` sets the starting count.
        // `<ol reversed>` counts down instead.
        $start = 1;
        $reversed = false;
        if (strtolower($parent->localName) === 'ol') {
            $rawStart = $parent->getAttribute('start');
            if ($rawStart !== null && preg_match('/^-?\d+$/', trim($rawStart)) === 1) {
                $start = (int) trim($rawStart);
            }
            $reversed = $parent->getAttribute('reversed') !== null;
        }
        if ($reversed) {
            // Count `<li>` siblings to derive the reversed initial value.
            $liCount = 0;
            for ($n = $parent->firstChild; $n !== null; $n = $n->nextSibling) {
                if ($n instanceof Element && strtolower($n->localName) === 'li') {
                    $liCount++;
                }
            }
            $count = $start === 1 ? $liCount + 1 : $start + 1;
            $step = -1;
        } else {
            $count = $start - 1;
            $step = 1;
        }
        for ($n = $parent->firstChild; $n !== null; $n = $n->nextSibling) {
            if (!($n instanceof Element) || strtolower($n->localName) !== 'li') {
                continue;
            }
            $raw = $n->getAttribute('value');
            if ($raw !== null && preg_match('/^-?\d+$/', trim($raw)) === 1) {
                $count = (int) trim($raw);
            } else {
                $count += $step;
            }
            if ($n === $item) {
                return $count;
            }
        }
        return $start;
    }
}
