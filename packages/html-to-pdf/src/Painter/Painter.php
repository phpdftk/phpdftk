<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Painter;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Layout\BoxGeometry;
use Phpdftk\HtmlToPdf\Layout\InlineFragment;
use Phpdftk\HtmlToPdf\Layout\LineBox;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Font\RegisteredFont;
use Phpdftk\Pdf\Writer\Font as WriterFont;
use Phpdftk\Pdf\Writer\Page as WriterPage;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Phase 1G — paints a laid-out box tree onto a {@see ContentStream}.
 *
 * The painter walks the box tree depth-first and emits PDF operators for
 * each box's visual contributions: background colour (rect + fill), then
 * border edges (four straight strokes one per side, honouring per-side
 * widths and colours), then recurses into children. Text rendering uses
 * the line-box / shaped-glyph data deposited by {@see InlineLayout};
 * Phase 1G.1 ships background + border painting and leaves text as a
 * follow-up that depends on `@font-face` integration (1M) — for now line
 * boxes are walked but text painting is a no-op, so the painter exercises
 * end-to-end without requiring a font registration.
 *
 * **Coordinate-system flip**: the layout uses PDF user-space units but
 * with Y growing downward from the top of the page (the convention of CSS
 * and every other layout engine). PDF's native content-stream coordinates
 * grow upward from the bottom. The painter flips Y when emitting
 * rectangles so consumers see PDF-correct output; the underlying box
 * geometry stays in top-down space for layout sanity.
 */
final class Painter
{
    public function __construct(
        private readonly float $pageHeight,
        private readonly ?RegisteredFont $defaultFont = null,
        private readonly ?WriterPage $page = null,
        /**
         * Layout-Y range this page covers. When set, the painter skips
         * any box whose geometry sits entirely above or entirely below
         * this range — a multi-page document no longer re-paints every
         * box on every page, just the ones intersecting the current
         * page slot.
         */
        private readonly ?float $pageRangeStart = null,
        private readonly ?float $pageRangeEnd = null,
        /**
         * When set, the painter can register Image XObjects via the
         * writer's `addImage` and emit `Do` for `<img>` elements whose
         * `src` is a `data:image/{png,jpeg}` URL. When null, image
         * painting is a no-op (the alt-text fallback still flows).
         */
        private readonly ?PdfWriter $writer = null,
        /**
         * Base directory for resolving relative `<img src>` paths against
         * the filesystem. When null, only `data:` URLs paint.
         */
        private readonly ?string $baseDir = null,
        /**
         * Map of `postScriptName → RegisteredFont` keyed by the font's
         * raw PS name. Used to switch `Tf` per fragment when an inline
         * subtree shaped against an alternate font from the `FontResolver`.
         * Defaults to `[$defaultFont->postScriptName => $defaultFont]`
         * when only the default is registered.
         *
         * @var array<string, RegisteredFont>
         */
        private readonly array $registeredFonts = [],
        /**
         * Page width in PDF user-space units. Used by per-axis
         * `overflow-x` / `overflow-y` clipping to extend the clip
         * rect across the unconstrained axis. Defaults to a value
         * large enough that any reasonable page-width effectively
         * disables horizontal clipping when only the Y axis clips.
         */
        private readonly float $pageWidth = 100000.0,
    ) {}

    /**
     * Track tempfile paths created for `data:` URL images so we can
     * delete them when the Painter is destroyed.
     *
     * @var list<string>
     */
    private array $tempImagePaths = [];

    /**
     * Cache `data:` URL → registered XObject resource name for the
     * current page, so the same image used multiple times only spills
     * + registers once.
     *
     * @var array<string, string>
     */
    private array $imageNameCache = [];

    public function __destruct()
    {
        foreach ($this->tempImagePaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Link rects in PDF coordinates collected during the most recent
     * {@see paint()} call. Each entry is `{href, llx, lly, urx, ury,
     * title}` with the Y-flip already applied. The Renderer reads this
     * list to register `/Link` annotations on the current page.
     *
     * @var list<array{href: string, llx: float, lly: float, urx: float, ury: float, title: ?string}>
     */
    public array $collectedLinks = [];

    public function paint(Box $root, ContentStream $stream): void
    {
        $this->collectedLinks = [];
        $this->imageNameCache = [];
        $this->paintBox($root, $stream);
    }

    private function paintBox(Box $box, ContentStream $stream): void
    {
        // Off-page skip: the box's layout-Y range doesn't overlap this
        // page's range. We still must descend into children for the
        // `<a href>` link-rect collection (which uses the page constant
        // to compute PDF-Y), but skip the heavy paint operations.
        if ($this->boxEntirelyOffPage($box)) {
            return;
        }
        $opacityGsName = $this->resolveOpacityGsName($box);
        if ($opacityGsName !== null) {
            $stream->saveGraphicsState();
            $stream->setGraphicsState($opacityGsName);
        }
        // CSS Transforms 2 §6: apply the box's transform (if any)
        // before any drawing. The graphics state save/restore wraps
        // the entire paint (background + content + children) so the
        // transform affects every nested operation.
        $hasTransform = $this->applyBoxTransform($box, $stream);
        // CSS Visual Formatting Model 9.5: `visibility: hidden` boxes
        // occupy layout space but paint nothing themselves; descendants
        // with their own `visibility` declaration can still be visible.
        $hidden = $this->isVisibilityHidden($box);
        if (!$hidden) {
            // CSS Fragmentation 4 §5.5: `box-decoration-break: clone`
            // makes each fragment paint full decorations as if it were
            // a standalone box. For a straddling box we temporarily
            // swap in a geometry clamped to this page's visible
            // extent, so background/border/shadow draw at the page
            // seam as a synthetic edge.
            $originalGeo = null;
            if ($this->shouldClampDecorationsToPage($box)) {
                $originalGeo = $box->geometry;
                $box->geometry = $this->clampGeometryToPage($originalGeo);
            }
            // CSS Backgrounds 3 §6.1.1 — paint stack from bottom up:
            // outset shadows → background → inset shadows → border.
            $this->paintBoxShadow($box, $stream, insetOnly: false);
            $this->paintBackground($box, $stream);
            $this->paintBoxShadow($box, $stream, insetOnly: true);
            $this->paintBorders($box, $stream);
            if ($originalGeo !== null) {
                $box->geometry = $originalGeo;
            }
            $this->paintOutline($box, $stream);
            $this->paintColumnRules($box, $stream);
            $this->paintImage($box, $stream);
            $this->paintListMarker($box, $stream);
            $this->paintLineBoxes($box, $stream);
            $this->collectBlockLinkRect($box);
        }
        // CSS Overflow 3 §3 — `overflow: hidden | clip | scroll | auto`
        // clips descendants to the box's padding-edge. `visible` (the
        // initial value) lets descendants render outside the box.
        // Print medium can't scroll, so `scroll` / `auto` behave like
        // `hidden` here. The clip is push/popped around the children
        // loop so siblings of this box stay unaffected.
        $overflowClip = $this->shouldOverflowClip($box);
        if ($overflowClip) {
            $stream->saveGraphicsState();
            $this->emitOverflowClipPath($stream, $box);
        }
        foreach ($box->children as $child) {
            $this->paintBox($child, $stream);
        }
        if ($overflowClip) {
            $stream->restoreGraphicsState();
        }
        if ($hasTransform) {
            $stream->restoreGraphicsState();
        }
        if ($opacityGsName !== null) {
            $stream->restoreGraphicsState();
        }
    }

    /**
     * Return `true` when the box's layout-Y range sits entirely above or
     * entirely below the painter's configured page range. Skipping these
     * subtrees lets a 100-page document not re-paint every box on every
     * page. Falls back to `false` (always paint) when the page range
     * isn't set — preserves the old behaviour for single-page renders.
     */
    private function boxEntirelyOffPage(Box $box): bool
    {
        if ($this->pageRangeStart === null || $this->pageRangeEnd === null) {
            return false;
        }
        $g = $box->geometry;
        $top = $g->y;
        $bottom = $g->y + $g->outerHeight();
        // Outline boxes / anonymous boxes can carry zero geometry; never
        // skip them — descendants may still be in range.
        if ($bottom === $top) {
            return false;
        }
        return $bottom <= $this->pageRangeStart || $top >= $this->pageRangeEnd;
    }

    /**
     * Apply the box's CSS `transform` (if any) via the PDF `cm`
     * operator. Returns `true` if a graphics-state save was emitted
     * (the caller must restoreGraphicsState after painting); `false`
     * if no transform applied. The transform is composed as
     *   T(origin) × M_css→pdf × T(-origin)
     * where M is the composition of all transform functions and the
     * origin sits at the box's `transform-origin` in PDF coordinates.
     */
    private function applyBoxTransform(Box $box, ContentStream $stream): bool
    {
        $value = $box->style->get('transform');
        if (!$value instanceof \Phpdftk\Css\Value\Transform || $value->functions === []) {
            return false;
        }
        $matrix = $this->composeTransformMatrix($value, $box);
        if ($matrix === null) {
            return false;
        }
        [$ox, $oy] = $this->resolveTransformOrigin($box);
        // T(ox, oy) × M × T(-ox, -oy). PDF cm composes
        // CTM_new = CTM_old × M_provided, so submit in
        // outer-to-inner order: translate(+), matrix, translate(-).
        $stream->saveGraphicsState();
        if ($ox !== 0.0 || $oy !== 0.0) {
            $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, $ox, $oy);
        }
        $stream->concatMatrix($matrix[0], $matrix[1], $matrix[2], $matrix[3], $matrix[4], $matrix[5]);
        if ($ox !== 0.0 || $oy !== 0.0) {
            $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, -$ox, -$oy);
        }
        return true;
    }

    /**
     * Compose the box's transform-function list into a single 2D
     * matrix [a, b, c, d, e, f] in PDF coordinate space (Y-up).
     * Returns `null` if no function produced output (3D-only
     * transforms flatten to identity at Phase 2).
     *
     * @return ?array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    private function composeTransformMatrix(\Phpdftk\Css\Value\Transform $transform, Box $box): ?array
    {
        $result = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]; // identity
        $any = false;
        foreach ($transform->functions as $fn) {
            $m = $this->transformFunctionToPdfMatrix($fn, $box);
            if ($m === null) {
                continue;
            }
            $result = $this->multiplyMatrices($result, $m);
            $any = true;
        }
        return $any ? $result : null;
    }

    /**
     * Convert a single CSS transform-function to a 2D PDF matrix
     * (Y-up). The conversion negates the (b, c, f) entries — that's
     * the matrix-form of conjugating by a Y-axis flip, which maps
     * CSS's Y-down coords to PDF's Y-up.
     *
     * @return ?array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    private function transformFunctionToPdfMatrix(
        \Phpdftk\Css\Value\TransformFunction $fn,
        Box $box,
    ): ?array {
        if ($fn instanceof \Phpdftk\Css\Value\TranslateTransform) {
            $tx = $this->lengthOrPercentageToFloat($fn->x, $box->geometry->width);
            $ty = $this->lengthOrPercentageToFloat($fn->y, $box->geometry->height);
            return [1.0, 0.0, 0.0, 1.0, $tx, -$ty];
        }
        if ($fn instanceof \Phpdftk\Css\Value\RotateTransform) {
            // 3D rotations around the X or Y axis flatten to identity
            // at Phase 2 (we don't render in perspective); only the Z
            // axis rotation produces a 2D effect.
            if (($fn->ax !== 0.0 || $fn->ay !== 0.0) && $fn->az === 0.0) {
                return null;
            }
            $rad = deg2rad($fn->angleDeg);
            $cos = cos($rad);
            $sin = sin($rad);
            return [$cos, -$sin, $sin, $cos, 0.0, 0.0];
        }
        if ($fn instanceof \Phpdftk\Css\Value\ScaleTransform) {
            return [$fn->sx, 0.0, 0.0, $fn->sy, 0.0, 0.0];
        }
        if ($fn instanceof \Phpdftk\Css\Value\SkewTransform) {
            $tanX = tan(deg2rad($fn->xDeg));
            $tanY = tan(deg2rad($fn->yDeg));
            // CSS skewX: [1, 0, tan(x), 1]; PDF flips b+c → [1, 0, -tan(x), 1]
            // CSS skewY: [1, tan(y), 0, 1]; PDF flips → [1, -tan(y), 0, 1]
            return [1.0, -$tanY, -$tanX, 1.0, 0.0, 0.0];
        }
        if ($fn instanceof \Phpdftk\Css\Value\MatrixTransform) {
            return [$fn->a, -$fn->b, -$fn->c, $fn->d, $fn->e, -$fn->f];
        }
        return null;
    }

    /**
     * Multiply two 2D affine matrices in [a, b, c, d, e, f] form:
     *   M = [a c e]    M' = [a' c' e']    M × M' = [...]
     *       [b d f]         [b' d' f']
     *       [0 0 1]         [0  0  1]
     *
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} $m1
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} $m2
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    private function multiplyMatrices(array $m1, array $m2): array
    {
        [$a1, $b1, $c1, $d1, $e1, $f1] = $m1;
        [$a2, $b2, $c2, $d2, $e2, $f2] = $m2;
        return [
            $a1 * $a2 + $c1 * $b2,
            $b1 * $a2 + $d1 * $b2,
            $a1 * $c2 + $c1 * $d2,
            $b1 * $c2 + $d1 * $d2,
            $a1 * $e2 + $c1 * $f2 + $e1,
            $b1 * $e2 + $d1 * $f2 + $f1,
        ];
    }

    /**
     * Resolve `transform-origin` to a PDF coordinate point. Default
     * `50% 50%` puts the pivot at the box's centre. Lengths and
     * percentages compose; percentages resolve against the box's
     * border-box dimension on each axis.
     *
     * @return array{0: float, 1: float} (px, py) in PDF coords.
     */
    private function resolveTransformOrigin(Box $box): array
    {
        $g = $box->geometry;
        $width = $g->width + $g->paddingLeft + $g->paddingRight + $g->borderLeft + $g->borderRight;
        $height = $g->height + $g->paddingTop + $g->paddingBottom + $g->borderTop + $g->borderBottom;
        $boxX = $g->x - $g->paddingLeft - $g->borderLeft;
        $boxY = $g->y - $g->paddingTop - $g->borderTop;

        $value = $box->style->get('transform-origin');
        $offX = $width / 2.0;
        $offY = $height / 2.0;
        if ($value instanceof \Phpdftk\Css\Value\ValueList && count($value->values) >= 2) {
            $offX = $this->resolveOriginComponent($value->values[0], $width, $offX);
            $offY = $this->resolveOriginComponent($value->values[1], $height, $offY);
        }
        $cssY = $boxY + $offY;
        return [$boxX + $offX, $this->pageHeight - $cssY];
    }

