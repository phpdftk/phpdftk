<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\SvgDocument;

/**
 * Top-level adapter for placing a parsed SVG document onto a PDF page.
 *
 * Usage:
 *
 *     $renderer = new SvgRenderer($page, $writer);
 *     $renderer->draw($svg, x: 72, y: 300);          // intrinsic size
 *     $renderer->draw($svg, x: 72, y: 200, width: 4 * 72);
 *
 * The renderer wraps the painting in a `q` … `Q` pair so it never leaks
 * graphics state past its drawing area, and emits a `cm` that scales the
 * SVG's coordinate space to the user-supplied rectangle. The Translator
 * itself does the rest.
 *
 * **Orientation note (deferred)**: the renderer does not apply the
 * standard SVG y-down → PDF y-up flip yet. SVG content authored with
 * the conventional y-down assumption will therefore appear vertically
 * mirrored on the page until a follow-up sub-phase wires the flip and
 * the compensating text-matrix adjustment together. The orientation
 * fix lands alongside per-glyph text positioning and proper
 * `preserveAspectRatio` handling.
 *
 * Source-rectangle resolution:
 *
 *  1. SVG `viewBox` if present — `[minX, minY, w, h]`.
 *  2. Numeric prefix of `width` / `height` attributes (`100`, `100px`,
 *     `4in`, …) with `viewBox` `min` defaulting to 0.
 *  3. Fall back to a `1×1` source so a missing-dimensions SVG paints
 *     in its native coordinate scale (1 unit ≈ 1 PDF point).
 */
final class SvgRenderer
{
    public function __construct(
        private readonly Page $page,
        private readonly PdfWriter $writer,
        private readonly Translator $translator = new Translator(),
    ) {}

    /**
     * Paint `$svg` onto the renderer's page with its source coordinate
     * space mapped to the rectangle `(x, y) … (x + width, y + height)`.
     * Omitting `$width` / `$height` keeps the source's natural size.
     */
    public function draw(
        SvgDocument $svg,
        float $x,
        float $y,
        ?float $width = null,
        ?float $height = null,
    ): void {
        $stream = $this->page->contentStream();
        [$srcMinX, $srcMinY, $srcWidth, $srcHeight] = self::resolveSourceRect($svg);

        $dstWidth = $width ?? $srcWidth;
        $dstHeight = $height ?? $srcHeight;
        $scaleX = $srcWidth > 0.0 ? $dstWidth / $srcWidth : 1.0;
        $scaleY = $srcHeight > 0.0 ? $dstHeight / $srcHeight : 1.0;

        $stream->saveGraphicsState();
        // cm sx 0 0 sy x y — translate origin to (x, y), scale src→dst.
        // Then offset by -srcMin so a viewBox like "10 20 W H" still
        // lines up with (x, y) rather than starting beyond it.
        $stream->concatMatrix(
            $scaleX,
            0.0,
            0.0,
            $scaleY,
            $x - $srcMinX * $scaleX,
            $y - $srcMinY * $scaleY,
        );
        $this->translator->paint($svg, $stream, $this->page, $this->writer);
        $stream->restoreGraphicsState();
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     *         minX, minY, width, height
     */
    private static function resolveSourceRect(SvgDocument $svg): array
    {
        $viewBox = $svg->viewBox();
        if ($viewBox !== null) {
            return $viewBox;
        }
        $w = self::parseLengthPrefix($svg->widthAttribute()) ?? 1.0;
        $h = self::parseLengthPrefix($svg->heightAttribute()) ?? 1.0;
        return [0.0, 0.0, $w, $h];
    }

    /**
     * Extract the leading numeric prefix from an SVG length attribute
     * (`"100"`, `"100px"`, `"4in"`, …). Unit suffixes are ignored at
     * 3R — proper unit resolution lands alongside CSS Lengths in a
     * later sub-phase.
     */
    private static function parseLengthPrefix(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return null;
        }
        $value = (float) $m[1];
        return $value <= 0.0 ? null : $value;
    }
}
