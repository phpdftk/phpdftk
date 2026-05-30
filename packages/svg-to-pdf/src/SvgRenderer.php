<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\Pdf;
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
 *  - `<align> meet` — uniform scale to fit (letterbox); the smallest
 *    axis scale wins so the whole content stays inside the
 *    destination rectangle.
 *  - `<align> slice` — uniform scale to fill; the largest axis scale
 *    wins so the destination is fully covered, and overflow is
 *    clipped to the destination rectangle.
 *
 * Both meet and slice honour all nine `<align>` keywords —
 * `xMinYMin`, `xMidYMin`, `xMaxYMin`, `xMinYMid`, `xMidYMid` (default),
 * `xMaxYMid`, `xMinYMax`, `xMidYMax`, `xMaxYMax` — controlling how the
 * scaled content is positioned within the destination rectangle.
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

        [$scaleX, $scaleY, $offsetX, $offsetY, $needsClip] = self::applyPreserveAspectRatio(
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
        if ($needsClip) {
            // `slice` mode lets the scaled content overflow the
            // destination rect. Clip to the dest rect first so the
            // overflow doesn't leak into other page content.
            $stream->rectangle($x, $y, $dstWidth, $dstHeight);
            $stream->clip();
            $stream->endPath();
        }
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
     * Drop an SVG into a top-level `Pdf` flow document, advancing the
     * cursor below it just like `Pdf::addImage` does. The Pdf class
     * itself doesn't depend on svg-to-pdf — it provides a generic
     * `Pdf::addBlock` hook this method wraps.
     *
     * Dimension resolution mirrors `Pdf::addImage`:
     *
     *   - Both `$width` and `$height` set → stretch to that rect (no
     *     aspect preservation, mirrors `addImage`).
     *   - Only one set → the other scales to the SVG's intrinsic
     *     aspect ratio (from the viewBox / width / height attributes).
     *   - Neither set → use the SVG's natural dimensions in PDF
     *     points (1 SVG unit = 1 point).
     *
     * Width caps at the current column width so a wide SVG doesn't
     * paint past the column edge — matches the row-of-text behaviour
     * the Pdf high-level API uses elsewhere.
     */
    public static function addToPdf(
        Pdf $pdf,
        SvgDocument $svg,
        ?float $width = null,
        ?float $height = null,
        Alignment $align = Alignment::Left,
    ): Pdf {
        [$srcMinX, $srcMinY, $srcWidth, $srcHeight] = self::resolveSourceRect($svg);
        $aspect = $srcHeight > 0.0 ? $srcWidth / $srcHeight : 1.0;

        if ($width === null && $height === null) {
            $w = $srcWidth;
            $h = $srcHeight;
        } elseif ($width !== null && $height === null) {
            $w = $width;
            $h = $aspect > 0.0 ? $width / $aspect : $width;
        } elseif ($width === null && $height !== null) {
            $h = $height;
            $w = $height * $aspect;
        } else {
            $w = (float) $width;
            $h = (float) $height;
        }
        unset($srcMinX, $srcMinY);

        return $pdf->addBlock(
            $w,
            $h,
            $align,
            static function (Page $page, float $x, float $y, float $bw, float $bh) use ($svg, $pdf): void {
                (new self($page, $pdf->writer()))->draw($svg, $x, $y, $bw, $bh);
            },
        );
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
     * `[scaleX, scaleY, offsetX, offsetY, needsClip]` so the caller can
     * fold them into the outer `cm` and decide whether to clip
     * overflow.
     *
     *  - `none` reproduces the original independent-axes behaviour, no
     *    offset, no clip.
     *  - `<align> meet` (default `xMidYMid meet`) — uniform scale to
     *    fit; the smaller axis scale wins.
     *  - `<align> slice` — uniform scale to fill; the larger axis
     *    scale wins. The leftover "leftover" goes negative, so the
     *    scaled content overflows the destination rectangle on one
     *    axis; the caller is told to add a destination-rect clip.
     *
     * @return array{0: float, 1: float, 2: float, 3: float, 4: bool}
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
            return [$sx, $sy, 0.0, 0.0, false];
        }

        $tokens = preg_split('/\s+/', $par) ?: [];
        $align = $tokens[0] ?? '';
        $slice = ($tokens[1] ?? 'meet') === 'slice';
        [$xRatio, $yRatio] = self::alignRatios($align);

        $scale = $slice ? max($sx, $sy) : min($sx, $sy);
        $effectiveW = $scale * $srcW;
        $effectiveH = $scale * $srcH;

        return [
            $scale,
            $scale,
            $xRatio * ($dstW - $effectiveW),
            $yRatio * ($dstH - $effectiveH),
            $slice,
        ];
    }

    /**
     * Map an SVG 2 §7.10 align keyword to a pair of ratios in the
     * `[xRatio, yRatio]` shape used by `applyPreserveAspectRatio`:
     *
     *  - `xRatio` ∈ {0, 0.5, 1} — how much of the X-leftover sits on
     *    the LEFT side of the content (`xMin` → 0, `xMid` → 0.5,
     *    `xMax` → 1).
     *  - `yRatio` ∈ {0, 0.5, 1} — how much of the Y-leftover sits
     *    BELOW the content in PDF coords. Because PDF's y axis points
     *    up and SVG's points down, "Y at the top" maps to "all
     *    leftover at the bottom" → `yMin` → 1, `yMid` → 0.5,
     *    `yMax` → 0.
     *
     * Unknown keywords default to `xMidYMid` (centre).
     *
     * @return array{0: float, 1: float}
     */
    private static function alignRatios(string $align): array
    {
        $alignLower = strtolower($align);
        $xRatio = match (true) {
            str_starts_with($alignLower, 'xmin') => 0.0,
            str_starts_with($alignLower, 'xmax') => 1.0,
            default => 0.5,
        };
        // The Y keyword sits after the X part — `xMinYMin`, `xMidYMax`, …
        $yRatio = match (true) {
            str_contains($alignLower, 'ymin') => 1.0,
            str_contains($alignLower, 'ymax') => 0.0,
            default => 0.5,
        };
        return [$xRatio, $yRatio];
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