    private function resolveOriginComponent(
        \Phpdftk\Css\Value\Value $value,
        float $extent,
        float $fallback,
    ): float {
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $value->value / 100.0 * $extent;
        }
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            return match (strtolower($value->name)) {
                'left', 'top' => 0.0,
                'right', 'bottom' => $extent,
                'center' => $extent / 2.0,
                default => $fallback,
            };
        }
        return $fallback;
    }

    private function lengthOrPercentageToFloat(
        \Phpdftk\Css\Value\Length|\Phpdftk\Css\Value\Percentage $value,
        float $basis,
    ): float {
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        return $value->value / 100.0 * $basis;
    }

    /**
     * `true` when this box (a) declares `box-decoration-break: clone`
     * AND (b) actually straddles the current page boundary. Boxes that
     * fit entirely on one page don't need the clamp — slice and clone
     * paint identically in that case.
     */
    private function shouldClampDecorationsToPage(Box $box): bool
    {
        if ($this->pageRangeStart === null || $this->pageRangeEnd === null) {
            return false;
        }
        if (!$this->isCloneDecorationBreak($box)) {
            return false;
        }
        $g = $box->geometry;
        $outerTop = $g->y - $g->paddingTop - $g->borderTop - $g->marginTop;
        $outerBottom = $g->y + $g->height + $g->paddingBottom + $g->borderBottom + $g->marginBottom;
        return $outerTop < $this->pageRangeStart || $outerBottom > $this->pageRangeEnd;
    }

    private function isCloneDecorationBreak(Box $box): bool
    {
        $value = $box->style->get('box-decoration-break');
        if (!($value instanceof Keyword)) {
            return false;
        }
        return strtolower($value->name) === 'clone';
    }

    /**
     * Return a clone of `$g` with content y / height clamped so the
     * box's outer margin-box sits entirely inside the painter's
     * current page range. Used by `box-decoration-break: clone` to
     * make each fragment paint full borders at its visible extent.
     */
    private function clampGeometryToPage(BoxGeometry $g): BoxGeometry
    {
        $clone = clone $g;
        if ($this->pageRangeStart === null || $this->pageRangeEnd === null) {
            return $clone;
        }
        $outerTop = $g->y - $g->paddingTop - $g->borderTop - $g->marginTop;
        $outerBottom = $g->y + $g->height + $g->paddingBottom + $g->borderBottom + $g->marginBottom;
        if ($outerTop < $this->pageRangeStart) {
            $delta = $this->pageRangeStart - $outerTop;
            $clone->y += $delta;
            $clone->height = max(0.0, $clone->height - $delta);
        }
        if ($outerBottom > $this->pageRangeEnd) {
            $delta = $outerBottom - $this->pageRangeEnd;
            $clone->height = max(0.0, $clone->height - $delta);
        }
        return $clone;
    }

    /**
     * Phase-1 `<img src="data:image/...">` painter: decodes the data URL,
     * spills the bytes to a tempfile, registers an Image XObject on the
     * current page via the writer, and emits `q cm /Name Do Q` at the
     * box's geometry. No-op when the writer or page is not wired in, or
     * when the src isn't a `data:image/png|jpeg` URL we can paint.
     */
    private function paintImage(Box $box, ContentStream $stream): void
    {
        if (!($box instanceof \Phpdftk\HtmlToPdf\Box\AtomicInlineBox)) {
            return;
        }
        if ($this->writer === null || $this->page === null) {
            return;
        }
        $element = $box->element;
        if ($element === null || strtolower($element->localName) !== 'img') {
            return;
        }
        $src = $element->getAttribute('src');
        if ($src === null) {
            return;
        }
        // Per-page cache: the same src only spills + registers once on
        // this page. Multi-page reuse still re-registers — XObject
        // resource names are page-local in PdfWriter.
        if (isset($this->imageNameCache[$src])) {
            $name = $this->imageNameCache[$src];
        } else {
            $resolvedPath = $this->resolveImageSrc($src);
            if ($resolvedPath === null) {
                return;
            }
            // ImageParser throws on malformed bytes; swallow + fall back to
            // the alt-text path (or empty box) rather than crashing the whole
            // render because of one bad asset.
            try {
                $name = $this->writer->addImage($resolvedPath, $this->page);
            } catch (\Throwable) {
                return;
            }
            $this->imageNameCache[$src] = $name;
        }
        $geo = $box->geometry;
        if ($geo->width <= 0.0) {
            // No declared size — skip; the alt-text fallback path covers
            // unsized images via the BoxGenerator's InlineBox lowering.
            return;
        }
        $height = $geo->height > 0.0 ? $geo->height : $geo->width;
        // CSS Images 3 §5: `object-fit` controls the image's scale
        // within its declared content rect. `fill` (default) stretches;
        // `contain` / `cover` / `none` / `scale-down` preserve aspect.
        // `object-position` selects which part of the box the image
        // anchors to when there's slack — defaults to centre.
        $fit = $this->objectFitKeyword($box);
        $rect = $this->resolveObjectFit($fit, $src, $geo->width, $height);
        $positionValue = $box->style->get('object-position');
        if ($positionValue !== null
            && ($rect['w'] !== $geo->width || $rect['h'] !== $height)
        ) {
            $pos = $this->resolveBackgroundPosition(
                $positionValue,
                $rect['w'],
                $rect['h'],
                $geo->width,
                $height,
            );
            $rect['offsetX'] = $pos['offsetX'];
            $rect['offsetY'] = $pos['offsetY'];
        }
        // PDF y-axis is inverted; the `cm` matrix maps the unit square
        // [0,1]^2 to the box's PDF-space rect.
        $pdfY = $this->pageHeight - $geo->y - $height;
        $stream->saveGraphicsState();
        // Clip to the box rect so `cover` overflow doesn't bleed into
        // sibling boxes.
        $stream->rectangle($geo->x, $pdfY, $geo->width, $height);
        $stream->clip();
        $stream->endPath();
        $stream->concatMatrix(
            $rect['w'],
            0.0,
            0.0,
            $rect['h'],
            $geo->x + $rect['offsetX'],
            $pdfY + ($height - $rect['h'] - $rect['offsetY']),
        );
        $stream->doXObject($name);
        $stream->restoreGraphicsState();
    }

    /**
     * Read the box's cascaded `object-fit` value, normalised to one of
     * `fill` / `contain` / `cover` / `none` / `scale-down`. Unknown
     * keywords fall back to `fill`.
     */
    private function objectFitKeyword(Box $box): string
    {
        $value = $box->style->get('object-fit');
        if ($value instanceof Keyword) {
            $kw = strtolower($value->name);
            if (in_array($kw, ['fill', 'contain', 'cover', 'none', 'scale-down'], true)) {
                return $kw;
            }
        }
        return 'fill';
    }

    /**
     * Compute the painted rect for a replaced element under `object-fit`.
     * Mirrors CSS Images 3 §5 semantics:
     *   - `fill` → stretch to the box.
     *   - `contain` → preserve aspect, fit inside; centred slack.
     *   - `cover` → preserve aspect, fill; clipped overflow.
     *   - `none` → natural size; centred slack.
     *   - `scale-down` → min(`none`, `contain`) — uses natural size when
     *     the image already fits, otherwise behaves like `contain`.
     *
     * @return array{w: float, h: float, offsetX: float, offsetY: float}
     */
    private function resolveObjectFit(
        string $fit,
        string $src,
        float $boxWidth,
        float $boxHeight,
    ): array {
        if ($fit === 'fill') {
            return ['w' => $boxWidth, 'h' => $boxHeight, 'offsetX' => 0.0, 'offsetY' => 0.0];
        }
        $intrinsic = $this->intrinsicSize($src);
        if ($intrinsic === null) {
            return ['w' => $boxWidth, 'h' => $boxHeight, 'offsetX' => 0.0, 'offsetY' => 0.0];
        }
        [$natW, $natH] = $intrinsic;
        if ($fit === 'none') {
            return [
                'w' => (float) $natW,
                'h' => (float) $natH,
                'offsetX' => ($boxWidth - $natW) / 2,
                'offsetY' => ($boxHeight - $natH) / 2,
            ];
        }
        $scaleW = $boxWidth / $natW;
        $scaleH = $boxHeight / $natH;
        if ($fit === 'scale-down') {
            // Use 1.0 (natural) when it already fits; else contain.
            $scale = min(1.0, $scaleW, $scaleH);
        } elseif ($fit === 'cover') {
            $scale = max($scaleW, $scaleH);
        } else {
            // contain
            $scale = min($scaleW, $scaleH);
        }
        $finalW = $natW * $scale;
        $finalH = $natH * $scale;
        return [
            'w' => $finalW,
            'h' => $finalH,
            'offsetX' => ($boxWidth - $finalW) / 2,
            'offsetY' => ($boxHeight - $finalH) / 2,
        ];
    }

    /**
     * Resolve an `<img src>` value to a real path that
     * {@see PdfWriter::addImage} can read. Handles:
     *   - `data:image/{png,jpeg}[;base64],...` → spilled tempfile
     *   - relative paths → joined with `baseDir`, must resolve under it
     *
     * Returns null when the source isn't a Phase-1 supported variant or
     * when path resolution escapes `baseDir`.
     */
    private function resolveImageSrc(string $src): ?string
    {
        if (str_starts_with($src, 'data:')) {
            return $this->materializeDataUrl($src);
        }
        return (new \Phpdftk\Filesystem\ResourceLoader($this->baseDir))
            ->resolveLocalPath($src);
    }

    /**
     * Decode `data:image/{png,jpeg};base64,...` (or the rfc2397 non-base64
     * form) into a tempfile so {@see PdfWriter::addImage} can parse it.
     * Returns null when the URL isn't a Phase-1 supported variant.
     */
    private function materializeDataUrl(string $dataUrl): ?string
    {
        // `data:image/png;base64,iVBORw0K...` — match the MIME + optional
        // `;base64` flag + the payload.
        if (preg_match('~^data:image/(png|jpeg|jpg);(base64,)?(.*)$~s', $dataUrl, $m) !== 1) {
            return null;
        }
        $mime = $m[1] === 'jpg' ? 'jpeg' : $m[1];
        $payload = $m[2] === 'base64,'
            ? base64_decode($m[3], strict: true)
            : urldecode($m[3]);
        if ($payload === false || $payload === '') {
            return null;
        }
        $ext = $mime === 'jpeg' ? 'jpg' : 'png';
        $tempPath = tempnam(sys_get_temp_dir(), 'phpdftk-img-') . '.' . $ext;
        \Phpdftk\Filesystem\LocalFilesystem::writeFile($tempPath, $payload);
        $this->tempImagePaths[] = $tempPath;
        return $tempPath;
    }

    /**
     * Resolve CSS Backgrounds 3 §3.5 `background-clip` to one of
     * `border-box` / `padding-box` / `content-box`. Unknown
     * keywords fall back to the initial `border-box`.
     */
    private function resolveBackgroundClip(Box $box): string
    {
        $value = $box->style->get('background-clip');
        if (!($value instanceof Keyword)) {
            return 'border-box';
        }
        $name = strtolower($value->name);
        if (in_array($name, ['border-box', 'padding-box', 'content-box'], true)) {
            return $name;
        }
        return 'border-box';
    }

    /**
     * Resolve CSS Backgrounds 3 §3.4 `background-origin` to one of
     * `padding-box` (initial) / `border-box` / `content-box`. The
     * origin rect anchors `background-position`'s percentage math.
     */
    private function resolveBackgroundOrigin(Box $box): string
    {
        $value = $box->style->get('background-origin');
        if (!($value instanceof Keyword)) {
            return 'padding-box';
        }
        $name = strtolower($value->name);
        if (in_array($name, ['border-box', 'padding-box', 'content-box'], true)) {
            return $name;
        }
        return 'padding-box';
    }

    /**
     * Compute the (x, top, width, height) rect that
     * `background-origin: <value>` selects on `$box`. `x/top` are in
     * top-down layout space.
     *
     * @return array{x: float, top: float, width: float, height: float}
     */
    private function backgroundOriginRect(Box $box, string $origin): array
    {
        $g = $box->geometry;
        switch ($origin) {
            case 'content-box':
                return [
                    'x' => $g->x,
                    'top' => $g->y,
                    'width' => $g->width,
                    'height' => $g->height,
                ];
            case 'border-box':
                return [
                    'x' => $g->x - $g->paddingLeft - $g->borderLeft,
                    'top' => $g->y - $g->paddingTop - $g->borderTop,
                    'width' => $g->paddingLeft + $g->width + $g->paddingRight
                        + $g->borderLeft + $g->borderRight,
                    'height' => $g->paddingTop + $g->height + $g->paddingBottom
                        + $g->borderTop + $g->borderBottom,
                ];
            default: // 'padding-box'
                return [
                    'x' => $g->x - $g->paddingLeft,
                    'top' => $g->y - $g->paddingTop,
                    'width' => $g->paddingLeft + $g->width + $g->paddingRight,
                    'height' => $g->paddingTop + $g->height + $g->paddingBottom,
                ];
        }
    }

    /**
     * CSS Overflow 3 §3 — return true when this box should clip its
     * descendants on at least one axis. `visible` (initial) → no
     * clip; any of `hidden` / `clip` / `scroll` / `auto` → clip.
     * Per-axis: when `overflow-x` is constraining and `overflow-y`
     * isn't (or vice versa), the clip rect extends to the page on
     * the unconstrained axis so spec-correct one-axis clipping holds.
     */
    private function shouldOverflowClip(Box $box): bool
    {
        return $this->axisClips($box, 'x') || $this->axisClips($box, 'y');
    }

    /**
     * Return true when the given axis (`'x'` or `'y'`) should clip
     * for this box. Checks the axis-specific longhand first, then
     * the `overflow` shorthand.
     */
    private function axisClips(Box $box, string $axis): bool
    {
        foreach (["overflow-$axis", 'overflow'] as $prop) {
            $value = $box->style->get($prop);
            if (!($value instanceof Keyword)) {
                continue;
            }
            $name = strtolower($value->name);
            if ($name === 'visible') {
                return false;
            }
            if ($name === 'hidden' || $name === 'clip' || $name === 'scroll' || $name === 'auto') {
                return true;
            }
        }
        return false;
    }

    /**
     * Emit a clip rect for CSS Overflow 3 §4. Unconstrained axes are
     * widened to the full page so the clip is effectively one-axis;
     * fully-constrained boxes clip to the padding rect on both axes.
     * Caller is responsible for the `saveGraphicsState` /
     * `restoreGraphicsState` envelope.
     */
    private function emitOverflowClipPath(ContentStream $stream, Box $box): void
    {
        $g = $box->geometry;
        $padX = $g->x - $g->paddingLeft;
        $padTop = $g->y - $g->paddingTop;
        $padWidth = $g->paddingLeft + $g->width + $g->paddingRight;
        $padHeight = $g->paddingTop + $g->height + $g->paddingBottom;
        if ($padWidth <= 0.0 || $padHeight <= 0.0) {
            return;
        }
        $clipsX = $this->axisClips($box, 'x');
        $clipsY = $this->axisClips($box, 'y');
        $rectX = $clipsX ? $padX : 0.0;
        $rectWidth = $clipsX ? $padWidth : $this->pageWidth;
        $rectTop = $clipsY ? $padTop : 0.0;
        $rectHeight = $clipsY ? $padHeight : $this->pageHeight;
        $pdfY = $this->pageHeight - $rectTop - $rectHeight;
        $stream->rectangle($rectX, $pdfY, $rectWidth, $rectHeight);
        $stream->clip();
        $stream->endPath();
    }

    private function isVisibilityHidden(Box $box): bool
    {
        $value = $box->style->get('visibility');
        return $value instanceof Keyword
            && in_array(strtolower($value->name), ['hidden', 'collapse'], true);
    }

    /**
     * Paint CSS Backgrounds 3 §6 `box-shadow`. Phase-1 implementation:
     * draws a hard-edged shadow rect (no blur — blur needs Filter Effects 1
     * which is Phase 2). Honours `<offset-x>`, `<offset-y>`, optional
     * `<spread-radius>`, and `<color>` (defaults to cascaded `color`).
     * Inset shadows are not yet emitted (would clip inward).
     *
     * Multi-shadow comma lists are read; each shadow paints in reverse
     * order so the first listed sits on top — matching CSS stacking.
     */
    private function paintBoxShadow(Box $box, ContentStream $stream, bool $insetOnly): void
    {
        $value = $box->style->get('box-shadow');
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'none')
        ) {
            return;
        }
        $shadows = $this->collectShadowLayers($value);
        if ($shadows === []) {
            return;
        }
        $defaultColor = $box->style->get('color');
        $textColor = $defaultColor instanceof Color ? $defaultColor : new Color(0, 0, 0, 1);
        $geo = $box->geometry;

        // Paint last shadow first so earlier-listed shadows sit on top.
        foreach (array_reverse($shadows) as $shadow) {
            if ($shadow['inset'] !== $insetOnly) {
                continue;
            }
            $color = $shadow['color'] ?? $textColor;
            $spread = $shadow['spread'];
            if ($shadow['inset']) {
                $this->paintInsetShadow($geo, $shadow, $color, $stream);
                continue;
            }
            $x = $geo->x - $geo->paddingLeft - $geo->borderLeft + $shadow['offsetX'] - $spread;
            $top = $geo->y - $geo->paddingTop - $geo->borderTop + $shadow['offsetY'] - $spread;
            $width = $geo->paddingLeft + $geo->width + $geo->paddingRight
                + $geo->borderLeft + $geo->borderRight + 2 * $spread;
            $height = $geo->paddingTop + $geo->height + $geo->paddingBottom
                + $geo->borderTop + $geo->borderBottom + 2 * $spread;
            $this->emitRect($stream, $x, $top, $width, $height, fill: $color);
        }
    }

    /**
     * Paint an inset box-shadow per CSS Backgrounds 3 §6. The shadow
     * paints INSIDE the padding-box edge (not outside the border-box
     * like the default outset case). Offsets are inverted in effect:
     * a positive `offsetX` makes the shadow visible at the *left*
     * edge of the inside (the shadow "comes from" the +X direction).
     * Positive `spread` makes the visible inner shadow band thicker
     * by shrinking the unshaded inner rect.
     *
     * Implementation: paint the padding-box outer rect plus the
     * computed inner rect as two subpaths, then fill with the
     * even-odd rule (`f*`) so PDF leaves the inner rect transparent
     * and fills only the frame between them with the shadow colour.
     *
     * @param array{offsetX: float, offsetY: float, blur: float, spread: float, color: ?Color, inset: bool} $shadow
     */
    private function paintInsetShadow(\Phpdftk\HtmlToPdf\Layout\BoxGeometry $geo, array $shadow, Color $color, ContentStream $stream): void
    {
        // Padding-box edge (one step inside the border edge).
        $padX = $geo->x - $geo->paddingLeft;
        $padTop = $geo->y - $geo->paddingTop;
        $padWidth = $geo->paddingLeft + $geo->width + $geo->paddingRight;
        $padHeight = $geo->paddingTop + $geo->height + $geo->paddingBottom;
        if ($padWidth <= 0.0 || $padHeight <= 0.0) {
            return;
        }
        $spread = $shadow['spread'];
        // Inner unshaded rect — inset from the padding-box by the
        // offset on the corresponding side, then further by spread on
        // every side. CSS 2 §6: a positive +X offset moves the shadow
        // toward +X (visible at the OPPOSITE edge — the left), so the
        // inner rect's left edge advances by offsetX.
        $innerX = $padX + max(0.0, $shadow['offsetX']) + $spread;
        $innerTop = $padTop + max(0.0, $shadow['offsetY']) + $spread;
        $innerRight = $padX + $padWidth + min(0.0, $shadow['offsetX']) - $spread;
        $innerBottom = $padTop + $padHeight + min(0.0, $shadow['offsetY']) - $spread;
        $innerWidth = $innerRight - $innerX;
        $innerHeight = $innerBottom - $innerTop;
        if ($innerWidth <= 0.0 || $innerHeight <= 0.0) {
            // Spread+offset consumes the whole padding box — fill it
            // solid with the shadow colour.
            $this->emitRect($stream, $padX, $padTop, $padWidth, $padHeight, fill: $color);
            return;
        }
        // PDF Y axis is inverted vs layout. Flip both rects.
        $padPdfY = $this->pageHeight - $padTop - $padHeight;
        $innerPdfY = $this->pageHeight - $innerTop - $innerHeight;
        $stream->saveGraphicsState();
        $stream->setFillColorRGB($color->r, $color->g, $color->b);
        $stream->rectangle($padX, $padPdfY, $padWidth, $padHeight);
        $stream->rectangle($innerX, $innerPdfY, $innerWidth, $innerHeight);
        $stream->fillEvenOdd();
        $stream->restoreGraphicsState();
    }

    /**
     * Parse the value list(s) into per-shadow layer arrays.
     *
     * @return list<array{offsetX: float, offsetY: float, blur: float, spread: float, color: ?Color, inset: bool}>
     */
    private function collectShadowLayers(\Phpdftk\Css\Value\Value $value): array
    {
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Comma
        ) {
            $layers = [];
            foreach ($value->values as $item) {
                $parsed = $this->parseShadowLayer($item);
                if ($parsed !== null) {
                    $layers[] = $parsed;
                }
            }
            return $layers;
        }
        $single = $this->parseShadowLayer($value);
        return $single === null ? [] : [$single];
    }

    /**
     * @return array{offsetX: float, offsetY: float, blur: float, spread: float, color: ?Color, inset: bool}|null
     */
    private function parseShadowLayer(\Phpdftk\Css\Value\Value $value): ?array
    {
        $components = $value instanceof \Phpdftk\Css\Value\ValueList
            ? $value->values
            : [$value];

        $inset = false;
        $color = null;
        $lengths = [];
        foreach ($components as $c) {
            if ($c instanceof Keyword && strtolower($c->name) === 'inset') {
                $inset = true;
                continue;
            }
            if ($c instanceof Color) {
                $color = $c;
                continue;
            }
            if ($c instanceof \Phpdftk\Css\Value\Length) {
                $lengths[] = $c->value;
                continue;
            }
            // CSS Values 4 §6.2: a unitless `0` is a valid zero-length
            // wherever a length is expected. Accept Integer/Number
            // values as zero (and treat non-zero numerics as 0 — the
            // grammar requires a unit otherwise).
            if ($c instanceof \Phpdftk\Css\Value\Integer
                || $c instanceof \Phpdftk\Css\Value\Number
            ) {
                $lengths[] = (float) $c->value;
            }
        }
        if (count($lengths) < 2) {
            return null;
        }
        return [
            'offsetX' => $lengths[0],
            'offsetY' => $lengths[1],
            'blur' => $lengths[2] ?? 0.0,
            'spread' => $lengths[3] ?? 0.0,
            'color' => $color,
            'inset' => $inset,
        ];
    }

    /**
     * Resolve the box's cascaded `opacity`. Returns the page-level
     * `ExtGState` resource name to invoke for partial opacity, or null
     * when opacity is full (1.0) or the painter wasn't given a Page
     * reference. Opacity affects this box plus every descendant since
     * the `gs` operator persists until the matching `Q`.
     */
    private function resolveOpacityGsName(Box $box): ?string
    {
        if ($this->page === null) {
            return null;
        }
        $value = $box->style->get('opacity');
        $alpha = match (true) {
            $value instanceof \Phpdftk\Css\Value\Number => $value->value,
            $value instanceof \Phpdftk\Css\Value\Integer => (float) $value->value,
            default => 1.0,
        };
        $alpha = max(0.0, min(1.0, $alpha));
        if ($alpha >= 0.999) {
            return null;
        }
        return $this->page->ensureOpacityState($alpha, $alpha);
    }

    /**
     * Paint a CSS Lists 3 list marker (the `::marker` pseudo) for boxes
     * with `display: list-item`, honouring `list-style-type` for the
     * three geometric markers (`disc` / `circle` / `square`). Counter-
     * style markers (`decimal`, `lower-alpha`, etc.) require font
     * rendering of the running counter and live in Phase 2; they fall
     * through to a `disc` (filled circle) here. Marker colour follows
     * the cascaded `color`.
     */
    private function paintListMarker(Box $box, ContentStream $stream): void
    {
        $display = $box->style->get('display');
        if (!$display instanceof Keyword || strtolower($display->name) !== 'list-item') {
            return;
        }
        $typeValue = $box->style->get('list-style-type');
        $type = $typeValue instanceof Keyword ? strtolower($typeValue->name) : 'disc';
        if ($type === 'none') {
            return;
        }

        $color = $box->style->get('color');
        $markerColor = $color instanceof Color ? $color : new Color(0, 0, 0, 1);
        $fontSize = $this->dominantFontSize($box);

        // Counter-style markers — formatted text, requires a registered font.
        $counterText = $this->formatCounterMarker($box, $type);
        if ($counterText !== null && $this->defaultFont !== null) {
            $this->paintCounterMarker($box, $stream, $markerColor, $fontSize, $counterText);
            return;
        }

        $size = max(2.0, $fontSize / 3.0);
        $x = $box->geometry->x - max(6.0, $fontSize * 0.5);
        $layoutY = $box->geometry->y + $fontSize * 0.35;
        $pdfY = $this->pageHeight - $layoutY - $size;

        $stream->saveGraphicsState();
        $stream->setFillColorRGB($markerColor->r, $markerColor->g, $markerColor->b);
        $stream->setStrokeColorRGB($markerColor->r, $markerColor->g, $markerColor->b);
        match ($type) {
            'circle' => $this->paintMarkerCircle($stream, $x, $pdfY, $size, fill: false),
            'square' => $this->paintMarkerSquare($stream, $x, $pdfY, $size),
            default => $this->paintMarkerCircle($stream, $x, $pdfY, $size, fill: true),
        };
        $stream->restoreGraphicsState();
    }

    /**
     * Format the marker text for counter-style list-style-types. Returns
     * null for geometric / unknown / `none` types (caller paints the
     * geometric stand-in or skips).
     */
    private function formatCounterMarker(Box $box, string $type): ?string
    {
        $index = $this->listItemIndex($box);
        if ($index < 1) {
            return null;
        }
        $supported = [
            'decimal', 'decimal-leading-zero',
            'lower-alpha', 'lower-latin', 'upper-alpha', 'upper-latin',
            'lower-roman', 'upper-roman',
        ];
        if (!in_array(strtolower($type), $supported, true)) {
            return null;
        }
        return \Phpdftk\HtmlToPdf\Layout\CounterFormat::format($index, $type) . '.';
    }

    /**
     * Compute the 1-based index of `$box` among its `<li>` siblings by
     * walking the originating Element's previousSibling chain. Returns
     * 0 when `$box` isn't bound to a DOM element (e.g. anonymous).
     */
    private function listItemIndex(Box $box): int
    {
        if ($box->element === null) {
            return 0;
        }
        $thisLi = $box->element;
        // HTML 5 §4.4.5.2: `<li value="N">` sets the explicit ordinal — and
        // also resets the count for following siblings. Walk left-to-right
        // from the parent's first child until we hit `$thisLi`; bumping on
        // each `<li>` and snapping to `value` whenever a sibling provides
        // it.
        $parent = $thisLi->parentNode;
        if (!$parent instanceof \Phpdftk\Html\Dom\Element) {
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
                if ($n instanceof \Phpdftk\Html\Dom\Element
                    && strtolower($n->localName) === 'li'
                ) {
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
            if (!($n instanceof \Phpdftk\Html\Dom\Element)
                || strtolower($n->localName) !== 'li'
            ) {
                continue;
            }
            $raw = $n->getAttribute('value');
            if ($raw !== null && preg_match('/^-?\d+$/', trim($raw)) === 1) {
                $count = (int) trim($raw);
            } else {
                $count += $step;
            }
            if ($n === $thisLi) {
                return $count;
            }
        }
        return $start;
    }

    /**
     * Paint a counter-style marker — shapes the text against the
     * registered font and emits a Tj at the marker position.
     */
    private function paintCounterMarker(
        Box $box,
        ContentStream $stream,
        Color $color,
        float $fontSize,
        string $text,
    ): void {
        $font = $this->defaultFont;
        if (!$font instanceof WriterFont) {
            return;
        }
        $otd = $font->getParsedData();
        if (!$otd instanceof \Phpdftk\FontParser\OpenTypeData) {
            return;
        }
        $shaper = new \Phpdftk\Text\Shaper();
        $shapedRun = $shaper->shapeRun(
            $text,
            new \Phpdftk\Text\ShapingContext($otd, $fontSize),
        );
        if ($shapedRun->glyphs === []) {
            return;
        }
        $ascent = ($otd->ascent / max(1, $otd->unitsPerEm)) * $fontSize;
        // Right-align the marker so it sits just to the left of the box content.
        $width = $shapedRun->totalAdvance;
        $x = $box->geometry->x - $width - max(2.0, $fontSize * 0.2);
        $baselineY = $box->geometry->y + $ascent;
        $pdfY = $this->pageHeight - $baselineY;

        $hex = '';
        $gidMap = $font->getOldToNewGidMap();
        foreach ($shapedRun->glyphs as $g) {
            $hex .= sprintf('%04X', $gidMap[$g->glyphId] ?? $g->glyphId);
        }

        $stream->saveGraphicsState();
        $stream->setFillColorRGB($color->r, $color->g, $color->b);
        $stream->beginText();
        $stream->setFont($font, $fontSize);
        $stream->setTextMatrix(1, 0, 0, 1, $x, $pdfY);
        $stream->showTextHex($hex);
        $stream->endText();
        $stream->restoreGraphicsState();
    }

    private function paintMarkerSquare(ContentStream $stream, float $x, float $y, float $size): void
    {
        $stream->rectangle($x, $y, $size, $size);
        $stream->fill();
    }

    /**
     * Approximate a circle inside the bounding box (x, y, size, size) with
     * four cubic Bézier curves. The classic offset constant for unit-radius
     * approximation is `0.5522847498` — keeps the curve within ≈ 0.027% of
     * the true circle, more than enough at marker scale.
     */
    private function paintMarkerCircle(
        ContentStream $stream,
        float $x,
        float $y,
        float $size,
        bool $fill,
    ): void {
        $r = $size / 2.0;
        $cx = $x + $r;
        $cy = $y + $r;
        $k = $r * 0.5522847498307933;
        $stream->moveTo($cx + $r, $cy);
        $stream->curveTo($cx + $r, $cy + $k, $cx + $k, $cy + $r, $cx, $cy + $r);
        $stream->curveTo($cx - $k, $cy + $r, $cx - $r, $cy + $k, $cx - $r, $cy);
        $stream->curveTo($cx - $r, $cy - $k, $cx - $k, $cy - $r, $cx, $cy - $r);
        $stream->curveTo($cx + $k, $cy - $r, $cx + $r, $cy - $k, $cx + $r, $cy);
        $stream->closePath();
        if ($fill) {
            $stream->fill();
        } else {
            $stream->setLineWidth(max(0.4, $r / 6.0));
            $stream->stroke();
        }
    }

    private function dominantFontSize(Box $box): float
    {
        $value = $box->style->get('font-size');
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        return 12.0;
    }

    /**
     * Emit glyphs for every {@see InlineFragment} in this box's line boxes.
     * Requires a {@see RegisteredFont} on the painter — without one, text
     * painting is a no-op so block + border content still renders.
     *
     * Coordinates: layout space is top-down; PDF text positioning is
     * baseline-relative in bottom-up space. The baseline sits at
     * `lineBox.y + ascent` where ascent = (font.ascent / unitsPerEm) ×
     * fontSize. The painter converts to PDF Y by subtracting from
     * `$this->pageHeight`.
     */
    private function paintLineBoxes(Box $box, ContentStream $stream): void
    {
        if ($box->lineBoxes === [] || $this->defaultFont === null) {
            return;
        }
        $color = $box->style->get('color');
        $textColor = $color instanceof Color ? $color : new Color(0, 0, 0, 1);

        // Inline backgrounds (`<mark>` and friends) paint as a strip behind
        // each fragment that carries a `backgroundColor`. Goes before the
        // text + shadow passes so the glyphs sit on top.
        foreach ($box->lineBoxes as $line) {
            $this->paintInlineBackgrounds($box, $line, $stream);
        }

        $shadows = $this->collectTextShadowLayers($box, $textColor);
        foreach ($box->lineBoxes as $line) {
            // Paint shadow layers behind the real text. CSS Text Decoration 4
            // §6 says the first listed shadow is painted on top, so we
            // reverse the list for the back-to-front emission order.
            foreach (array_reverse($shadows) as $shadow) {
                $this->paintLine(
                    $box,
                    $line,
                    $stream,
                    $shadow['color'],
                    $shadow['offsetX'],
                    $shadow['offsetY'],
                );
            }
            $this->paintLine($box, $line, $stream, $textColor);
            $this->paintTextDecorations($box, $line, $stream, $textColor);
        }
    }

    /**
     * Paint per-fragment inline background rectangles. Each fragment that
     * carries a `backgroundColor` (propagated from an inline element like
     * `<mark>` whose cascade sets `background-color`) gets a filled rect
     * spanning the fragment's width and the line's height. Adjacent
     * fragments with the same colour are merged so we emit one wider rect
     * per run of same-colour fragments — cheaper output without sub-pixel
     * gaps from neighbouring fills.
     */
    private function paintInlineBackgrounds(Box $box, LineBox $line, ContentStream $stream): void
    {
        if ($line->fragments === []) {
            return;
        }
        // Coalesce contiguous fragments that share a background colour into
        // single rects so we emit cheaper output without sub-pixel gaps.
        /** @var list<array{x: float, width: float, color: Color}> $runs */
        $runs = [];
        foreach ($line->fragments as $fragment) {
            $bg = $fragment->backgroundColor;
            if ($bg === null) {
                continue;
            }
            $last = $runs === [] ? null : $runs[array_key_last($runs)];
            $sameAsLast = $last !== null
                && abs($last['x'] + $last['width'] - $fragment->x) < 0.001
                && $last['color']->r === $bg->r
                && $last['color']->g === $bg->g
                && $last['color']->b === $bg->b;
            if ($sameAsLast) {
                $runs[array_key_last($runs)]['width'] = ($fragment->x + $fragment->width) - $last['x'];
            } else {
                $runs[] = [
                    'x' => $fragment->x,
                    'width' => $fragment->width,
                    'color' => $bg,
                ];
            }
        }
        if ($runs === []) {
            return;
        }
        $pdfY = $this->pageHeight - ($box->geometry->y + $line->y + $line->height);
        foreach ($runs as $run) {
            $stream->saveGraphicsState();
            $stream->setFillColorRGB($run['color']->r, $run['color']->g, $run['color']->b);
            $stream->rectangle($box->geometry->x + $run['x'], $pdfY, $run['width'], $line->height);
            $stream->fill();
            $stream->restoreGraphicsState();
        }
    }

    /**
     * Parse the cascaded `text-shadow` value into layer entries. Returns
     * an empty array when text-shadow is `none` or absent.
     *
     * @return list<array{offsetX: float, offsetY: float, color: Color}>
     */
    private function collectTextShadowLayers(Box $box, Color $fallback): array
    {
        $value = $box->style->get('text-shadow');
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'none')
        ) {
            return [];
        }
        $layers = [];
        $items = $value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Comma
            ? $value->values
            : [$value];
        foreach ($items as $item) {
            $components = $item instanceof \Phpdftk\Css\Value\ValueList ? $item->values : [$item];
            $lengths = [];
            $color = null;
            foreach ($components as $c) {
                if ($c instanceof \Phpdftk\Css\Value\Length) {
                    $lengths[] = $c->value;
                } elseif ($c instanceof Color) {
                    $color = $c;
                }
            }
            if (count($lengths) < 2) {
                continue;
            }
            $layers[] = [
                'offsetX' => $lengths[0],
                'offsetY' => $lengths[1],
                'color' => $color ?? $fallback,
            ];
        }
        return $layers;
    }

    private function paintLine(
        Box $box,
        LineBox $line,
        ContentStream $stream,
        Color $color,
        float $offsetX = 0.0,
        float $offsetY = 0.0,
    ): void {
        if ($line->fragments === []) {
            return;
        }
        $stream->saveGraphicsState();
        $stream->setFillColorRGB($color->r, $color->g, $color->b);
        $stream->beginText();
        $activeColor = $color;
        foreach ($line->fragments as $fragment) {
            // Inline-level `color` override: when the fragment carries its
            // own colour (typically `<a>` inheriting blue from the UA
            // stylesheet inside a black `<p>`), reseat the fill colour.
            $fragColor = $fragment->textColor ?? $color;
            if ($fragColor !== $activeColor) {
                $stream->setFillColorRGB($fragColor->r, $fragColor->g, $fragColor->b);
                $activeColor = $fragColor;
            }
            $this->paintFragment($box, $line, $fragment, $stream, $fragColor, $offsetX, $offsetY);
        }
        // Reset rendering mode in case the last fragment left it set.
        $stream->setTextRenderingMode(0);
        $stream->endText();
        $stream->restoreGraphicsState();
    }

    /**
     * Paint text-decoration lines (underline / overline / line-through) for
     * every fragment whose parent style sets `text-decoration-line` to a
     * non-`none` value.
     *
     * Position approximation per CSS Text Decoration 3 §3:
     *  - underline:   baseline + 0.15 × fontSize
     *  - overline:    baseline − ascent (top of em box)
     *  - line-through: baseline − 0.3 × fontSize (~x-height middle)
     * Thickness: `fontSize / 14`.
     *
     * The fallback approximations stand in until `phpdftk/font-parser`
     * exposes the OS/2 sTypoUnderlinePosition / underlineThickness fields.
     */
    private function paintTextDecorations(Box $box, LineBox $line, ContentStream $stream, Color $color): void
    {
        $blockLines = $this->textDecorationLines($box);
        $decoColor = $this->textDecorationColor($box, $color);
        foreach ($line->fragments as $fragment) {
            // CSS Text Decoration 4 §2: a fragment's effective decoration
            // is the union of inherited (from inline ancestors) + block-
            // level lines. Block-level wins for color since the value
            // doesn't inherit through inlines.
            $lines = array_values(array_unique(array_merge($blockLines, $fragment->decorationLines)));
            if ($lines === []) {
                continue;
            }
            $shapedRun = $fragment->shapedRun;
            if ($shapedRun->glyphs === [] && $fragment->width <= 0.0) {
                continue;
            }
            $fontSize = $shapedRun->fontSizePt;
            $font = $shapedRun->font;
            $unitsPerEm = max(1, $font->unitsPerEm);
            $ascent = ($font->ascent / $unitsPerEm) * $fontSize;
            // Real OS/2 underline metrics when available; fall back to the
            // 1G.3 approximation otherwise.
            $underlineOffset = $font->underlinePosition !== null
                ? -($font->underlinePosition / $unitsPerEm) * $fontSize
                : 0.15 * $fontSize;
            $thickness = $font->underlineThickness !== null
                ? max(0.5, ($font->underlineThickness / $unitsPerEm) * $fontSize)
                : max(0.5, $fontSize / 14.0);
            // CSS Text Decoration 4 §4 — `text-decoration-thickness`
            // explicit Length / Percentage overrides the font metric.
            // `auto` defers to the metric above.
            $explicitThickness = $this->resolveDecorationThickness($box, $fontSize);
            if ($explicitThickness !== null) {
                $thickness = max(0.5, $explicitThickness);
            }
            // `text-underline-offset` shifts the underline ONLY (not
            // overline or line-through). Positive values push the line
            // further below the baseline.
            $explicitUnderlineOffset = $this->resolveUnderlineOffset($box, $fontSize);
            $x = $box->geometry->x + $fragment->x;
            $width = $fragment->width;
            $baselineY = $box->geometry->y + $line->y + $ascent;
            $style = $this->textDecorationStyle($box);
            // Per CSS Text Decoration 4 §3, the decoration colour follows
            // the *originating* element's `text-decoration-color` (when
            // explicitly set) — fall back to the fragment's `color` (so an
            // inline `<a>` with cascaded `color: blue` paints a blue
            // underline) and finally to the block-level value resolved at
            // the outer paint context.
            $effectiveColor = $fragment->decorationColor
                ?? $fragment->textColor
                ?? $decoColor;
            foreach ($lines as $lineKind) {
                $offsetY = match ($lineKind) {
                    'underline' => $underlineOffset + ($explicitUnderlineOffset ?? 0.0),
                    'overline' => -$ascent,
                    'line-through' => -0.3 * $fontSize,
                    default => 0.0,
                };
                $layoutY = $baselineY + $offsetY;
                $pdfY = $this->pageHeight - $layoutY - $thickness;
                $this->emitDecorationStyled($stream, $x, $pdfY, $width, $thickness, $effectiveColor, $style);
            }
        }
    }

    /**
     * Emit one text-decoration line in the given style. `solid` is one
     * rect; `double` is two parallel rects with a small gap; `dashed`
     * and `dotted` emit a series of segment rects; `wavy` strokes a
     * cubic-Bezier-approximated sine wave at the decoration position.
     */
    private function emitDecorationStyled(
        ContentStream $stream,
        float $x,
        float $pdfY,
        float $width,
        float $thickness,
        Color $color,
        string $style,
    ): void {
        if ($style === 'wavy') {
            $this->emitWavyDecoration($stream, $x, $pdfY, $width, $thickness, $color);
            return;
        }
        $stream->saveGraphicsState();
        $stream->setFillColorRGB($color->r, $color->g, $color->b);
        switch ($style) {
            case 'double':
                $gap = max(0.5, $thickness);
                $stream->rectangle($x, $pdfY, $width, $thickness);
                $stream->rectangle($x, $pdfY - $gap - $thickness, $width, $thickness);
                $stream->fill();
                break;
            case 'dashed':
                $segment = max(2.0, $thickness * 3);
                $gap = max(1.5, $thickness * 2);
                for ($cx = $x; $cx < $x + $width; $cx += $segment + $gap) {
                    $w = min($segment, $x + $width - $cx);
                    $stream->rectangle($cx, $pdfY, $w, $thickness);
                }
                $stream->fill();
                break;
            case 'dotted':
                $dotSize = max(1.0, $thickness);
                $gap = $dotSize * 1.2;
                for ($cx = $x; $cx < $x + $width; $cx += $dotSize + $gap) {
                    $w = min($dotSize, $x + $width - $cx);
                    $stream->rectangle($cx, $pdfY, $w, $thickness);
                }
                $stream->fill();
                break;
            default: // solid
                $stream->rectangle($x, $pdfY, $width, $thickness);
                $stream->fill();
        }
        $stream->restoreGraphicsState();
    }

    /**
     * Stroke a sine-wave-shaped text decoration line, approximated by
     * cubic Bezier curves. Two Beziers per period (one half-cycle up,
     * one half-cycle down). The wave's period is `thickness × 6` and
     * amplitude is `thickness × 0.7` — these tune the look to match
     * the wavy spell-check underlines that browsers render.
     */
    private function emitWavyDecoration(
        ContentStream $stream,
        float $x,
        float $pdfY,
        float $width,
        float $thickness,
        Color $color,
    ): void {
        if ($width <= 0.0 || $thickness <= 0.0) {
            return;
        }
        $period = max(4.0, $thickness * 6.0);
        $amp = max(1.0, $thickness * 0.7);
        $strokeWidth = max(0.5, $thickness * 0.7);
        $stream->saveGraphicsState();
        $stream->setStrokeColorRGB($color->r, $color->g, $color->b);
        $stream->setLineWidth($strokeWidth);
        // Centerline of the wave sits at $pdfY + thickness/2 so the
        // visible band stays within the decoration's allocated band.
        $centerY = $pdfY + $thickness / 2.0;
        $stream->moveTo($x, $centerY);
        $halfPeriod = $period / 2.0;
        // Bezier control offset for a sine half-cycle (well-known
        // approximation: control points at 1/3 and 2/3 of the half).
        $cx1Offset = $halfPeriod / 3.0;
        $cx2Offset = ($halfPeriod * 2.0) / 3.0;
        $end = $x + $width;
        $curX = $x;
        $up = true;
        while ($curX < $end) {
            $segmentEnd = min($curX + $halfPeriod, $end);
            $controlY = $up ? $centerY + $amp : $centerY - $amp;
            $stream->curveTo(
                $curX + $cx1Offset,
                $controlY,
                $curX + $cx2Offset,
                $controlY,
                $segmentEnd,
                $centerY,
            );
            $curX = $segmentEnd;
            $up = !$up;
        }
        $stream->stroke();
        $stream->restoreGraphicsState();
    }

    /**
     * Resolve CSS Text Decoration 4 §4 `text-decoration-thickness`.
     * Returns the resolved pixel value when an explicit Length or
     * Percentage is set (percentage is relative to the font size per
     * CSS UI 4 §6); returns null when the value is `auto` so the font
     * metric stays in effect.
     */
    private function resolveDecorationThickness(Box $box, float $fontSize): ?float
    {
        $value = $box->style->get('text-decoration-thickness');
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $value->value / 100.0 * $fontSize;
        }
        return null;
    }

    /**
     * Resolve CSS Text Decoration 4 §4.2 `text-underline-offset`.
     * Positive values push the underline further below the baseline;
     * `auto` (null) defers to the font-metric default.
     */
    private function resolveUnderlineOffset(Box $box, float $fontSize): ?float
    {
        $value = $box->style->get('text-underline-offset');
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $value->value / 100.0 * $fontSize;
        }
        return null;
    }

    private function textDecorationStyle(Box $box): string
    {
        $value = $box->style->get('text-decoration-style');
        if ($value instanceof Keyword) {
            $name = strtolower($value->name);
            if (in_array($name, ['solid', 'double', 'dashed', 'dotted', 'wavy'], true)) {
                return $name;
            }
        }
        return 'solid';
    }

    /** @return list<string> */
    private function textDecorationLines(Box $box): array
    {
        $value = $box->style->get('text-decoration-line');
        if ($value === null) {
            return [];
        }
        $items = $value instanceof \Phpdftk\Css\Value\ValueList ? $value->values : [$value];
        $out = [];
        foreach ($items as $item) {
            if (!$item instanceof Keyword) {
                continue;
            }
            $lower = strtolower($item->name);
            if ($lower === 'none' || $lower === 'blink') {
                continue;
            }
            if (in_array($lower, ['underline', 'overline', 'line-through'], true)) {
                $out[] = $lower;
            }
        }
        return $out;
    }

    private function textDecorationColor(Box $box, Color $fallback): Color
    {
        $value = $box->style->get('text-decoration-color');
        return $value instanceof Color ? $value : $fallback;
    }

    private function paintFragment(
        Box $box,
        LineBox $line,
        InlineFragment $fragment,
        ContentStream $stream,
        Color $color,
        float $offsetX = 0.0,
        float $offsetY = 0.0,
    ): void {
        $shapedRun = $fragment->shapedRun;
        if ($shapedRun->glyphs === []) {
            return;
        }
        $font = $shapedRun->font;
        $ascent = ($font->ascent / max(1, $font->unitsPerEm)) * $shapedRun->fontSizePt;
        $x = $box->geometry->x + $fragment->x + $offsetX;
        // CSS Inline 3 §4.5 vertical-align — `baselineShift` is negative
        // for `super`, positive for `sub`. Add it in layout-Y space, then
        // flip to PDF-Y.
        $baselineY = $box->geometry->y + $line->y + $ascent + $offsetY + $fragment->baselineShift;
        $pdfY = $this->pageHeight - $baselineY;

        // Pick the RegisteredFont matching this fragment's shaped font.
        // The map is keyed by OpenType postScriptName; the shaping context
        // chose the font, so this lookup just hands the painter the right
        // PDF Tf resource. Falls back to defaultFont when the fragment's
        // font wasn't registered (e.g., the resolver returned defaultFont
        // and there's no alt map).
        $registered = $this->registeredFonts[$font->postScriptName] ?? $this->defaultFont;
        $stream->setFont($registered ?? $font->postScriptName, $shapedRun->fontSizePt);
        // Fake-italic via a 12° skew in the Tm `c` slot (≈ tan(12°) = 0.213).
        // CSS Fonts 4 §6.4.1 lets browsers synthesise oblique from regular
        // when no real italic face is registered; this is the same trick.
        $skew = $fragment->isItalic ? 0.213 : 0.0;
        // Tm reseats the text matrix at each fragment's left baseline, which
        // is simpler than tracking incremental Td offsets between fragments.
        $stream->setTextMatrix(1, 0, $skew, 1, $x, $pdfY);
        // Fake-bold via text rendering mode 2 (fill + stroke). Stroke
        // contributes ≈ fontSize × 0.04 of extra thickness — visually close
        // to the design-weight increment for bold. Match the stroke color
        // to the cascaded fill color so the bold outline doesn't bleed in a
        // different hue. Always re-emit the Tr so a non-bold fragment that
        // follows a bold one resets to fill-only.
        if ($fragment->isBold) {
            $stream->setStrokeColorRGB($color->r, $color->g, $color->b);
            $stream->setLineWidth($shapedRun->fontSizePt * 0.04);
            $stream->setTextRenderingMode(2);
        } else {
            $stream->setTextRenderingMode(0);
        }

        // Per-font GID translation: CFF subsetting renumbers glyphs in the
        // embedded font, so the shaper's full-font GIDs must be mapped to
        // the subset GIDs before emission. Each registered font has its
        // own gid map.
        $gidMap = $registered instanceof WriterFont
            ? $registered->getOldToNewGidMap()
            : [];
        $unitsPerEm = max(1, $font->unitsPerEm);
        $fontSize = $shapedRun->fontSizePt;

        // Build a TJ array if the shaper's advances diverge from the font's
        // natural hmtx widths — that gap is the kern adjustment to encode.
        // Otherwise emit a plain Tj for the whole run.
        $items = [];
        $hex = '';
        $hasKern = false;
        foreach ($shapedRun->glyphs as $g) {
            $emitted = $gidMap[$g->glyphId] ?? $g->glyphId;
            $hex .= sprintf('%04X', $emitted);

            $natural = ($font->glyphWidths[$g->glyphId] ?? 0) / $unitsPerEm * $fontSize;
            $delta = $natural - $g->advanceX; // positive = shaper pulled the glyph in (kern)
            $kern = $delta * 1000.0 / $fontSize;
            if (abs($kern) >= 0.5) {
                $items[] = $hex;
                $items[] = $this->snapKern($kern);
                $hex = '';
                $hasKern = true;
            }
        }
        if ($hex !== '') {
            $items[] = $hex;
        }

        if ($hasKern) {
            $stream->showTextArrayHex($items);
        } else {
            $stream->showTextHex(implode('', array_filter($items, 'is_string')));
        }

        // Inline `<a href>` — record the fragment's rect for /Link emission.
        // We only collect on the "real text" pass (offsetX / offsetY == 0)
        // so multi-layer text-shadow doesn't multiply the link count.
        if ($fragment->href !== null && $offsetX === 0.0 && $offsetY === 0.0) {
            $descent = abs($font->descent) / max(1, $unitsPerEm) * $fontSize;
            $this->collectedLinks[] = [
                'href' => $fragment->href,
                'llx' => $x,
                'lly' => $pdfY - $descent,
                'urx' => $x + $fragment->width,
                'ury' => $pdfY + $ascent,
                'title' => $fragment->linkTitle,
            ];
        }
    }

    /**
     * Block-level `<a href>` — emit a single link rect covering the box's
     * border box. Inline `<a>` is handled inside {@see paintFragment()}.
     */
    private function collectBlockLinkRect(Box $box): void
    {
        if ($box->element === null
            || strtolower($box->element->localName) !== 'a'
        ) {
            return;
        }
        $href = $box->element->getAttribute('href');
        if ($href === null || $href === '') {
            return;
        }
        // Inline `<a>` already produces per-fragment rects via paintFragment;
        // skip when there's nothing to do at the block level.
        if (!($box instanceof \Phpdftk\HtmlToPdf\Box\BlockBox)
            && !($box instanceof \Phpdftk\HtmlToPdf\Box\AnonymousBlockBox)
            && !($box instanceof \Phpdftk\HtmlToPdf\Box\AtomicInlineBox)
        ) {
            return;
        }
        $g = $box->geometry;
        if ($g->width <= 0.0 || $g->outerHeight() <= 0.0) {
            return;
        }
        $llx = $g->x;
        $urx = $g->x + $g->width;
        $ury = $this->pageHeight - $g->y;
        $lly = $this->pageHeight - ($g->y + $g->outerHeight());
        $this->collectedLinks[] = [
            'href' => $href,
            'llx' => $llx,
            'lly' => $lly,
            'urx' => $urx,
            'ury' => $ury,
            'title' => $box->element->getAttribute('title'),
        ];
    }

    /**
     * Round to the nearest 0.1 unit so the emitted PDF stays compact.
     * PDF readers don't visually distinguish sub-tenth-unit kerns.
     */
    private function snapKern(float $kern): float|int
    {
        $rounded = round($kern, 1);
        return $rounded == (int) $rounded ? (int) $rounded : $rounded;
    }

    private function paintBackground(Box $box, ContentStream $stream): void
    {
        // Inline-level backgrounds (InlineBox, TextBox, LineBreakBox) are
        // painted per-fragment by {@see paintInlineBackgrounds()}; their
        // own geometry is meaningless for block-style background painting
        // (layout doesn't size them as a single rect). AtomicInlineBox /
        // BlockBox / AnonymousBlockBox keep the block-style fill.
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\InlineBox
            || $box instanceof \Phpdftk\HtmlToPdf\Box\TextBox
            || $box instanceof \Phpdftk\HtmlToPdf\Box\LineBreakBox
        ) {
            return;
        }
        $color = $box->style->get('background-color');
        $bgImage = $box->style->get('background-image');
        $hasColor = $color instanceof Color && $color->a > 0.0;
        $hasImage = $bgImage instanceof \Phpdftk\Css\Value\Url;
        $hasGradient = $bgImage instanceof \Phpdftk\Css\Value\LinearGradient;
        $hasRadial = $bgImage instanceof \Phpdftk\Css\Value\RadialGradient;
        if (!$hasColor && !$hasImage && !$hasGradient && !$hasRadial) {
            return;
        }
        $geo = $box->geometry;
        // CSS Backgrounds 3 §3.5 — `background-clip` controls which
        // box edge the background paint extends to. `border-box`
        // (initial) reaches the outer border edge; `padding-box`
        // stops at the inner border edge; `content-box` stays inside
        // padding. We honour all three keywords.
        $clip = $this->resolveBackgroundClip($box);
        switch ($clip) {
            case 'content-box':
                $x = $geo->x;
                $top = $geo->y;
                $width = $geo->width;
                $height = $geo->height;
                break;
            case 'padding-box':
                $x = $geo->x - $geo->paddingLeft;
                $top = $geo->y - $geo->paddingTop;
                $width = $geo->paddingLeft + $geo->width + $geo->paddingRight;
                $height = $geo->paddingTop + $geo->height + $geo->paddingBottom;
                break;
            default: // 'border-box'
                $x = $geo->x - $geo->paddingLeft - $geo->borderLeft;
                $top = $geo->y - $geo->paddingTop - $geo->borderTop;
                $width = $geo->paddingLeft + $geo->width + $geo->paddingRight
                    + $geo->borderLeft + $geo->borderRight;
                $height = $geo->paddingTop + $geo->height + $geo->paddingBottom
                    + $geo->borderTop + $geo->borderBottom;
        }
        if ($hasColor) {
            $radii = $this->borderRadii($box);
            if (array_sum($radii) > 0.0) {
                $this->emitRoundedFill($stream, $x, $top, $width, $height, $radii, $color);
            } else {
                $this->emitRect($stream, $x, $top, $width, $height, fill: $color);
            }
        }
        if ($hasImage && $width > 0.0 && $height > 0.0) {
            $sizeValue = $box->style->get('background-size');
            $positionValue = $box->style->get('background-position');
            $repeatValue = $box->style->get('background-repeat');
            // CSS Backgrounds 3 §3.4 — `background-origin` controls
            // which box rect the image is positioned within. It is
            // independent of `background-clip` (which controls where
            // the paint is clipped).
            $origin = $this->resolveBackgroundOrigin($box);
            $originRect = $this->backgroundOriginRect($box, $origin);
            $this->paintBackgroundImage(
                $bgImage,
                $stream,
                $x,
                $top,
                $width,
                $height,
                $sizeValue,
                $positionValue,
                $repeatValue,
                $originRect,
            );
        }
        if ($hasGradient && $width > 0.0 && $height > 0.0) {
            $this->paintLinearGradient($bgImage, $stream, $x, $top, $width, $height);
        }
        if ($hasRadial && $width > 0.0 && $height > 0.0) {
            $this->paintRadialGradient($bgImage, $stream, $x, $top, $width, $height);
        }
    }

    /**
     * Paint a CSS `radial-gradient([<shape> <size>] [at <position>], <stops>)`
     * as the box's background. Phase-1 simplification: only the first
     * and last stops are honoured (PDF's basic ShadingType3 is two-stop),
     * the box centre is used when no `at <position>` is supplied, and
     * `circle` shapes default to half the box's smaller side while
     * `ellipse` shapes get half the box's dimensions per axis.
     *
     * Because PDF's radial shading is a *circular* primitive, ellipse
     * gradients are approximated by scaling the user-space matrix so a
     * unit circle becomes an ellipse — the gradient still expands
     * outward proportionally.
     */
    private function paintRadialGradient(
        \Phpdftk\Css\Value\RadialGradient $gradient,
        ContentStream $stream,
        float $x,
        float $top,
        float $width,
        float $height,
    ): void {
        if ($this->writer === null || $gradient->stops === []) {
            return;
        }
        $stopList = $this->resolveGradientStops($gradient->stops);
        $pdfY = $this->pageHeight - $top - $height;
        // Centre: default to the box centre when no `at <position>` is
        // supplied. Author-supplied length values resolve relative to
        // the box's content rect.
        $cx = $x + ($gradient->centerX !== null ? $gradient->centerX->value : $width / 2);
        $cy = $pdfY + ($height - ($gradient->centerY !== null ? $gradient->centerY->value : $height / 2));
        // Radii: prefer author lengths, otherwise default to the
        // farthest-corner distance for circles (PDF's two-stop primitive
        // assumes a single outer radius; we use the larger axis).
        $rx = $gradient->sizeX !== null ? $gradient->sizeX->value : $width / 2;
        $ry = $gradient->sizeY !== null ? $gradient->sizeY->value : $height / 2;
        // ShadingType3 takes inner+outer concentric circles. Phase-1 has
        // a single outer radius (inner = 0); scale the user-space matrix
        // for elliptical aspect when sizeX != sizeY.
        try {
            $doc = \Phpdftk\Pdf\Writer\PdfDoc::wrap($this->writer);
            $pattern = $doc->addRadialGradientStops(
                new \Phpdftk\Geometry\Point(0, 0),
                0.0,
                new \Phpdftk\Geometry\Point(0, 0),
                max($rx, $ry),
                $stopList,
            );
        } catch (\Throwable) {
            return;
        }
        $patternName = $this->page?->useGradient($pattern);
        if ($patternName === null) {
            return;
        }
        $stream->saveGraphicsState();
        // Clip to the box rect, then translate to the centre and scale
        // for elliptical shapes so the unit-radius gradient covers the
        // right footprint.
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->clip();
        $stream->endPath();
        $scaleX = $rx / max($rx, $ry);
        $scaleY = $ry / max($rx, $ry);
        $stream->concatMatrix($scaleX, 0.0, 0.0, $scaleY, $cx, $cy);
        $stream->setFillColorSpace('Pattern');
        $stream->setFillColor($patternName);
        // Paint over a rect big enough to cover the largest possible
        // gradient extent (in the un-scaled space the gradient sits at
        // origin with radius `max(rx, ry)`, so a square of side 2*max
        // covers it).
        $extent = max($rx, $ry);
        $stream->rectangle(-$extent, -$extent, 2 * $extent, 2 * $extent);
        $stream->fill();
        $stream->restoreGraphicsState();
    }

    /**
     * Normalise CSS gradient stops to PDF `{offset, rgb}` tuples per
     * CSS Images 3 §3.5.1: unspecified positions distribute evenly
     * between adjacent positioned stops; the first stop defaults to
     * offset 0 and the last to 1. After normalisation, offsets are
     * monotonically non-decreasing in [0, 1].
     *
     * @param list<\Phpdftk\Css\Value\GradientStop> $stops
     * @return list<array{offset: float, rgb: array{float, float, float}}>
     */
    private function resolveGradientStops(array $stops): array
    {
        $count = count($stops);
        if ($count === 0) {
            return [];
        }
        // Step 1: pull positions where authored. `<percentage>` →
        // [0,1] fraction; `<length>` is not yet honoured (a Phase-3
        // follow-up — would require the gradient line's length).
        $offsets = array_fill(0, $count, null);
        foreach ($stops as $i => $s) {
            if ($s->position instanceof \Phpdftk\Css\Value\Percentage) {
                $offsets[$i] = max(0.0, min(1.0, $s->position->value / 100.0));
            }
        }
        // Step 2: anchor unset endpoints at 0/1.
        if ($offsets[0] === null) {
            $offsets[0] = 0.0;
        }
        if ($offsets[$count - 1] === null) {
            $offsets[$count - 1] = 1.0;
        }
        // Step 3: monotonic clamp — each stop's offset must be ≥ the
        // previous stop's offset.
        $prev = 0.0;
        foreach ($offsets as $i => $o) {
            if ($o !== null) {
                $offsets[$i] = max($o, $prev);
                $prev = $offsets[$i];
            }
        }
        // Step 4: linearly interpolate runs of unset offsets between
        // adjacent anchored stops.
        $i = 0;
        while ($i < $count) {
            if ($offsets[$i] !== null) {
                $i++;
                continue;
            }
            $start = $i - 1;
            $end = $i;
            while ($end < $count && $offsets[$end] === null) {
                $end++;
            }
            // $start ≥ 0 (anchored), $end < $count (anchored at last).
            $startOffset = $offsets[$start];
            $endOffset = $offsets[$end];
            $span = $endOffset - $startOffset;
            $gap = $end - $start;
            for ($j = $start + 1; $j < $end; $j++) {
                $offsets[$j] = $startOffset + $span * ($j - $start) / $gap;
            }
            $i = $end;
        }
        // Step 5: emit tuples.
        $out = [];
        foreach ($stops as $i => $s) {
            $out[] = [
                'offset' => (float) $offsets[$i],
                'rgb' => [$s->color->r, $s->color->g, $s->color->b],
            ];
        }
        return $out;
    }

    /**
     * Paint a CSS `linear-gradient(<angle>|to <side>, <stops>)` as the
     * box's background. Phase-1 simplification: only the first and last
     * stop colours are honoured (PDF's basic shading dictionary is
     * two-stop). The gradient line orientation comes from the CSS angle
     * (CSS direction: 0deg = upward, 90deg = rightward, 180deg = down,
     * 270deg = leftward; angles increase clockwise).
     */
    private function paintLinearGradient(
        \Phpdftk\Css\Value\LinearGradient $gradient,
        ContentStream $stream,
        float $x,
        float $top,
        float $width,
        float $height,
    ): void {
        if ($this->writer === null || $gradient->stops === []) {
            return;
        }
        $stopList = $this->resolveGradientStops($gradient->stops);
        $pdfY = $this->pageHeight - $top - $height;
        // CSS angle convention: 0deg points up, increases clockwise. The
        // gradient line passes through the centre. Compute its start
        // and end points on the box's edge per CSS Images 3 §3.1.
        $angle = fmod($gradient->angleDeg, 360.0);
        if ($angle < 0.0) {
            $angle += 360.0;
        }
        $rad = deg2rad($angle);
        $cx = $x + $width / 2;
        $cy = $pdfY + $height / 2;
        // Gradient line half-length so the endpoints sit on the box
        // boundary corners (CSS spec): l/2 = |W sin θ| + |H cos θ| / 2
        $sin = sin($rad);
        $cos = cos($rad);
        $halfLen = (abs($width * $sin) + abs($height * $cos)) / 2;
        // The CSS convention rotates the gradient line such that 0deg
        // points UP (towards the box top). In PDF space the y-axis
        // grows upward already (after our flip), so "up" is +y.
        $dx = $sin;
        $dy = $cos;
        $startPdfX = $cx - $dx * $halfLen;
        $startPdfY = $cy - $dy * $halfLen;
        $endPdfX = $cx + $dx * $halfLen;
        $endPdfY = $cy + $dy * $halfLen;
        try {
            $doc = \Phpdftk\Pdf\Writer\PdfDoc::wrap($this->writer);
            $pattern = $doc->addLinearGradientStops(
                new \Phpdftk\Geometry\Point($startPdfX, $startPdfY),
                new \Phpdftk\Geometry\Point($endPdfX, $endPdfY),
                $stopList,
            );
        } catch (\Throwable) {
            return;
        }
        $stream->saveGraphicsState();
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->clip();
        $stream->endPath();
        $patternName = $this->page?->useGradient($pattern);
        if ($patternName !== null) {
            $stream->setFillColorSpace('Pattern');
            $stream->setFillColor($patternName);
            $stream->rectangle($x, $pdfY, $width, $height);
            $stream->fill();
        }
        $stream->restoreGraphicsState();
    }

    /**
     * Paint a CSS `background-image: url(...)` over the box's
     * background-positioning area. CSS Backgrounds 3 §3.9 `background-size`
     * support:
     *   - `auto` / unset → stretch to fill (Phase-1 default; the legacy
     *     `100% 100%`-equivalent we shipped before).
     *   - `cover` → preserve aspect, scale to fully cover the box (image
     *     may overflow; clipped to box rect).
     *   - `contain` → preserve aspect, scale to fit inside the box;
     *     image is centred and may show background-color through the
     *     letterbox area.
     *   - `<length> <length>` → explicit width × height; centred.
     */
    /**
     * @param array{x: float, top: float, width: float, height: float}|null $originRect
     *   Positioning area per CSS Backgrounds 3 §3.4 `background-origin`.
     *   When null, defaults to the (x, top, width, height) clip rect —
     *   keeps the Phase-1 behaviour of positioning + clipping against
     *   the same rect. When supplied, image sizing + positioning math
     *   uses this rect while clipping uses the outer (clip) rect.
     */
    private function paintBackgroundImage(
        \Phpdftk\Css\Value\Url $url,
        ContentStream $stream,
        float $x,
        float $top,
        float $width,
        float $height,
        ?\Phpdftk\Css\Value\Value $sizeValue = null,
        ?\Phpdftk\Css\Value\Value $positionValue = null,
        ?\Phpdftk\Css\Value\Value $repeatValue = null,
        ?array $originRect = null,
    ): void {
        if ($this->writer === null || $this->page === null) {
            return;
        }
        $src = $url->url;
        if (isset($this->imageNameCache[$src])) {
            $name = $this->imageNameCache[$src];
        } else {
            $resolved = $this->resolveImageSrc($src);
            if ($resolved === null) {
                return;
            }
            try {
                $name = $this->writer->addImage($resolved, $this->page);
            } catch (\Throwable) {
                return;
            }
            $this->imageNameCache[$src] = $name;
        }
        // Positioning anchor: $originRect when supplied, else fall
        // back to the clip rect (Phase-1 behaviour).
        $originX = $originRect['x'] ?? $x;
        $originTop = $originRect['top'] ?? $top;
        $originWidth = $originRect['width'] ?? $width;
        $originHeight = $originRect['height'] ?? $height;
        // Resolve final paint rect (final size + offset within the
        // positioning area).
        $paint = $this->resolveBackgroundSize($sizeValue, $src, $originWidth, $originHeight);
        // Author `background-position` may override the size resolver's
        // default centred offset. `auto` size keeps the stretch
        // behaviour so positioning has no effect (the rect equals the
        // origin box). For non-auto sizes, apply the position formula:
        // offset = (box - image) × percent.
        $isAuto = $sizeValue === null
            || ($sizeValue instanceof \Phpdftk\Css\Value\Keyword
                && strtolower($sizeValue->name) === 'auto');
        if (!$isAuto && $positionValue !== null) {
            $pos = $this->resolveBackgroundPosition(
                $positionValue,
                $paint['w'],
                $paint['h'],
                $originWidth,
                $originHeight,
            );
            $paint['offsetX'] = $pos['offsetX'];
            $paint['offsetY'] = $pos['offsetY'];
        }
        $pdfY = $this->pageHeight - $top - $height;
        $stream->saveGraphicsState();
        // `cover` may overflow the box; clip to box rect so the overflow
        // doesn't bleed into adjacent boxes.
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->clip();
        $stream->endPath();
        // CSS Backgrounds 3 §3.8: `background-repeat` decides whether
        // to tile the image when its painted rect doesn't fill the box.
        // `no-repeat` paints one instance; `repeat` / `repeat-x` /
        // `repeat-y` tile across the relevant axes. The painter's box
        // clip handles edge tiles that extend past the box rect.
        $repeat = $this->repeatAxes($repeatValue);
        $tileW = $paint['w'];
        $tileH = $paint['h'];
        if ($tileW <= 0.0 || $tileH <= 0.0) {
            $stream->restoreGraphicsState();
            return;
        }
        // Start positions: shift the anchor backwards by whole tile
        // widths until the leftmost / topmost tile sits at or before
        // the origin box (NOT the clip box — tiles anchor against
        // `background-origin`). With `no-repeat`, no shift happens.
        $startX = $paint['offsetX'];
        if ($repeat['x']) {
            while ($startX > 0.0) {
                $startX -= $tileW;
            }
        }
        $startY = $paint['offsetY'];
        if ($repeat['y']) {
            while ($startY > 0.0) {
                $startY -= $tileH;
            }
        }
        // Tile iteration bounds: extend until the tile passes the
        // origin rect's far edge. Tiles anchored inside the origin
        // rect may still spill into the clip rect (when clip > origin)
        // — that's the spec semantic.
        $originBottomLayoutY = $originTop + $originHeight;
        $originPdfBottom = $this->pageHeight - $originBottomLayoutY;
        $maxTiles = 4096;
        $tileCount = 0;
        $offsetY = $startY;
        while ($offsetY < $originHeight) {
            $offsetX = $startX;
            while ($offsetX < $originWidth) {
                if ($tileCount >= $maxTiles) {
                    break 2;
                }
                $stream->saveGraphicsState();
                $stream->concatMatrix(
                    $tileW,
                    0.0,
                    0.0,
                    $tileH,
                    $originX + $offsetX,
                    $originPdfBottom + ($originHeight - $tileH - $offsetY),
                );
                $stream->doXObject($name);
                $stream->restoreGraphicsState();
                $tileCount++;
                if (!$repeat['x']) {
                    break;
                }
                $offsetX += $tileW;
            }
            if (!$repeat['y']) {
                break;
            }
            $offsetY += $tileH;
        }
        $stream->restoreGraphicsState();
    }

    /**
     * Resolve a CSS `background-repeat` value to a `{x: bool, y: bool}`
     * pair indicating whether each axis should tile.
     *
     * Phase-1 handles the simple keyword set:
     *   - `repeat` (default) → both axes
     *   - `repeat-x` → x only
     *   - `repeat-y` → y only
     *   - `no-repeat` → neither
     *   - two-value form (e.g. `repeat no-repeat`): per-axis keywords
     * The `space` / `round` per-axis variants land later — they need
     * extra-spacing / scaling math beyond the simple loop.
     *
     * @return array{x: bool, y: bool}
     */
    private function repeatAxes(?\Phpdftk\Css\Value\Value $value): array
    {
        if ($value === null) {
            return ['x' => true, 'y' => true];
        }
        $items = $value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
                ? $value->values
                : [$value];
        if (count($items) === 2
            && $items[0] instanceof \Phpdftk\Css\Value\Keyword
            && $items[1] instanceof \Phpdftk\Css\Value\Keyword
        ) {
            return [
                'x' => strtolower($items[0]->name) !== 'no-repeat',
                'y' => strtolower($items[1]->name) !== 'no-repeat',
            ];
        }
        if ($items[0] instanceof \Phpdftk\Css\Value\Keyword) {
            return match (strtolower($items[0]->name)) {
                'repeat-x' => ['x' => true, 'y' => false],
                'repeat-y' => ['x' => false, 'y' => true],
                'no-repeat' => ['x' => false, 'y' => false],
                default => ['x' => true, 'y' => true],
            };
        }
        return ['x' => true, 'y' => true];
    }

    /**
     * Resolve a CSS `background-position` value to a top-left offset of
     * the image rect within the background-positioning area (the box's
     * rect). Per CSS Backgrounds 3 §3.7, position offsets are
     * interpolated such that `0% 0%` puts the image's top-left at the
     * box's top-left and `100% 100%` puts the image's bottom-right at
     * the box's bottom-right (i.e. `offset = (box - image) × percent`).
     *
     * Phase-1 surface:
     *   - 1 keyword (`center` / `top` / `bottom` / `left` / `right`) → centred on the missing axis
     *   - 2 values (keyword | length | percentage), one per axis
     *   - Lengths offset directly; percentages use the spec formula.
     * Edge syntax (`right 10px bottom 20px`, 4-value form) lands later.
     *
     * @return array{offsetX: float, offsetY: float}
     */
    private function resolveBackgroundPosition(
        \Phpdftk\Css\Value\Value $value,
        float $imageWidth,
        float $imageHeight,
        float $boxWidth,
        float $boxHeight,
    ): array {
        $items = $value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
                ? $value->values
                : [$value];
        // Default both axes to centre (`50%`).
        $xPercent = 0.5;
        $yPercent = 0.5;
        $xLength = null;
        $yLength = null;
        // Single keyword: maps to one axis and centres the other.
        if (count($items) === 1 && $items[0] instanceof \Phpdftk\Css\Value\Keyword) {
            $kw = strtolower($items[0]->name);
            // Vertical-only keywords pin y; horizontal-only pin x.
            switch ($kw) {
                case 'top': $yPercent = 0.0;
                    break;
                case 'bottom': $yPercent = 1.0;
                    break;
                case 'left': $xPercent = 0.0;
                    break;
                case 'right': $xPercent = 1.0;
                    break;
                case 'center': default: break;
            }
        } else {
            // Two-value form: first is x, second is y.
            $xItem = $items[0] ?? null;
            $yItem = $items[1] ?? null;
            $xAxis = $this->axisOffsetFromValue($xItem, isHorizontal: true);
            $yAxis = $this->axisOffsetFromValue($yItem, isHorizontal: false);
            if ($xAxis['percent'] !== null) {
                $xPercent = $xAxis['percent'];
            }
            if ($xAxis['length'] !== null) {
                $xLength = $xAxis['length'];
            }
            if ($yAxis['percent'] !== null) {
                $yPercent = $yAxis['percent'];
            }
            if ($yAxis['length'] !== null) {
                $yLength = $yAxis['length'];
            }
        }
        $offsetX = $xLength ?? ($boxWidth - $imageWidth) * $xPercent;
        $offsetY = $yLength ?? ($boxHeight - $imageHeight) * $yPercent;
        return ['offsetX' => $offsetX, 'offsetY' => $offsetY];
    }

    /**
     * Classify a single `background-position` axis value into a
     * `{percent?, length?}` pair. Keywords (`top`/`bottom`/`left`/
     * `right`/`center`) become percentages; explicit `<length>` /
     * `<percentage>` values come through as-is.
     *
     * @return array{percent: ?float, length: ?float}
     */
    private function axisOffsetFromValue(
        ?\Phpdftk\Css\Value\Value $value,
        bool $isHorizontal,
    ): array {
        if ($value === null) {
            return ['percent' => 0.5, 'length' => null];
        }
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            $kw = strtolower($value->name);
            $percent = match ($kw) {
                'left', 'top' => 0.0,
                'right', 'bottom' => 1.0,
                'center' => 0.5,
                default => 0.5,
            };
            return ['percent' => $percent, 'length' => null];
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return ['percent' => $value->value / 100.0, 'length' => null];
        }
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return ['percent' => null, 'length' => $value->value];
        }
        // CSS Values 4 §4.2: bare `0` is treated as `0` in any
        // dimensional context, so `background-position: 0 0` resolves
        // to top-left anchor (not the default centre).
        if (($value instanceof \Phpdftk\Css\Value\Number
            || $value instanceof \Phpdftk\Css\Value\Integer)
            && (float) $value->value === 0.0
        ) {
            return ['percent' => null, 'length' => 0.0];
        }
        return ['percent' => 0.5, 'length' => null];
    }

    /**
     * Compute the final paint rect for a CSS `background-size` value.
     * Returns the width / height (in points) and the offset (top-left
     * corner) within the containing background-positioning area. Phase 1
     * always centres `contain`-sized images; `background-position`
     * support lands later with the full Backgrounds 3 §3.7 grammar.
     *
     * @return array{w: float, h: float, offsetX: float, offsetY: float}
     */
    private function resolveBackgroundSize(
        ?\Phpdftk\Css\Value\Value $sizeValue,
        string $src,
        float $boxWidth,
        float $boxHeight,
    ): array {
        // Default / unset / `auto`: legacy stretch behaviour.
        $isAuto = $sizeValue === null
            || ($sizeValue instanceof \Phpdftk\Css\Value\Keyword
                && strtolower($sizeValue->name) === 'auto');
        if ($isAuto) {
            return ['w' => $boxWidth, 'h' => $boxHeight, 'offsetX' => 0.0, 'offsetY' => 0.0];
        }
        $keyword = $sizeValue instanceof \Phpdftk\Css\Value\Keyword
            ? strtolower($sizeValue->name)
            : null;
        if ($keyword === 'cover' || $keyword === 'contain') {
            $intrinsic = $this->intrinsicSize($src);
            if ($intrinsic === null) {
                // Fallback to stretch when we can't read natural size.
                return ['w' => $boxWidth, 'h' => $boxHeight, 'offsetX' => 0.0, 'offsetY' => 0.0];
            }
            [$natW, $natH] = $intrinsic;
            $scaleW = $boxWidth / $natW;
            $scaleH = $boxHeight / $natH;
            $scale = $keyword === 'cover' ? max($scaleW, $scaleH) : min($scaleW, $scaleH);
            $finalW = $natW * $scale;
            $finalH = $natH * $scale;
            return [
                'w' => $finalW,
                'h' => $finalH,
                'offsetX' => ($boxWidth - $finalW) / 2,
                'offsetY' => ($boxHeight - $finalH) / 2,
            ];
        }
        // `<length> <length>` — explicit dimensions in a 2-element
        // space-separated ValueList. Single value sets width, height auto
        // (which we resolve to the natural aspect).
        if ($sizeValue instanceof \Phpdftk\Css\Value\ValueList
            && $sizeValue->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $w = $sizeValue->values[0] ?? null;
            $h = $sizeValue->values[1] ?? null;
            $finalW = $w instanceof \Phpdftk\Css\Value\Length ? $w->value : $boxWidth;
            $finalH = $h instanceof \Phpdftk\Css\Value\Length ? $h->value : $boxHeight;
            return [
                'w' => $finalW,
                'h' => $finalH,
                'offsetX' => max(0.0, ($boxWidth - $finalW) / 2),
                'offsetY' => max(0.0, ($boxHeight - $finalH) / 2),
            ];
        }
        if ($sizeValue instanceof \Phpdftk\Css\Value\Length) {
            // Single length sets width; height = natural aspect.
            $intrinsic = $this->intrinsicSize($src);
            $finalW = $sizeValue->value;
            $finalH = $intrinsic !== null && $intrinsic[0] > 0
                ? $finalW * ($intrinsic[1] / $intrinsic[0])
                : $boxHeight;
            return [
                'w' => $finalW,
                'h' => $finalH,
                'offsetX' => max(0.0, ($boxWidth - $finalW) / 2),
                'offsetY' => max(0.0, ($boxHeight - $finalH) / 2),
            ];
        }
        return ['w' => $boxWidth, 'h' => $boxHeight, 'offsetX' => 0.0, 'offsetY' => 0.0];
    }

    /**
     * Read the intrinsic pixel dimensions of an image referenced by an
     * `<img src>` or `background-image: url(...)` value. Tolerates both
     * `data:image/...` URIs and resolved local-file paths via the
     * painter's existing `resolveImageSrc`. Returns null when the bytes
     * can't be read or parsed.
     *
     * @return array{int, int}|null
     */
    private function intrinsicSize(string $src): ?array
    {
        try {
            if (str_starts_with($src, 'data:image/')) {
                if (preg_match('~^data:image/(png|jpeg|jpg);(base64,)?(.*)$~s', $src, $m) !== 1) {
                    return null;
                }
                $payload = $m[2] === 'base64,'
                    ? base64_decode($m[3], strict: true)
                    : urldecode($m[3]);
                if ($payload === false || $payload === '') {
                    return null;
                }
                $info = \Phpdftk\ImageMetadata\ImageParser::parseString($payload);
            } else {
                $resolved = $this->resolveImageSrc($src);
                if ($resolved === null) {
                    return null;
                }
                $info = \Phpdftk\ImageMetadata\ImageParser::parse($resolved);
            }
        } catch (\Throwable) {
            return null;
        }
        return [$info->width, $info->height];
    }

    private function paintBorders(Box $box, ContentStream $stream): void
    {
        $geo = $box->geometry;
        $outerX = $geo->x - $geo->paddingLeft - $geo->borderLeft;
        $outerY = $geo->y - $geo->paddingTop - $geo->borderTop;
        $outerWidth = $geo->paddingLeft + $geo->width + $geo->paddingRight
            + $geo->borderLeft + $geo->borderRight;
        $outerHeight = $geo->paddingTop + $geo->height + $geo->paddingBottom
            + $geo->borderTop + $geo->borderBottom;

        // Rounded-uniform-border fast path: all four sides share width +
        // colour + style, and any radius is set → emit one stroked
        // rounded path. Mixed-width/colour borders or no radius fall back
        // to the per-side rectangle path (still straight corners).
        $radii = $this->borderRadii($box);
        if (array_sum($radii) > 0.0 && $this->bordersAreUniform($box)) {
            $width = $geo->borderTop;
            if ($width > 0.0) {
                $this->emitRoundedStroke(
                    $stream,
                    $outerX + $width / 2,
                    $outerY + $width / 2,
                    $outerWidth - $width,
                    $outerHeight - $width,
                    $radii,
                    $this->borderColor($box, 'top'),
                    $width,
                );
                return;
            }
        }

        if ($geo->borderTop > 0.0 && $this->borderIsVisible($box, 'top')) {
            $this->paintBorderSide(
                $stream,
                $this->borderStyleName($box, 'top'),
                $this->borderColor($box, 'top'),
                $outerX,
                $outerY,
                $outerWidth,
                $geo->borderTop,
                side: 'top',
            );
        }
        if ($geo->borderBottom > 0.0 && $this->borderIsVisible($box, 'bottom')) {
            $this->paintBorderSide(
                $stream,
                $this->borderStyleName($box, 'bottom'),
                $this->borderColor($box, 'bottom'),
                $outerX,
                $outerY + $outerHeight - $geo->borderBottom,
                $outerWidth,
                $geo->borderBottom,
                side: 'bottom',
            );
        }
        if ($geo->borderLeft > 0.0 && $this->borderIsVisible($box, 'left')) {
            $this->paintBorderSide(
                $stream,
                $this->borderStyleName($box, 'left'),
                $this->borderColor($box, 'left'),
                $outerX,
                $outerY,
                $geo->borderLeft,
                $outerHeight,
                side: 'left',
            );
        }
        if ($geo->borderRight > 0.0 && $this->borderIsVisible($box, 'right')) {
            $this->paintBorderSide(
                $stream,
                $this->borderStyleName($box, 'right'),
                $this->borderColor($box, 'right'),
                $outerX + $outerWidth - $geo->borderRight,
                $outerY,
                $geo->borderRight,
                $outerHeight,
                side: 'right',
            );
        }
    }

    /**
     * Paint one border side honouring `border-style`. `axis` flags
     * whether the rect runs horizontally (top / bottom) or vertically
     * (left / right) so the `double` decomposition knows which
     * dimension to split into thirds.
     *
     *  - `solid`: one filled rect (the original behaviour).
     *  - `double` (CSS Backgrounds 3 §5): two parallel bands each
     *    `thickness/3` thick with a `thickness/3` gap. When the
     *    thickness is too small to split (< 3 units), falls back to
     *    solid so the border doesn't disappear into a hairline.
     *  - `dashed` / `dotted`: stroke a line at the centerline of the
     *    side with a PDF dash pattern. Dashed uses 3w-on / 2w-off;
     *    dotted uses 1w-on / 1w-off (PDF rounds dotted patterns to
     *    square caps).
     *  - Other style keywords (`groove`, `ridge`, `inset`, `outset`):
     *    Phase-1 fallback to solid.
     */
    private function paintBorderSide(
        ContentStream $stream,
        string $styleName,
        Color $color,
        float $x,
        float $y,
        float $width,
        float $height,
        string $side,
    ): void {
        $axis = ($side === 'top' || $side === 'bottom') ? 'horizontal' : 'vertical';
        // CSS Backgrounds 3 §5.2 — 3D-effect styles. `inset` darkens
        // top + left; `outset` lightens them; `groove` and `ridge`
        // split per side as if etched / raised.
        if (in_array($styleName, ['inset', 'outset', 'groove', 'ridge'], true)) {
            $color = $this->resolve3dBorderColor($styleName, $color, $side);
        }
        if ($styleName === 'dashed' || $styleName === 'dotted') {
            $thickness = $axis === 'horizontal' ? $height : $width;
            $this->paintDashedDottedSide(
                $stream,
                $styleName,
                $color,
                $x,
                $y,
                $width,
                $height,
                $axis,
                $thickness,
            );
            return;
        }
        if ($styleName === 'double') {
            $thickness = $axis === 'horizontal' ? $height : $width;
            if ($thickness >= 3.0) {
                $third = $thickness / 3.0;
                if ($axis === 'horizontal') {
                    // Two horizontal bands stacked vertically.
                    $this->emitRect($stream, $x, $y, $width, $third, fill: $color);
                    $this->emitRect($stream, $x, $y + 2 * $third, $width, $third, fill: $color);
                } else {
                    // Two vertical bands stacked horizontally.
                    $this->emitRect($stream, $x, $y, $third, $height, fill: $color);
                    $this->emitRect($stream, $x + 2 * $third, $y, $third, $height, fill: $color);
                }
                return;
            }
        }
        $this->emitRect($stream, $x, $y, $width, $height, fill: $color);
    }

    /**
     * Stroke one side of a border as a dashed / dotted line at the
     * centerline of the side, at line-width = thickness. PDF's `d`
     * operator takes a `[on, off]` array + phase; we use:
     *   - dashed: `[thickness*3, thickness*2]`
     *   - dotted: `[thickness, thickness]` (PDF strokes with square
     *     caps so dotted shows as squares the size of thickness;
     *     close enough to CSS dotted in print rendering).
     */
    private function paintDashedDottedSide(
        ContentStream $stream,
        string $styleName,
        Color $color,
        float $x,
        float $y,
        float $width,
        float $height,
        string $axis,
        float $thickness,
    ): void {
        if ($thickness <= 0.0) {
            return;
        }
        $stream->saveGraphicsState();
        $stream->setStrokeColorRGB($color->r, $color->g, $color->b);
        $stream->setLineWidth($thickness);
        $pattern = $styleName === 'dotted'
            ? [$thickness, $thickness]
            : [$thickness * 3, $thickness * 2];
        $stream->setDashPattern($pattern, 0);
        if ($axis === 'horizontal') {
            // Stroke from (x, midY) to (x+width, midY). PDF Y is
            // inverted; midY in PDF coords = pageHeight - (y + height/2).
            $midPdfY = $this->pageHeight - ($y + $height / 2.0);
            $stream->moveTo($x, $midPdfY);
            $stream->lineTo($x + $width, $midPdfY);
        } else {
            $midX = $x + $width / 2.0;
            $topPdfY = $this->pageHeight - $y;
            $bottomPdfY = $this->pageHeight - ($y + $height);
            $stream->moveTo($midX, $topPdfY);
            $stream->lineTo($midX, $bottomPdfY);
        }
        $stream->stroke();
        $stream->restoreGraphicsState();
    }

    /**
     * Resolve the per-side colour for CSS Backgrounds 3 §5.2 3D-style
     * borders. The light source is conventionally top-left:
     *
     *  - `inset`  → top + left use a darker variant (carved-in look).
     *  - `outset` → bottom + right use a darker variant (raised look).
     *  - `groove` → top + left darker, bottom + right lighter (etched in).
     *  - `ridge`  → top + left lighter, bottom + right darker (raised ridge).
     *
     * "Darker" multiplies each RGB channel by 0.5; "lighter" lightens
     * toward white by 30%. These match common browser approximations.
     */
    private function resolve3dBorderColor(string $styleName, Color $base, string $side): Color
    {
        $isTopLeft = $side === 'top' || $side === 'left';
        $darken = static function (Color $c): Color {
            return new Color($c->r * 0.5, $c->g * 0.5, $c->b * 0.5, $c->a, $c->space);
        };
        $lighten = static function (Color $c): Color {
            return new Color(
                $c->r + (1.0 - $c->r) * 0.3,
                $c->g + (1.0 - $c->g) * 0.3,
                $c->b + (1.0 - $c->b) * 0.3,
                $c->a,
                $c->space,
            );
        };
        return match ($styleName) {
            'inset' => $isTopLeft ? $darken($base) : $base,
            'outset' => $isTopLeft ? $base : $darken($base),
            'groove' => $isTopLeft ? $darken($base) : $lighten($base),
            'ridge' => $isTopLeft ? $lighten($base) : $darken($base),
            default => $base,
        };
    }

    private function borderStyleName(Box $box, string $side): string
    {
        $value = $box->style->get("border-$side-style");
        if (!$value instanceof Keyword) {
            return 'none';
        }
        return strtolower($value->name);
    }

    /**
     * Uniform borders: same width / colour / visible-style on all 4 sides.
     * Enables the rounded-stroke fast path; mixed borders fall back to
     * straight per-side rectangles.
     */
    private function bordersAreUniform(Box $box): bool
    {
        $g = $box->geometry;
        if (abs($g->borderTop - $g->borderRight) > 0.001
            || abs($g->borderTop - $g->borderBottom) > 0.001
            || abs($g->borderTop - $g->borderLeft) > 0.001
        ) {
            return false;
        }
        $colorTop = $this->borderColor($box, 'top');
        foreach (['right', 'bottom', 'left'] as $side) {
            if (!$this->borderIsVisible($box, $side)) {
                return false;
            }
            $c = $this->borderColor($box, $side);
            if ($c->r !== $colorTop->r || $c->g !== $colorTop->g || $c->b !== $colorTop->b) {
                return false;
            }
        }
        return $this->borderIsVisible($box, 'top');
    }

    /**
     * Stroke a rounded-rectangle path. `x,topY,width,height` describe the
     * path's centreline (so the stroke straddles both inside and outside);
     * radii are clamped per spec. Used for uniform-border rendering when
     * border-radius is set.
     *
     * @param array{float, float, float, float} $radii
     */
    private function emitRoundedStroke(
        ContentStream $stream,
        float $x,
        float $topY,
        float $width,
        float $height,
        array $radii,
        Color $color,
        float $lineWidth,
    ): void {
        $maxR = min($width, $height) / 2.0;
        [$rtl, $rtr, $rbr, $rbl] = array_map(static fn($r) => max(0.0, min($r, $maxR)), $radii);
        $k = 0.5522847498;
        $bottomPdfY = $this->pageHeight - $topY - $height;
        $topPdfY = $this->pageHeight - $topY;
        $stream->saveGraphicsState();
        $stream->setStrokeColorRGB($color->r, $color->g, $color->b);
        $stream->setLineWidth($lineWidth);
        $stream->moveTo($x + $rtl, $topPdfY);
        $stream->lineTo($x + $width - $rtr, $topPdfY);
        if ($rtr > 0.0) {
            $stream->curveTo(
                $x + $width - $rtr + $rtr * $k,
                $topPdfY,
                $x + $width,
                $topPdfY - $rtr + $rtr * $k,
                $x + $width,
                $topPdfY - $rtr,
            );
        }
        $stream->lineTo($x + $width, $bottomPdfY + $rbr);
        if ($rbr > 0.0) {
            $stream->curveTo(
                $x + $width,
                $bottomPdfY + $rbr - $rbr * $k,
                $x + $width - $rbr + $rbr * $k,
                $bottomPdfY,
                $x + $width - $rbr,
                $bottomPdfY,
            );
        }
        $stream->lineTo($x + $rbl, $bottomPdfY);
        if ($rbl > 0.0) {
            $stream->curveTo(
                $x + $rbl - $rbl * $k,
                $bottomPdfY,
                $x,
                $bottomPdfY + $rbl - $rbl * $k,
                $x,
                $bottomPdfY + $rbl,
            );
        }
        $stream->lineTo($x, $topPdfY - $rtl);
        if ($rtl > 0.0) {
            $stream->curveTo(
                $x,
                $topPdfY - $rtl + $rtl * $k,
                $x + $rtl - $rtl * $k,
                $topPdfY,
                $x + $rtl,
                $topPdfY,
            );
        }
        $stream->closePath();
        $stream->stroke();
        $stream->restoreGraphicsState();
    }

    /**
     * Paint CSS UI 3 §4 `outline`. Outlines don't take part in layout —
     * they're drawn just outside the border-box at `outline-offset`. We
     * only paint the visible outline-style values (everything except
     * `none` / `hidden`); `outline-width` and `outline-color` follow the
     * cascade.
     */
    private function paintOutline(Box $box, ContentStream $stream): void
    {
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\InlineBox
            || $box instanceof \Phpdftk\HtmlToPdf\Box\TextBox
            || $box instanceof \Phpdftk\HtmlToPdf\Box\LineBreakBox
        ) {
            return;
        }
        $style = $box->style->get('outline-style');
        if (!$style instanceof Keyword) {
            return;
        }
        $styleName = strtolower($style->name);
        if ($styleName === 'none' || $styleName === 'hidden') {
            return;
        }
        $widthValue = $box->style->get('outline-width');
        $width = $widthValue instanceof \Phpdftk\Css\Value\Length ? max(0.0, $widthValue->value) : 0.0;
        if ($width <= 0.0) {
            return;
        }
        $offsetValue = $box->style->get('outline-offset');
        $offset = $offsetValue instanceof \Phpdftk\Css\Value\Length ? $offsetValue->value : 0.0;
        $colorValue = $box->style->get('outline-color');
        $color = $colorValue instanceof Color ? $colorValue : ($box->style->get('color') instanceof Color
            ? $box->style->get('color')
            : new Color(0, 0, 0, 1));

        $geo = $box->geometry;
        $outerX = $geo->x - $geo->paddingLeft - $geo->borderLeft - $offset - $width / 2;
        $outerY = $geo->y - $geo->paddingTop - $geo->borderTop - $offset - $width / 2;
        $outerWidth = $geo->paddingLeft + $geo->width + $geo->paddingRight
            + $geo->borderLeft + $geo->borderRight + 2 * $offset + $width;
        $outerHeight = $geo->paddingTop + $geo->height + $geo->paddingBottom
            + $geo->borderTop + $geo->borderBottom + 2 * $offset + $width;
        $pdfY = $this->pageHeight - $outerY - $outerHeight;
        $stream->saveGraphicsState();
        $stream->setStrokeColorRGB($color->r, $color->g, $color->b);
        $stream->setLineWidth($width);
        // CSS Outline 3 §5 styles. `dashed` / `dotted` map onto PDF
        // line-dash patterns; `double` paints two concentric strokes
        // each `width/3` thick separated by a `width/3` gap; the rest
        // (`groove` / `ridge` / `inset` / `outset`) fall back to solid.
        if ($styleName === 'double' && $width >= 3.0) {
            $third = $width / 3.0;
            $stream->setLineWidth($third);
            // Outer ring: path centred between the outline's outer
            // edge and (outer edge + third). The stroke straddles the
            // path by ±third/2, so the outer face sits on the outline
            // outer edge.
            $stream->rectangle(
                $outerX + $third / 2,
                $pdfY + $third / 2,
                $outerWidth - $third,
                $outerHeight - $third,
            );
            $stream->stroke();
            // Inner ring: path centred two-thirds in from the outer.
            $stream->rectangle(
                $outerX + 2.5 * $third,
                $pdfY + 2.5 * $third,
                $outerWidth - 5 * $third,
                $outerHeight - 5 * $third,
            );
            $stream->stroke();
            $stream->restoreGraphicsState();
            return;
        }
        switch ($styleName) {
            case 'dashed':
                $stream->setDashPattern([$width * 3, $width * 2], 0);
                break;
            case 'dotted':
                $stream->setDashPattern([$width, $width * 1.5], 0);
                break;
                // 'groove' / 'ridge' / 'inset' / 'outset' fall back
                // to solid for Phase 1.
        }
        $stream->rectangle($outerX, $pdfY, $outerWidth, $outerHeight);
        $stream->stroke();
        $stream->restoreGraphicsState();
    }

    /**
     * Stroke `column-rule` between adjacent columns inside a multi-column
     * container (CSS Multi-column 1 §3). Each rule is centred in its
     * column-gap, spans the container's content-area height, and honours
     * `column-rule-style` for `solid` / `dashed` / `dotted`. No-op when
     * the box isn't a multi-column container, the rule has zero width, or
     * the style is `none` / `hidden`.
     */
    private function paintColumnRules(Box $box, ContentStream $stream): void
    {
        $mc = $box->multiColumn;
        if ($mc === null || $mc->columnCount < 2) {
            return;
        }
        if ($mc->ruleWidth <= 0.0 || $mc->ruleColor === null) {
            return;
        }
        $styleName = $mc->ruleStyle;
        if ($styleName === 'none' || $styleName === 'hidden') {
            return;
        }
        $geo = $box->geometry;
        $top = $geo->y;
        $height = $geo->height;
        if ($height <= 0.0) {
            return;
        }
        $pdfTop = $this->pageHeight - $top;
        $pdfBottom = $this->pageHeight - ($top + $height);
        $stream->saveGraphicsState();
        $stream->setStrokeColorRGB($mc->ruleColor->r, $mc->ruleColor->g, $mc->ruleColor->b);
        $stream->setLineWidth($mc->ruleWidth);
        switch ($styleName) {
            case 'dashed':
                $stream->setDashPattern([$mc->ruleWidth * 3, $mc->ruleWidth * 2], 0);
                break;
            case 'dotted':
                $stream->setDashPattern([$mc->ruleWidth, $mc->ruleWidth * 1.5], 0);
                break;
                // Other styles (double / groove / ridge / inset / outset)
                // fall back to solid for Phase 1, mirroring the outline
                // painter's approximation.
        }
        for ($i = 0; $i < $mc->columnCount - 1; $i++) {
            // Centre line of the gap between column $i and $i+1.
            $gapCentreX = $geo->x
                + ($i + 1) * $mc->columnWidth
                + $i * $mc->columnGap
                + $mc->columnGap / 2.0;
            $stream->moveTo($gapCentreX, $pdfBottom);
            $stream->lineTo($gapCentreX, $pdfTop);
            $stream->stroke();
        }
        $stream->restoreGraphicsState();
    }

    private function borderIsVisible(Box $box, string $side): bool
    {
        $style = $box->style->get("border-$side-style");
        if (!$style instanceof Keyword) {
            return false;
        }
        $lower = strtolower($style->name);
        return $lower !== 'none' && $lower !== 'hidden';
    }

    private function borderColor(Box $box, string $side): Color
    {
        $color = $box->style->get("border-$side-color");
        if ($color instanceof Color) {
            return $color;
        }
        // CSS Colors 4: border-color initial is currentColor, which means
        // the cascaded `color` property.
        $current = $box->style->get('color');
        if ($current instanceof Color) {
            return $current;
        }
        return new Color(0, 0, 0, 1);
    }

    /**
     * Emit a rect in PDF coordinates (Y flipped from top-down layout space).
     * `topY` is the layout-space top edge; `height` is positive downward.
     */
    private function emitRect(
        ContentStream $stream,
        float $x,
        float $topY,
        float $width,
        float $height,
        Color $fill,
    ): void {
        $pdfY = $this->pageHeight - $topY - $height;
        $stream->saveGraphicsState();
        $stream->setFillColorRGB($fill->r, $fill->g, $fill->b);
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->fill();
        $stream->restoreGraphicsState();
    }

    /**
     * Read the box's four corner radii in pixel-equivalent units. CSS
     * Backgrounds 3 §6 requires each radius to be clamped to half the
     * shorter side; we do that here.
     *
     * @return array{float, float, float, float} [tl, tr, br, bl]
     */
    private function borderRadii(Box $box): array
    {
        $read = function (string $name) use ($box): float {
            $v = $box->style->get($name);
            return $v instanceof \Phpdftk\Css\Value\Length ? max(0.0, $v->value) : 0.0;
        };
        return [
            $read('border-top-left-radius'),
            $read('border-top-right-radius'),
            $read('border-bottom-right-radius'),
            $read('border-bottom-left-radius'),
        ];
    }

    /**
     * Emit a rounded-rectangle fill path using cubic Béziers at the four
     * corners. Topology in layout-Y (top-down) with the painter's flip
     * applied at emission time. The 0.5522847498 constant is the standard
     * cubic-Bézier circle approximation factor.
     *
     * @param array{float, float, float, float} $radii [tl, tr, br, bl]
     */
    private function emitRoundedFill(
        ContentStream $stream,
        float $x,
        float $topY,
        float $width,
        float $height,
        array $radii,
        Color $fill,
    ): void {
        $maxR = min($width, $height) / 2.0;
        [$rtl, $rtr, $rbr, $rbl] = array_map(static fn($r) => min($r, $maxR), $radii);
        $k = 0.5522847498;
        // Flip to PDF coords for emission.
        $bottomPdfY = $this->pageHeight - $topY - $height;
        $topPdfY = $this->pageHeight - $topY;
        // Walk clockwise starting at the top-left straight edge.
        $stream->saveGraphicsState();
        $stream->setFillColorRGB($fill->r, $fill->g, $fill->b);
        $stream->moveTo($x + $rtl, $topPdfY);
        $stream->lineTo($x + $width - $rtr, $topPdfY);
        if ($rtr > 0.0) {
            $stream->curveTo(
                $x + $width - $rtr + $rtr * $k,
                $topPdfY,
                $x + $width,
                $topPdfY - $rtr + $rtr * $k,
                $x + $width,
                $topPdfY - $rtr,
            );
        }
        $stream->lineTo($x + $width, $bottomPdfY + $rbr);
        if ($rbr > 0.0) {
            $stream->curveTo(
                $x + $width,
                $bottomPdfY + $rbr - $rbr * $k,
                $x + $width - $rbr + $rbr * $k,
                $bottomPdfY,
                $x + $width - $rbr,
                $bottomPdfY,
            );
        }
        $stream->lineTo($x + $rbl, $bottomPdfY);
        if ($rbl > 0.0) {
            $stream->curveTo(
                $x + $rbl - $rbl * $k,
                $bottomPdfY,
                $x,
                $bottomPdfY + $rbl - $rbl * $k,
                $x,
                $bottomPdfY + $rbl,
            );
        }
        $stream->lineTo($x, $topPdfY - $rtl);
        if ($rtl > 0.0) {
            $stream->curveTo(
                $x,
                $topPdfY - $rtl + $rtl * $k,
                $x + $rtl - $rtl * $k,
                $topPdfY,
                $x + $rtl,
                $topPdfY,
            );
        }
        $stream->closePath();
        $stream->fill();
        $stream->restoreGraphicsState();
    }
}
