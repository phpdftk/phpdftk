<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\ResourceLoader\ResourceLoader;
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
     * Ergonomic alternative to `new SvgRenderer($page, $writer,
     * new Translator($loader))`. Use when you want network image
     * hrefs (`<image href="https://...">`) to resolve through
     * `phpdftk/resource-loader` without constructing a Translator
     * manually.
     *
     *   $renderer = SvgRenderer::withLoader($page, $writer, $loader);
     *   $renderer->draw($svg, x: 72, y: 600, width: 200, height: 200);
     */
    public static function withLoader(
        Page $page,
        PdfWriter $writer,
        ResourceLoader $resourceLoader,
    ): self {
        return new self($page, $writer, new Translator($resourceLoader));
    }

    /**
     * Paint `$svg` onto the renderer's page with its source coordinate
     * space mapped to the rectangle `(x, y) … (x + width, y + height)`.
     * Omitting `$width` / `$height` keeps the source's natural size.
     */
    /**
     * @param ContentStream|null $stream Override the page's primary
     *   content stream. When omitted, falls back to
     *   `$this->page->contentStream()` — the legacy behaviour. Callers
     *   that have already opened a graphics-state scope on a specific
     *   stream (a `q ... clip ... Q` wrap from a host renderer) must
     *   pass that stream here so the SVG draw appears inside the
     *   wrap; otherwise the page may attach a second content stream
     *   and the SVG paints outside the caller's clip context.
     */
    public function draw(
        SvgDocument $svg,
        float $x,
        float $y,
        ?float $width = null,
        ?float $height = null,
        ?ContentStream $stream = null,
    ): void {
        $stream ??= $this->page->contentStream();
        [$srcMinX, $srcMinY, $srcWidth, $srcHeight, $srcSynthetic] = self::resolveSourceRect($svg, $width, $height);

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
        // When the source rect was synthesised from the destination
        // (SVG had no viewBox and no parseable fixed dims), the
        // Translator's `currentViewport` would otherwise fall back to
        // the document's own width/height attributes — which for
        // percentage-only SVGs return zero/garbage and collapse
        // percentage-sized children. Pass the source rect so inner
        // percentage attributes resolve against the area the SVG
        // actually paints into.
        $effectiveViewport = $srcSynthetic
            ? ['w' => $srcWidth, 'h' => $srcHeight]
            : null;
        $this->translator->paint(
            $svg,
            $stream,
            $this->page,
            $this->writer,
            compensateTextFlip: true,
            effectiveViewport: $effectiveViewport,
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
        ?ResourceLoader $resourceLoader = null,
    ): Pdf {
        // Explicit parameter wins; otherwise fall back to the loader
        // attached to the Pdf via `withResourceLoader`. Lets callers
        // configure once and forget for the whole document.
        $resourceLoader ??= $pdf->resourceLoader();
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
            static function (Page $page, float $x, float $y, float $bw, float $bh) use ($svg, $pdf, $resourceLoader): void {
                $translator = $resourceLoader !== null
                    ? new Translator($resourceLoader)
                    : new Translator();
                (new self($page, $pdf->writer(), $translator))->draw($svg, $x, $y, $bw, $bh);
            },
        );
    }

    /**
     * Build a reusable Form XObject from an SVG that can be placed on
     * multiple pages without re-emitting the underlying operators.
     * Pair with `Page::drawTemplate($tpl, $x, $y, ?w, ?h)` for cheap
     * watermark / repeating-graphic use cases.
     *
     * The template's BBox is `[0, 0, $w, $h]` where `$w` / `$h`
     * follow the same dimension-resolution ladder as
     * {@see addToPdf()}:
     *
     *   - both set       → that rectangle (no aspect preservation)
     *   - only width     → height scales to the intrinsic aspect
     *   - only height    → width scales to the intrinsic aspect
     *   - neither        → the SVG's natural width / height
     *
     * Resources (gradients, fonts, embedded images) are registered on
     * `$resourceHost`. FormXObjects inherit resources from the page
     * that places them, so callers should reuse `$resourceHost` (or a
     * page sharing its resource pool) when invoking the template via
     * `Page::drawTemplate`. The template itself is registered with the
     * doc's writer immediately, so the returned handle is safe to
     * pass between pages.
     */
    public static function createTemplate(
        PdfDoc $doc,
        Page $resourceHost,
        SvgDocument $svg,
        ?float $width = null,
        ?float $height = null,
        ?ResourceLoader $resourceLoader = null,
    ): FormXObject {
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

        $bbox = new \Phpdftk\Geometry\Rectangle(0.0, 0.0, $w, $h);
        $writer = $doc->writer();
        return $doc->createTemplate(
            $bbox,
            static function (ContentStream $stream) use ($svg, $resourceHost, $writer, $w, $h, $resourceLoader): void {
                [$srcMinX2, $srcMinY2, $srcW, $srcH] = self::resolveSourceRect($svg);
                [$scaleX, $scaleY, $offsetX, $offsetY, $needsClip] = self::applyPreserveAspectRatio(
                    $svg,
                    $srcW,
                    $srcH,
                    $w,
                    $h,
                );
                $effectiveH = $scaleY * $srcH;
                $stream->saveGraphicsState();
                if ($needsClip) {
                    $stream->rectangle(0.0, 0.0, $w, $h);
                    $stream->clip();
                    $stream->endPath();
                }
                // Same derivation as SvgRenderer::draw with x=y=0:
                // map source-rect top-left to (offsetX, offsetY + effectiveH).
                $stream->concatMatrix(
                    $scaleX,
                    0.0,
                    0.0,
                    -$scaleY,
                    $offsetX - $srcMinX2 * $scaleX,
                    $offsetY + $effectiveH + $srcMinY2 * $scaleY,
                );
                $translator = $resourceLoader !== null
                    ? new Translator($resourceLoader)
                    : new Translator();
                $translator->paint(
                    $svg,
                    $stream,
                    $resourceHost,
                    $writer,
                    compensateTextFlip: true,
                );
                $stream->restoreGraphicsState();
            },
        );
    }

    /**
     * Resolve the SVG's source-coordinate rectangle. Returns a fifth
     * element `synthetic` — true when the source rect was derived
     * from the destination because the document carried at least one
     * percentage-style dimension attribute (`width="50%"`, etc.) but
     * no parseable fixed pair. The caller uses this to propagate the
     * destination as the effective viewport for inner percentage
     * attributes. When the document has no width/height/viewBox at
     * all, the legacy unit-square fallback is preserved.
     *
     * @return array{0: float, 1: float, 2: float, 3: float, 4: bool}
     *         minX, minY, width, height, synthetic
     */
    private static function resolveSourceRect(
        SvgDocument $svg,
        ?float $dstWidth = null,
        ?float $dstHeight = null,
    ): array {
        $viewBox = $svg->viewBox();
        if ($viewBox !== null) {
            return [$viewBox[0], $viewBox[1], $viewBox[2], $viewBox[3], false];
        }
        $widthAttr = $svg->widthAttribute();
        $heightAttr = $svg->heightAttribute();
        $w = self::parseLengthPrefix($widthAttr);
        $h = self::parseLengthPrefix($heightAttr);
        if ($w !== null && $h !== null) {
            return [0.0, 0.0, $w, $h, false];
        }
        // CSS Images 3 §5.2 — when the SVG doesn't have both a
        // fixed width AND fixed height (and no viewBox to imply a
        // ratio), use the caller's destination as the source
        // viewport. This covers: partial fixed dims, percentage
        // attributes, fully-omitted dims, and any combination of
        // those. Inner percentage attributes resolve against the
        // dst, matching browsers' "default object size" outcome
        // for `background-image: url(svg)`.
        if ($dstWidth !== null && $dstHeight !== null) {
            return [0.0, 0.0, $dstWidth, $dstHeight, true];
        }
        // No dst supplied (standalone draw with no width/height) and
        // no SVG-supplied dims either — fall back to a unit square
        // so the caller at least produces a finite-sized render.
        return [0.0, 0.0, $w ?? 1.0, $h ?? 1.0, false];
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
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*([%a-zA-Z]*)/', $raw, $m) !== 1) {
            return null;
        }
        // Percentage values carry no intrinsic dimension — CSS Images
        // 3 §5.2 treats `<svg width="50%">` as having no intrinsic
        // width. Reject so the caller can fall back to the dst
        // viewport as the source rect.
        if ($m[2] === '%') {
            return null;
        }
        $value = (float) $m[1];
        return $value <= 0.0 ? null : $value;
    }
}
