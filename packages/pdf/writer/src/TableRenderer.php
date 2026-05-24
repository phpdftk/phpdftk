<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Pdf\Core\Content\ContentStream;

/**
 * Renders {@see Table} content into a {@see ContentStream}. The
 * renderer is stateless and reusable; pagination logic lives at the
 * call site (`Pdf::addTable` measures each row and starts a new page
 * when needed).
 *
 * Coordinates: `$y` is the y-coordinate of the top edge of the row;
 * the row is drawn downward and the returned float is the height
 * consumed.
 */
final class TableRenderer
{
    /**
     * Height a single row needs, in points, after greedy word-wrap.
     *
     * @param list<string> $row
     * @param list<float>  $widths
     */
    public function rowHeight(array $row, array $widths, TableRenderContext $ctx, bool $isHeader): float
    {
        $size = $ctx->fontSize;
        $padding = $ctx->style->cellPadding;
        $metrics = $isHeader ? $ctx->headerMetrics : $ctx->bodyMetrics;
        $font = $isHeader ? $ctx->headerFont : $ctx->bodyFont;

        $maxLines = 1;
        $colCount = count($widths);
        for ($i = 0; $i < $colCount; $i++) {
            $cell = $row[$i] ?? '';
            $innerW = max(0.0, $widths[$i] - 2.0 * $padding);
            $encoded = $font->getTextEncoder()?->encode($cell) ?? $cell;
            $lines = TextLayout::wrap($encoded, $metrics, $size, $innerW);
            $maxLines = max($maxLines, count($lines));
        }

        return $maxLines * $size * $ctx->lineHeight + 2.0 * $padding;
    }

    /**
     * Draw one row at (x, y). Returns the height consumed so the
     * caller can advance its cursor.
     *
     * @param list<string> $row
     * @param list<float>  $widths
     */
    public function drawRow(
        ContentStream $cs,
        float $x,
        float $y,
        array $row,
        array $widths,
        TableRenderContext $ctx,
        bool $isHeader,
    ): float {
        $style = $ctx->style;
        $size = $ctx->fontSize;
        $padding = $style->cellPadding;
        $lineHeight = $size * $ctx->lineHeight;
        $font = $isHeader ? $ctx->headerFont : $ctx->bodyFont;
        $metrics = $isHeader ? $ctx->headerMetrics : $ctx->bodyMetrics;

        $rowH = $this->rowHeight($row, $widths, $ctx, $isHeader);
        $totalWidth = array_sum($widths);

        $cs->saveGraphicsState();

        // Header background fill, drawn before borders so the lines sit on top.
        if ($isHeader && $style->headerBgColor !== null) {
            [$r, $g, $b] = $style->headerBgColor;
            $cs->setFillColorRGB($r, $g, $b)
               ->rectangle($x, $y - $rowH, $totalWidth, $rowH)
               ->fill();
        }

        // Borders (cells outline + outer frame).
        if ($style->borderWidth > 0.0) {
            [$br, $bg, $bb] = $style->borderColor;
            $cs->setStrokeColorRGB($br, $bg, $bb)->setLineWidth($style->borderWidth);

            // Top and bottom of the row.
            $cs->moveTo($x, $y)->lineTo($x + $totalWidth, $y)->stroke();
            $cs->moveTo($x, $y - $rowH)->lineTo($x + $totalWidth, $y - $rowH)->stroke();

            // Vertical column dividers (left edge of every cell + right edge of last cell).
            $cx = $x;
            foreach ($widths as $w) {
                $cs->moveTo($cx, $y)->lineTo($cx, $y - $rowH)->stroke();
                $cx += $w;
            }
            $cs->moveTo($cx, $y)->lineTo($cx, $y - $rowH)->stroke();
        }

        // Cell content.
        $cs->setFillColorRGB(0.0, 0.0, 0.0);
        $colX = $x;
        $colCount = count($widths);
        for ($i = 0; $i < $colCount; $i++) {
            $cell = $row[$i] ?? '';
            $colW = $widths[$i];
            $innerW = max(0.0, $colW - 2.0 * $padding);

            $encoded = $font->getTextEncoder()?->encode($cell) ?? $cell;
            $lines = TextLayout::wrap($encoded, $metrics, $size, $innerW);
            $align = $style->alignmentFor($i);

            $textTop = $y - $padding;
            foreach ($lines as $lineIdx => $line) {
                $lineWidth = TextLayout::measure($line, $metrics, $size);
                $tx = $colX + $padding + match ($align) {
                    Alignment::Left   => 0.0,
                    Alignment::Center => ($innerW - $lineWidth) / 2.0,
                    Alignment::Right  => $innerW - $lineWidth,
                };
                $ty = $textTop - ($lineIdx + 1) * $lineHeight + ($lineHeight - $size) / 2.0;
                $cs->beginText()
                   ->setFont($font->getResourceName(), $size)
                   ->moveTextPosition($tx, $ty)
                   ->showText($line)
                   ->endText();
            }

            $colX += $colW;
        }

        $cs->restoreGraphicsState();
        return $rowH;
    }
}
