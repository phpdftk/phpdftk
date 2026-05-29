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
 * `(x, y)` is the **bottom-left** of the destination rectangle in PDF
 * user space; the SVG's top edge lands at PDF y = `y + height` and its
 * bottom edge at PDF y = `y`. The renderer wraps everything in `q` / `Q`
 * so its graphics state never leaks past its drawing area.
 *
 * Coordinate alignment: the renderer flips the y-axis (SVG y-down → PDF
 * y-up) at the `cm` level and tells the `Translator` to compensate the
 * flip inside text objects via `Tm` so glyphs still render upright.
 * The flip is what makes SVG content appear right-side-up; the
 * compensation is what keeps text readable.
 *
 * Source-rectangle resolution:
 *
 *  1. SVG `viewBox` if present — `[minX, minY, w, h]`.
 *  2. Numeric prefix of `width` / `height` attributes (`100`, `100px`,
 *     `4in`, …) with `viewBox` `min` defaulting to 0.
 *  3. Fall back to `1 × 1` so a missing-dimensions SVG paints in its
 *     native coordinate scale (1 unit ≈ 1 PDF point).
 *
 * `preserveAspectRatio` handling (SVG 2 §7.10):
 *
 *  - `none` — scale axes independently to fill the destination.
 *  - Default (`xMidYMid meet`) — uniform scale to fit (letterbox),
 *    centred in the destination.
 *
 * Other alignment keywords (`xMinYMin`, `xMaxYMax`, …) and the `slice`
 * meet/slice mode are deferred to a future sub-phase; everything that
 * isn't `none` resolves to `xMidYMid meet` for now.
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

        [$scaleX, $scaleY, $offsetX, $offsetY] = self::applyPreserveAspectRatio(
            $svg,
            $srcWidth,
            $srcHeight,
            $dstWidth,
            $dstHeight,
        );

        // cm sx 0 0 -sy e f flips the y-axis. Derivation: a point
        // (svgMinX, svgMinY) (the SVG's top-left in source coords)
        // should land at PDF (x + offsetX, y + offsetY + effectiveH)
        // — i.e., the top of its bounding box. Solving the affine for
        // `(e, f)` gives:
        //   e = x + offsetX - srcMinX * sx
        //   f = y + offsetY + effectiveH + srcMinY * sy
        $effectiveH = $scaleY * $srcHeight;
        $stream->saveGraphicsState();
        $stream->concatMatrix(
            $scaleX,
            0.0,
            0.0,
            -$scaleY,
            $x + $offsetX - $srcMinX * $scaleX,
            $y + $offsetY + $effectiveH + $srcMinY * $scaleY,
        );
        $this->translator->paint(
            $svg,
            $stream,
            $this->page,
            $this->writer,
            compensateTextFlip: true,
        );
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
     * SVG 2 §7.10 viewport / viewBox alignment. Returns
     * `[scaleX, scaleY, offsetX, offsetY]` so the caller can fold them
     * into the outer `cm`. `none` reproduces the pre-fix behaviour
     * (independent axes, no offset). Anything else resolves to the
     * default `xMidYMid meet` — uniform scale to fit, centred.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function applyPreserveAspectRatio(
        SvgDocument $svg,
        float $srcW,
        float $srcH,
        float $dstW,
        float $dstH,
    ): array {
        $sx = $srcW > 0.0 ? $dstW / $srcW : 1.0;
        $sy = $srcH > 0.0 ? $dstH / $srcH : 1.0;
        $par = strtolower(trim($svg->getAttribute('preserveAspectRatio') ?? ''));
        if ($par === 'none') {
            return [$sx, $sy, 0.0, 0.0];
        }
        // Default: uniform scale + centre. Out-of-scope keywords
        // collapse to the same behaviour rather than failing the draw.
        $scale = min($sx, $sy);
        $effectiveW = $scale * $srcW;
        $effectiveH = $scale * $srcH;
        return [
            $scale,
            $scale,
            ($dstW - $effectiveW) / 2.0,
            ($dstH - $effectiveH) / 2.0,
        ];
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
