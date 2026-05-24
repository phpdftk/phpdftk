<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Pdf\Core\Content\ContentStream;

/**
 * Internal helper that walks a {@see BarcodeBitmap} and emits the
 * filled-rectangle operators that draw it into a `ContentStream`.
 *
 * Shared between `PdfDoc::createBarcode` (FormXObject), the
 * `Pdf::addBarcode` flow adapter, and `Writer\Page::drawBarcode`
 * positioned adapter so the visual output is identical.
 *
 * @internal
 */
final class BarcodeRendering
{
    /**
     * Emit fills for every dark module of `$bitmap` into `$cs`, with
     * `(0, 0)` at the bottom-left of the quiet zone. Coordinates are
     * in user units (points for PDF).
     *
     * Caller is responsible for `saveGraphicsState` + transformations
     * (translate / scale) that position the barcode on the page.
     */
    public static function renderInto(ContentStream $cs, BarcodeBitmap $bitmap): void
    {
        $mw = $bitmap->moduleWidth;
        $rows = $bitmap->rows();
        $cols = $bitmap->columns();
        $quiet = $bitmap->quietZoneModules;
        $cs->setFillColorRGB(0.0, 0.0, 0.0);

        // 1D barcode = single row → all bars use the full bar height.
        // 2D barcode = N rows × moduleWidth squares.
        $is2D = $rows > 1;

        for ($y = 0; $y < $rows; $y++) {
            $row = $bitmap->modules[$y];
            // For 2D, rows are drawn top-down; bottom-left of the
            // top-row module is at totalHeight - moduleWidth.
            $cellH = $is2D ? $mw : $bitmap->height;
            $rowOriginY = $is2D
                ? ($rows - 1 - $y) * $mw
                : 0.0;

            // Coalesce consecutive dark modules into one rectangle to
            // minimise emitted ops.
            $x = 0;
            while ($x < $cols) {
                if (!$row[$x]) {
                    $x++;
                    continue;
                }
                $runStart = $x;
                while ($x < $cols && $row[$x]) {
                    $x++;
                }
                $runLen = $x - $runStart;
                $cs->rectangle(
                    ($quiet + $runStart) * $mw,
                    $rowOriginY,
                    $runLen * $mw,
                    $cellH,
                );
            }
            $cs->fill();
        }
    }
}
