<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\FontMetrics\AfmData;
use Phpdftk\Pdf\Core\Content\ContentStream;

/**
 * Renders {@see ListBlock} content into a {@see ContentStream}.
 *
 * The renderer is stateless; pagination logic lives at the call site
 * (`Pdf::addList` measures each item and starts a new page when one
 * would overflow). Items are independent — measure and draw one at a
 * time, threading a running item number for numbered lists.
 *
 * Coordinates: `$y` is the top of the area available to the item; the
 * renderer draws downward and returns the height consumed.
 */
final class ListRenderer
{
    /**
     * Height needed to render a single item, in points.
     */
    public function measureItem(
        string $item,
        float $maxWidth,
        Font $font,
        AfmData $metrics,
        float $fontSize,
        float $lineHeight,
        ListStyle $style,
    ): float {
        $textWidth = max(0.0, $maxWidth - $style->indent);
        $encoded = $font->getTextEncoder()?->encode($item) ?? $item;
        $lines = TextLayout::wrap($encoded, $metrics, $fontSize, $textWidth);
        $lineCount = max(1, count($lines));
        return $lineCount * $fontSize * $lineHeight + $style->itemSpacing;
    }

    /**
     * Draw a single item at `(x, y)`. The marker (bullet glyph or
     * numbered prefix) sits at `$x`; the wrapped text starts at
     * `$x + $style->indent`.
     */
    public function drawItem(
        ContentStream $cs,
        float $x,
        float $y,
        string $item,
        float $maxWidth,
        Font $font,
        AfmData $metrics,
        float $fontSize,
        float $lineHeight,
        ListStyle $style,
        ?int $itemNumber,
    ): float {
        $textX = $x + $style->indent;
        $textWidth = max(0.0, $maxWidth - $style->indent);

        $marker = $itemNumber !== null
            ? $itemNumber . $style->numberSuffix
            : $style->bulletAt(0);

        $encoder = $font->getTextEncoder();
        $encodedMarker = $encoder?->encode($marker) ?? $marker;
        $encodedItem = $encoder?->encode($item) ?? $item;

        $lines = TextLayout::wrap($encodedItem, $metrics, $fontSize, $textWidth);
        if ($lines === []) {
            $lines = [''];
        }

        $lineH = $fontSize * $lineHeight;
        foreach ($lines as $lineIdx => $line) {
            $baselineY = $y - ($lineIdx + 1) * $lineH + ($lineH - $fontSize) / 2.0;

            if ($lineIdx === 0) {
                $cs->beginText()
                   ->setFont($font->getResourceName(), $fontSize)
                   ->moveTextPosition($x, $baselineY)
                   ->showText($encodedMarker)
                   ->endText();
            }

            if ($line !== '') {
                $cs->beginText()
                   ->setFont($font->getResourceName(), $fontSize)
                   ->moveTextPosition($textX, $baselineY)
                   ->showText($line)
                   ->endText();
            }
        }

        return count($lines) * $lineH + $style->itemSpacing;
    }

    /**
     * Convenience: draw an entire {@see ListBlock} starting at `(x, y)`
     * without pagination. Returns the height consumed. Useful for
     * `Writer\Page::drawList()` — for flow-paginated rendering, drive
     * the per-item loop yourself via {@see measureItem}/{@see drawItem}.
     */
    public function drawBlock(
        ContentStream $cs,
        float $x,
        float $y,
        ListBlock $block,
        float $maxWidth,
        Font $font,
        AfmData $metrics,
        float $fontSize,
        float $lineHeight,
        ListStyle $style,
    ): float {
        $consumed = 0.0;
        $itemNumber = 1;
        foreach ($block->items as $item) {
            $h = $this->drawItem(
                $cs,
                $x,
                $y - $consumed,
                $item,
                $maxWidth,
                $font,
                $metrics,
                $fontSize,
                $lineHeight,
                $style,
                $block->numbered ? $itemNumber : null,
            );
            $consumed += $h;
            $itemNumber++;
        }
        return $consumed;
    }
}
