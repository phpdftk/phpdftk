<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\MathmlDocument;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mo;
use Phpdftk\Mathml\Ms;
use Phpdftk\Mathml\Mtext;

/**
 * Walks a {@see MathmlDocument} and gathers every Unicode codepoint
 * the painter will end up rendering. The PdfWriter Type-0 font
 * registration takes a codepoint list and subsets the underlying
 * font program against it, so we need to know what's actually used
 * before draw().
 *
 * Only token elements (mn, mi, mo, ms, mtext) contribute glyphs.
 * Token children of any other element are reached through the
 * recursive walk, so nested mfrac / msqrt / mtable still get their
 * tokens collected.
 */
final class MathmlCodepointCollector
{
    /**
     * @return list<int>
     */
    public static function collect(MathmlDocument $doc): array
    {
        $seen = [];
        self::walk($doc, $seen);
        return array_values($seen);
    }

    /**
     * @param array<int, int> $seen
     */
    private static function walk(Element $element, array &$seen): void
    {
        if (self::isToken($element)) {
            $text = $element->textContent();
            foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
                $cp = mb_ord($char, 'UTF-8');
                if ($cp !== false) {
                    $seen[$cp] = $cp;
                }
            }
        }
        foreach ($element->children as $child) {
            if ($child instanceof Element) {
                self::walk($child, $seen);
            }
        }
    }

    private static function isToken(Element $element): bool
    {
        return $element instanceof Mn
            || $element instanceof Mi
            || $element instanceof Mo
            || $element instanceof Ms
            || $element instanceof Mtext;
    }
}
