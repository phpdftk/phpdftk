<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Color\CmykColor;
use Phpdftk\Color\ColorInterface;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\RgbColor;
use Phpdftk\Css\Shape\BasicShapePath;
use Phpdftk\Css\Value\BasicShape;
use Phpdftk\Css\Value\Keyword as CssKeyword;
use Phpdftk\Css\Value\ValueList;
use Phpdftk\Css\Cascade\CalcEvaluator;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Value\Calc;
use Phpdftk\Css\ValueParser;
use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\ResourceLoader\Exception\FetchFailedException;
use Phpdftk\ResourceLoader\Exception\SsrfBlockedException;
use Phpdftk\ResourceLoader\ResourceLoader;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\GroupAttributes;
use Phpdftk\Pdf\Core\Graphics\ExtGState;
use Phpdftk\Pdf\Core\Graphics\SoftMask;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\SvgToPdf\Gradient\GradientPainter;
use Phpdftk\SvgToPdf\Text\DocumentFont;
use Phpdftk\SvgToPdf\Text\DocumentFontProvider;
use Phpdftk\SvgToPdf\Text\FontResolver;
use Phpdftk\Svg\ClipPath;
use Phpdftk\Svg\Defs;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\Image as SvgImage;
use Phpdftk\Svg\Mask;
use Phpdftk\Svg\Symbol;
use Phpdftk\Svg\Use_;
use Phpdftk\SvgToPdf\Geometry\BoundingBox;
use Phpdftk\SvgToPdf\Path\PathLengthMeasure;
use Phpdftk\Svg\Path;
use Phpdftk\Svg\Pattern;
use Phpdftk\Svg\Path\ArcTo;
use Phpdftk\Svg\Path\ClosePath;
use Phpdftk\Svg\Path\CurveTo;
use Phpdftk\Svg\Path\HorizontalLineTo;
use Phpdftk\Svg\Path\LineTo;
use Phpdftk\Svg\Path\MoveTo;
use Phpdftk\Svg\Path\PathCommand;
use Phpdftk\Svg\Path\QuadraticCurveTo;
use Phpdftk\Svg\Path\SmoothCurveTo;
use Phpdftk\Svg\Path\SmoothQuadraticCurveTo;
use Phpdftk\Svg\Path\VerticalLineTo;
use Phpdftk\Svg\Shape\Circle;
use Phpdftk\Svg\Shape\Ellipse;
use Phpdftk\Svg\Shape\Line;
use Phpdftk\Svg\Shape\Polygon;
use Phpdftk\Svg\Shape\Polyline;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Svg\Parser as SvgDocumentParser;
use Phpdftk\Svg\SvgDocument;
use Phpdftk\Svg\View as SvgView;
use Phpdftk\Svg\Text as TextNode;
use Phpdftk\Svg\Text\TextElement;
use Phpdftk\Svg\Value\Transform;
use Phpdftk\Svg\Value\TransformOrigin;
use Phpdftk\Svg\Value\Paint;
use Phpdftk\Svg\Value\Color as SvgColor;
use Phpdftk\Svg\Value\Paint\CurrentColor;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\Svg\Value\Paint\SolidColor;
use Phpdftk\Svg\Value\Paint\Url;
use Phpdftk\SvgToPdf\Path\ArcToCubic;
use Phpdftk\SvgToPdf\Path\PathPainterState;

/**
 * Translates a parsed `Phpdftk\Svg\SvgDocument` into PDF content-stream
 * operators. The translator is a thin recursive walk: each element is
 * dispatched to a per-shape painter that emits the right path and
 * `f`/`S`/`B` operator combination.
 *
 * Coordinate convention: SVG and PDF disagree on Y-axis direction (SVG
 * Y-down, PDF Y-up). The translator emits SVG coordinates verbatim — the
 * caller is responsible for setting up a PDF transformation (`cm`) that
 * flips and translates if it wants the SVG to appear at a specific PDF
 * position. Tests can paint directly into a fresh PDF stream because the
 * default user space happens to put numbers in a viewable range for small
 * SVGs.
 *
 * What 3K covers: basic shapes (`<rect>`, `<circle>`, `<ellipse>`,
 * `<line>`, `<polyline>`, `<polygon>`) and the SolidColor fill / stroke
 * paint cases. `<path>` lands in 3L, `<g>` + transforms in 3M, gradients
 * in 3O, text in 3P, use/clip/mask/image in 3Q. Until then unrecognised
 * elements are walked through transparently — their children paint as if
 * the unknown container weren't there.
 *
 * Default paint per SVG 2 §13.2.1: black fill, no stroke. The translator
 * applies that fallback when no explicit fill is set on the element.
 */
final class Translator
{
    /**
     * Cubic-Bézier "magic number" approximating a unit-circle quarter
     * arc — `(4/3) · tan(π/8) ≈ 0.5522847498`. Standard κ for
     * `<circle>` / `<ellipse>` rendering.
     */
    private const float KAPPA = 0.5522847498;

    /**
     * Longest `<pattern href>` template chain the translator follows
     * before giving up. SVG 2 places no explicit cap; this one exists
     * purely so a pathological document can't make resolution quadratic.
     */
    private const int MAX_PATTERN_TEMPLATE_DEPTH = 16;

    /**
     * How deep `<image href="…svg">` references may nest before the
     * painter stops descending. An SVG resource can reference another
     * SVG resource that references it back; SVG 2 §8.6 forbids
     * rendering such a cycle, and a hard cap is the cheapest way to
     * guarantee termination when the cycle runs through `data:` URIs
     * that are never identity-equal.
     */
    private const int MAX_EMBEDDED_SVG_DEPTH = 8;

    /**
     * SVG 2 §13.3 — the `<pattern>` attributes a referencing pattern
     * inherits from its `href` template when it does not specify them
     * itself. `href` / `xlink:href` are deliberately absent: the chain
     * is already walked, and copying them onto the merged result would
     * make it self-referential.
     *
     * @var list<string>
     */
    private const array PATTERN_INHERITED_ATTRIBUTES = [
        'x',
        'y',
        'width',
        'height',
        'patternUnits',
        'patternContentUnits',
        'patternTransform',
        'viewBox',
        'preserveAspectRatio',
    ];

    /**
     * Optional `phpdftk/resource-loader` for `http(s)://` `<image>`
     * hrefs. When `null` (the default — preserves existing call-
     * site behaviour), network hrefs drop silently per the SVG 2
     * §12.6 "no image available" outcome. When supplied, the
     * loader runs (with its SSRF guard, redirect handling, body
     * cap, and MIME sniffing) and the embedded bytes get
     * materialised to a temp file the same way `data:` URIs do.
     */
    public function __construct(
        private readonly ?ResourceLoader $resourceLoader = null,
        /**
         * Optional seam through which an embedding document (HTML, at
         * present) supplies its own font matching + PDF font handles.
         * When null - or when it declines a particular request - SVG
         * text falls back to the built-in standard-14
         * {@see FontResolver}.
         */
        private readonly ?DocumentFontProvider $documentFontProvider = null,
        /**
         * Projects a referenced document's `<style>` cascade into its
         * elements' `style` attributes before an embedded SVG is
         * painted — the referenced resource never went through
         * {@see SvgRenderer::draw}, which is where the host document
         * gets the same treatment.
         */
        private readonly SvgCascadeProjector $cascadeProjector = new SvgCascadeProjector(),
    ) {
        $this->useExpansionsInProgress = new \SplObjectStorage();
    }

    /**
     * How many `<image href="…svg">` hops led to this translator.
     * Zero for the host document; incremented for each embedded
     * resource so {@see MAX_EMBEDDED_SVG_DEPTH} can terminate cycles.
     */
    private int $embeddedSvgDepth = 0;

    /**
     * Paint a parsed SVG document into the given content stream.
     *
     * When `$page` is supplied, the painter registers an `ExtGState`
     * resource on that page for any element that carries `opacity`,
     * `fill-opacity`, or `stroke-opacity` < 1 and emits the `gs`
     * operator to invoke it. Without a `$page` reference opacity
     * attributes are silently ignored — the painter falls back to
     * fully-opaque rendering.
     *
     * When `$page` AND `$writer` are both supplied, gradient paint
     * references (`fill="url(#id)"`) resolve through the writer's
     * `PdfDoc` for shading registration and the page's
     * `useGradient` for resource attachment. Without them, gradient
     * fills fall back to no paint per SVG 2's "invalid → no paint"
     * semantics.
     */
    /**
     * @param array{w: float, h: float}|null $effectiveViewport
     *   Override viewport for inner percentage-attribute resolution
     *   when the document's own width/height/viewBox don't yield
     *   useful dimensions. Set by `SvgRenderer::draw` when it
     *   synthesises a source rect from the destination.
     * @param array{float, float, float, float, float, float}|null $baseMatrix
     *   The SVG→page base transform the caller applied to `$stream`
     *   (viewport scale + y-flip + placement); seeds the cumulative
     *   matrix stack so gradient pattern `/Matrix` entries track the CTM.
     *   Null (direct-render tests) seeds the identity.
     */
    public function paint(
        SvgDocument $document,
        ContentStream $stream,
        ?Page $page = null,
        ?PdfWriter $writer = null,
        bool $compensateTextFlip = false,
        ?array $effectiveViewport = null,
        ?array $baseMatrix = null,
    ): void {
        $this->page = $page;
        $this->writer = $writer;
        $this->document = $document;
        $this->compensateTextFlip = $compensateTextFlip;
        $this->effectiveViewport = $effectiveViewport;
        // CSS Values 4 §6.1 — `vw` / `vh` / `vmin` / `vmax` resolve
        // against the INITIAL containing block, which for an SVG
        // document is its outermost viewport. Capture it here, with
        // the nested-viewport stack empty, so a `<svg>` nested inside
        // the document can't shadow it.
        $this->viewportStack = [];
        $this->rootViewport = $this->currentViewport();
        // Seed the cumulative-transform stack with the renderer's base matrix
        // (viewport scale + y-flip + page placement) so gradient patterns can
        // reconstruct the SVG→page mapping the caller applied to the stream.
        $this->matrixStack = [$baseMatrix ?? [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]];
        $this->gradientPainter = $page !== null && $writer !== null
            ? new GradientPainter($writer, $page, $document)
            : null;
        $this->fontResolver = $page !== null && $writer !== null
            ? new FontResolver($writer, $page)
            : null;
        // SVG 2 §8.4 — `transform` applies to the OUTERMOST `<svg>` as
        // well, and transforms everything inside it. The root never
        // goes through `paintElement()`, so its own transform has to be
        // concatenated here. It composes BEFORE the viewBox origin
        // shift: the viewBox coordinate system is what the root
        // transform acts on.
        //
        // Resolved before the `try` so the `finally` that unwinds it
        // can never see an unassigned variable.
        $rootTransform = $document->transform();
        $rootMatrix = $rootTransform === null
            ? null
            : $this->transformMatrixFor($document, $rootTransform);
        try {
            if ($rootMatrix !== null) {
                $stream->saveGraphicsState();
                $stream->concatMatrix(
                    $rootMatrix[0],
                    $rootMatrix[1],
                    $rootMatrix[2],
                    $rootMatrix[3],
                    $rootMatrix[4],
                    $rootMatrix[5],
                );
                $this->pushMatrix($rootMatrix);
            }
            $viewBox = $document->viewBox();
            if (
                $baseMatrix === null
                && $viewBox !== null
                && ($viewBox[0] !== 0.0 || $viewBox[1] !== 0.0)
            ) {
                // SVG 2 §7.7 — the viewBox's `min-x`/`min-y` shift the
                // origin of the local coordinate system. The proper
                // viewBox-to-viewport mapping (with `preserveAspectRatio`)
                // needs a caller-supplied target rectangle, so it lives
                // in the 3R adapter layer; here we honour just the
                // translation so the painted content stays anchored
                // correctly relative to the viewBox.
                //
                // ONLY when the caller did not hand us a `$baseMatrix`.
                // `SvgRenderer::draw` already folds the shift into the
                // matrix it concatenates (`e = x + offsetX - minX * sx`),
                // so doing it again here translated the content a second
                // time — and UNSCALED, which pushed every document with a
                // non-zero viewBox min-x clean off the page and rendered
                // it blank.
                $stream->saveGraphicsState();
                $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, -$viewBox[0], -$viewBox[1]);
                $this->pushMatrix([1.0, 0.0, 0.0, 1.0, -$viewBox[0], -$viewBox[1]]);
                $this->paintChildren($document, $stream);
                $this->popMatrix();
                $stream->restoreGraphicsState();
                return;
            }
            $this->paintChildren($document, $stream);
        } finally {
            if ($rootMatrix !== null) {
                $this->popMatrix();
                $stream->restoreGraphicsState();
            }
            $this->page = null;
            $this->writer = null;
            $this->document = null;
            $this->gradientPainter = null;
            $this->fontResolver = null;
            $this->activeFont = null;
            $this->compensateTextFlip = false;
            $this->effectiveViewport = null;
            $this->rootViewport = null;
        }
    }

    private ?Page $page = null;
    private ?PdfWriter $writer = null;
    /** @var array{w: float, h: float}|null */
    private ?array $effectiveViewport = null;
    private ?SvgDocument $document = null;
    /**
     * Stack of nested `<svg>` viewports (viewBox units), innermost last.
     * Percentage lengths + `currentViewport()` resolve against the top.
     *
     * @var list<array{w: float, h: float}>
     */
    private array $viewportStack = [];

    /**
     * `<clipPath>` elements currently being reified into a clip region,
     * innermost last. Guards {@see emitClipRegion} against `clip-path`
     * reference cycles between clipPath elements.
     *
     * @var list<ClipPath>
     */
    private array $clipPathStack = [];
    /**
     * The document's OUTERMOST viewport — the initial containing block
     * the `vw` / `vh` / `vmin` / `vmax` units resolve against (CSS
     * Values 4 §6.1). Captured once per {@see paint()} so a nested
     * `<svg>` viewport can't shadow it the way it shadows `%`.
     *
     * @var array{w: float, h: float}|null
     */
    private ?array $rootViewport = null;
    /**
     * A `<use>`'s width/height override for the viewport element it
     * references (SVG 2 §5.6.2), consumed by the next
     * `paintViewportInstance`.
     *
     * Each axis is independent: `<use width="90">` on a 10x10
     * `<symbol>` overrides only the width, so the instance is 90x10.
     * Treating the override as all-or-nothing silently dropped the
     * single-axis form.
     *
     * @var array{w: float|null, h: float|null}|null
     */
    private ?array $pendingUseViewport = null;
    /**
     * Referenced elements whose `<use>` expansion is currently on the
     * stack, keyed by identity.
     *
     * SVG 2 §5.6.2 — a circular `<use>` reference must not be rendered.
     * The ancestor test in `paintUse` catches the direct form
     * (`<g id="a"><use href="#a"/></g>`), but two `<use>` elements can
     * also reference each other's containers, where neither referent is
     * an ancestor of its own `<use>`. This re-entrancy set closes that:
     * a referent already being painted is not painted again.
     *
     * Keyed on the REFERENT, not the `<use>`, and cleared on the way
     * out, so the same target referenced twice in sequence still expands
     * both times.
     *
     * @var \SplObjectStorage<Element, bool>
     */
    private \SplObjectStorage $useExpansionsInProgress;
    private ?GradientPainter $gradientPainter = null;
    private ?FontResolver $fontResolver = null;
    /**
     * Font selected for the `<text>` element currently being painted.
     * Set by {@see paintTextElement} before any `Tj` is emitted and read
     * by {@see showTextRun} so the shadow, single-run and per-glyph
     * paths all agree on how to encode the string.
     */
    private ?DocumentFont $activeFont = null;
    private bool $compensateTextFlip = false;
    /**
     * Stack of cumulative affine transforms (outer→inner) mapping the CURRENT
     * SVG user space to PDF page space — seeded in {@see paint()} with the
     * renderer's base matrix (viewport scale + y-flip + placement) and grown
     * by each `<svg>` viewport, element `transform`, and `<use>` translate as
     * painting descends. Its top is threaded into gradient patterns whose
     * `/Matrix` PDF resolves against DEFAULT page space (not the fill-time
     * CTM), so without this a transformed or vertically-oriented gradient
     * paints in the wrong place / orientation.
     *
     * @var non-empty-list<array{float, float, float, float, float, float}>
     */
    private array $matrixStack = [[1.0, 0.0, 0.0, 1.0, 0.0, 0.0]];

    private function paintChildren(Element $parent, ContentStream $stream): void
    {
        foreach ($parent->children as $child) {
            if ($child instanceof Element) {
                $this->paintElement($child, $stream);
            }
        }
    }

    /**
     * SVG 2 §7.5 — a nested `<svg>` establishes a new viewport from its
     * `x` / `y` / `width` / `height`, clips overflow to it, and (when it
     * carries a `viewBox`) sets up a new user coordinate system via the
     * viewBox-to-viewport scale + `preserveAspectRatio` alignment.
     */
    private function paintNestedSvg(\Phpdftk\Svg\NestedSvg $svg, ContentStream $stream): void
    {
        $this->paintViewportInstance($svg, $stream);
    }

    /**
     * Paint a viewport-establishing element — a nested `<svg>`, or the
     * instance a `<use>` generates for a `<symbol>` (SVG 2 §5.5,
     * §5.6.2). Both map their content into a rectangle and clip to it;
     * the only difference is that a `<symbol>` never paints unless a
     * `<use>` brings it here.
     */
    private function paintViewportInstance(\Phpdftk\Svg\ViewportElement $svg, ContentStream $stream): void
    {
        // A `<use>` that references this element overrides its viewport size.
        $override = $this->pendingUseViewport;
        $this->pendingUseViewport = null;
        $vp = $this->currentViewport();
        $x = $this->resolveViewportLength($svg->getAttribute('x'), $vp['w'], 0.0);
        $y = $this->resolveViewportLength($svg->getAttribute('y'), $vp['h'], 0.0);
        // Width / height: a `<use>` override wins PER AXIS; otherwise the
        // element's own attributes, defaulting to 100% of the enclosing
        // viewport.
        $w = $override['w'] ?? $this->resolveViewportLength($svg->widthAttribute(), $vp['w'], $vp['w']);
        $h = $override['h'] ?? $this->resolveViewportLength($svg->heightAttribute(), $vp['h'], $vp['h']);
        if ($w <= 0.0 || $h <= 0.0) {
            return; // A zero-sized viewport disables rendering (SVG 2 §7.5).
        }

        $stream->saveGraphicsState();
        // Position the viewport, then clip overflow to it — SVG 2 §8.2
        // puts `overflow: hidden` on viewport elements in the UA
        // stylesheet, which the author can turn off.
        $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, $x, $y);
        if (self::viewportClips($svg)) {
            $stream->rectangle(0.0, 0.0, $w, $h);
            $stream->clip();
            $stream->endPath();
        }

        $childViewport = ['w' => $w, 'h' => $h];
        $viewBox = $svg->viewBox();
        if ($viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0) {
            [$scaleX, $scaleY, $offsetX, $offsetY]
                = $this->nestedViewBoxTransform($svg, $viewBox[2], $viewBox[3], $w, $h);
            $stream->concatMatrix(
                $scaleX,
                0.0,
                0.0,
                $scaleY,
                $offsetX - $viewBox[0] * $scaleX,
                $offsetY - $viewBox[1] * $scaleY,
            );
            $childViewport = ['w' => $viewBox[2], 'h' => $viewBox[3]];
        }

        $this->viewportStack[] = $childViewport;
        $this->paintChildren($svg, $stream);
        array_pop($this->viewportStack);
        $stream->restoreGraphicsState();
    }

    /**
     * Whether a viewport element clips its content to the viewport.
     *
     * SVG 2 §8.2 — the UA stylesheet sets `overflow: hidden` on `svg`,
     * `symbol`, `image`, `marker` and `pattern`, so the DEFAULT here is
     * to clip even though CSS's own initial value is `visible`. An
     * author `overflow: visible` turns the clip off; so does `auto`,
     * which SVG defines as "the content is not clipped" rather than
     * CSS's scroll container.
     */
    private static function viewportClips(Element $element): bool
    {
        $overflow = $element->overflowValue();
        if ($overflow === null) {
            return true;
        }
        return $overflow !== 'visible' && $overflow !== 'auto';
    }

    /**
     * Resolve a nested-viewport length attribute: a `%` resolves against
     * the given viewport dimension, a plain number is taken as-is, and an
     * absent/empty value falls back to `$default`.
     */
    private function resolveViewportLength(?string $raw, float $viewport, float $default): float
    {
        if ($raw === null || trim($raw) === '') {
            return $default;
        }
        if (preg_match(
            '/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*(%|vw|vh|vmin|vmax)\s*$/i',
            $raw,
            $m,
        ) === 1) {
            return ((float) $m[1]) / 100.0 * $this->relativeLengthBasis(strtolower($m[2]), $viewport);
        }
        $plain = self::parseLengthPrefixForViewport($raw);
        return $plain ?? $default;
    }

    /**
     * Nested viewBox-to-viewport mapping in SVG user space (y-down — the
     * outer document transform applies the PDF flip). Returns
     * `[scaleX, scaleY, offsetX, offsetY]`. Mirrors the root
     * `applyPreserveAspectRatio` but without the PDF y-axis inversion,
     * since a nested `<svg>` composes inside the already-flipped space.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function nestedViewBoxTransform(
        \Phpdftk\Svg\ViewportElement $svg,
        float $srcW,
        float $srcH,
        float $dstW,
        float $dstH,
    ): array {
        return self::viewBoxTransform(
            $svg->getAttribute('preserveAspectRatio') ?? '',
            $srcW,
            $srcH,
            $dstW,
            $dstH,
        );
    }

    /**
     * viewBox-to-viewport mapping for a given `preserveAspectRatio`
     * string, in SVG user space (y-down — the outer document transform
     * applies the PDF flip). Returns `[scaleX, scaleY, offsetX,
     * offsetY]`.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function viewBoxTransform(
        string $preserveAspectRatio,
        float $srcW,
        float $srcH,
        float $dstW,
        float $dstH,
    ): array {
        $sx = $srcW > 0.0 ? $dstW / $srcW : 1.0;
        $sy = $srcH > 0.0 ? $dstH / $srcH : 1.0;
        $par = strtolower(trim($preserveAspectRatio));
        if ($par === 'none') {
            return [$sx, $sy, 0.0, 0.0];
        }
        $tokens = preg_split('/\s+/', $par) ?: [];
        $align = $tokens[0] ?? '';
        $slice = ($tokens[1] ?? 'meet') === 'slice';
        $scale = $slice ? max($sx, $sy) : min($sx, $sy);
        [$xRatio, $yRatio] = self::nestedAlignRatios($align);
        return [
            $scale,
            $scale,
            $xRatio * ($dstW - $scale * $srcW),
            $yRatio * ($dstH - $scale * $srcH),
        ];
    }

    /**
     * The affine matrix for an element's `transform`, pivoted about its
     * `transform-origin` (CSS Transforms 1 §6).
     *
     * SVG elements have no associated CSS layout box, so the initial
     * `transform-origin` is `0 0` — the plain `transform` matrix — and
     * only an explicit value costs a bounding-box computation.
     *
     * @return array{float, float, float, float, float, float}
     */
    private function transformMatrixFor(Element $element, Transform $transform): array
    {
        $raw = $element->transformOrigin();
        $boxKeyword = $element->transformBox();
        if ($raw === null && $boxKeyword === null) {
            return $transform->toMatrix();
        }
        $box = $this->referenceBox($element, $boxKeyword);
        if ($box === null) {
            return $transform->toMatrix();
        }
        if ($raw === null) {
            // An explicit `transform-box` with no `transform-origin`
            // pivots on the reference box's own origin: SVG elements
            // have no CSS layout box, so their used initial
            // `transform-origin` is `0 0` rather than `50% 50%`.
            return $transform->toMatrixAbout($box[0], $box[1]);
        }
        $origin = TransformOrigin::parse($raw);
        if ($origin === null) {
            return $boxKeyword === null
                ? $transform->toMatrix()
                : $transform->toMatrixAbout($box[0], $box[1]);
        }
        [$ox, $oy] = $origin->resolve(
            $box[0],
            $box[1],
            $box[2],
            $box[3],
        );
        return $transform->toMatrixAbout($ox, $oy);
    }

    /**
     * The reference box `transform-origin` resolves against
     * (CSS Transforms 1 §7), as `[x, y, width, height]` in user units.
     *
     * `view-box` is the nearest SVG viewport; `fill-box` is the object
     * bounding box; `stroke-box` is that box grown by half the stroke
     * on each side. For SVG elements — which have no CSS layout box —
     * `content-box` behaves as `fill-box` and `border-box` as
     * `stroke-box`.
     *
     * A null `$keyword` means no `transform-box` was specified, which
     * takes the initial value, `view-box`.
     *
     * @return array{float, float, float, float}|null
     */
    private function referenceBox(Element $element, ?string $keyword): ?array
    {
        if ($keyword === null || $keyword === 'view-box') {
            $viewport = $this->currentViewport();
            return [0.0, 0.0, $viewport['w'], $viewport['h']];
        }
        $bbox = BoundingBox::compute($element);
        if ($bbox === null) {
            return null;
        }
        $box = [$bbox['minX'], $bbox['minY'], $bbox['width'], $bbox['height']];
        if ($keyword === 'stroke-box' || $keyword === 'border-box') {
            $half = ($element->strokeWidth() ?? 1.0) / 2.0;
            $box = [
                $box[0] - $half,
                $box[1] - $half,
                $box[2] + $half * 2.0,
                $box[3] + $half * 2.0,
            ];
        }
        return $box;
    }

    /**
     * `preserveAspectRatio` align keyword → `[xRatio, yRatio]` leftover
     * fractions, in SVG y-down space (`yMin` → 0, `yMid` → 0.5,
     * `yMax` → 1). Defaults to `xMidYMid`.
     *
     * @return array{0: float, 1: float}
     */
    private static function nestedAlignRatios(string $align): array
    {
        $align = $align === '' ? 'xmidymid' : $align;
        $xRatio = str_contains($align, 'xmax') ? 1.0 : (str_contains($align, 'xmin') ? 0.0 : 0.5);
        $yRatio = str_contains($align, 'ymax') ? 1.0 : (str_contains($align, 'ymin') ? 0.0 : 0.5);
        return [$xRatio, $yRatio];
    }

    private function paintElement(Element $element, ContentStream $stream): void
    {
        // SVG 2 §5.8 — conditional processing gates EVERY direct
        // rendering element, not only `<switch>` branches. An element
        // whose conditions evaluate false is not rendered, and neither
        // are its children.
        if (!$this->switchChildPasses($element)) {
            return;
        }
        // Any of these scope-leaking attributes triggers a `q`/`Q` wrap
        // so the state doesn't leak across siblings:
        //
        //   - transform: emits `cm`.
        //   - opacity / fill-opacity / stroke-opacity (< 1): emits `gs`.
        //   - stroke params (w / J / j / M / d): each emits its own op.
        //   - clip-path: emits the clip region inside the same wrap.
        //   - mask: emits `gs` referencing a SMask-bearing ExtGState.
        //
        // Painting the same shape with all defaults stays a one-shot
        // op stream — no overhead when none of the above is set.
        $transform = $element->transform();
        $opacityGs = $this->resolveOpacityState($element);
        $needsStrokeParams = $this->needsStrokeParams($element);
        $clipPath = $this->resolveClipPath($element);
        $shapeClip = $clipPath === null ? $this->resolveShapeClipPath($element) : null;
        $maskGs = $this->resolveMaskState($element);
        $needsWrap = $transform !== null
            || $opacityGs !== null
            || $needsStrokeParams
            || $clipPath !== null
            || $shapeClip !== null
            || $maskGs !== null;

        if (!$needsWrap) {
            $this->dispatchElement($element, $stream);
            return;
        }

        $stream->saveGraphicsState();
        if ($transform !== null) {
            $matrix = $this->transformMatrixFor($element, $transform);
            $stream->concatMatrix(
                $matrix[0],
                $matrix[1],
                $matrix[2],
                $matrix[3],
                $matrix[4],
                $matrix[5],
            );
            // Track the transform so gradient fills inside this element (and
            // its children) anchor their pattern to the same CTM the geometry
            // is painted under.
            $this->pushMatrix($matrix);
        }
        if ($opacityGs !== null) {
            $stream->setGraphicsState($opacityGs);
        }
        if ($needsStrokeParams) {
            $this->applyStrokeParams($element, $stream);
        }
        if ($clipPath !== null) {
            $this->applyClipPath($clipPath, $element, $stream);
        }
        if ($shapeClip !== null) {
            self::emitShapeClip($shapeClip, $stream);
        }
        if ($maskGs !== null) {
            $stream->setGraphicsState($maskGs);
        }
        $this->dispatchElement($element, $stream);
        if ($transform !== null) {
            $this->popMatrix();
        }
        $stream->restoreGraphicsState();
    }

    /**
     * Resolve `mask="url(#id)"` to a registered `ExtGState` whose
     * `/SMask` references a Form XObject containing the mask's
     * painted children. Returns the resource name to invoke via
     * `gs`, or null when no mask is set / can't be resolved.
     *
     * Pipeline:
     *
     *   1. Paint the `<mask>`'s children into a new `ContentStream`
     *      (running the full Translator pipeline so gradients, fonts,
     *      images, etc. all register on the host page).
     *   2. Wrap the resulting bytes in a Form XObject. `/Group /S
     *      Transparency /CS DeviceGray` so the form's pixels become an
     *      alpha channel via their luminance.
     *   3. Build a `SoftMask` dict with `/S Luminosity` and `/BC [0]`
     *      so the backdrop outside the mask region is black (hidden).
     *   4. Drop the SMask in an `ExtGState`, register, and attach to
     *      the page's resources under a stable name.
     *
     * Implemented:
     *
     *   - `maskContentUnits = objectBoundingBox` applies a bbox `cm`
     *     to the mask content stream so authored coords are in [0, 1].
     *   - SVG 2 §14.5.4 defaults are honoured: bbox-mode defaults
     *     `(-10%, -10%, 120%, 120%)` so the mask reaches a hair
     *     beyond the painted geometry; userspace-mode defaults to
     *     the masked element's own bbox.
     *   - Explicit `x` / `y` / `width` / `height` attributes on
     *     the `<mask>` element override the defaults.
     */
    private function resolveMaskState(Element $element): ?string
    {
        if ($this->writer === null
            || $this->page === null
            || $this->document === null
        ) {
            return null;
        }
        $raw = $element->maskValue();
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === 'none') {
            return null;
        }
        if (preg_match('/^url\(\s*[\x22\x27]?#([^)\s\x22\x27]+)[\x22\x27]?\s*\)/i', $trimmed, $m) !== 1) {
            return null;
        }
        $referent = $this->document->findByFragment($m[1]);
        if (!$referent instanceof Mask) {
            return null;
        }
        $elementBbox = BoundingBox::compute($element);
        if ($elementBbox === null) {
            return null;
        }
        return $this->buildMaskState($referent, $elementBbox, null, null);
    }

    /**
     * CSS Masking 1 §4 — build the soft-mask `ExtGState` for a `<mask>`
     * element referenced from OUTSIDE the SVG pipeline (an HTML element's
     * `mask-image: url(#id)`), and return its resource name.
     *
     * `$elementBbox` is the masked element's bounding box in the mask's
     * own user space — for an HTML element that space has its origin at
     * the top-left of the border box with y running down, so the bbox is
     * `(0, 0, w, h)`. `$baseMatrix` maps that space onto the page and is
     * prepended to the mask's content stream (the soft mask is evaluated
     * under the CTM in force when its `gs` runs, and the HTML painter
     * paints under the identity CTM). `$subtypeOverride` carries CSS
     * `mask-mode`, which wins over the element's `mask-type`.
     *
     * @param array{minX: float, minY: float, width: float, height: float} $elementBbox
     * @param array{float, float, float, float, float, float}|null $baseMatrix
     */
    public function registerMaskState(
        SvgDocument $document,
        Mask $mask,
        Page $page,
        PdfWriter $writer,
        array $elementBbox,
        ?array $baseMatrix = null,
        ?string $subtypeOverride = null,
    ): ?string {
        $previous = [$this->document, $this->page, $this->writer, $this->gradientPainter, $this->fontResolver, $this->matrixStack];
        $this->document = $document;
        $this->page = $page;
        $this->writer = $writer;
        $this->gradientPainter = new GradientPainter($writer, $page, $document);
        $this->fontResolver = new FontResolver($writer, $page);
        $this->matrixStack = [$baseMatrix ?? [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]];
        try {
            return $this->buildMaskState($mask, $elementBbox, $baseMatrix, $subtypeOverride);
        } finally {
            [$this->document, $this->page, $this->writer, $this->gradientPainter, $this->fontResolver, $this->matrixStack] = $previous;
        }
    }

    /**
     * Shared body of {@see resolveMaskState} / {@see registerMaskState}.
     *
     * @param array{minX: float, minY: float, width: float, height: float} $elementBbox
     * @param array{float, float, float, float, float, float}|null $baseMatrix
     */
    private function buildMaskState(
        Mask $referent,
        array $elementBbox,
        ?array $baseMatrix,
        ?string $subtypeOverride,
    ): ?string {
        if ($this->writer === null || $this->page === null) {
            return null;
        }
        $region = self::computeMaskRegion($referent, $elementBbox);

        $maskStream = new ContentStream();
        if ($baseMatrix !== null) {
            $maskStream->concatMatrix(...$baseMatrix);
        }
        if ($referent->maskContentUnits() === 'objectBoundingBox') {
            // Reify mask children's [0, 1] coords against the masked
            // element's bbox (the same reference frame the masked
            // element's geometry inhabits — not the mask region).
            $maskStream->concatMatrix(
                $elementBbox['width'],
                0.0,
                0.0,
                $elementBbox['height'],
                $elementBbox['minX'],
                $elementBbox['minY'],
            );
        }
        foreach ($referent->children as $child) {
            if ($child instanceof Element) {
                $this->paintElement($child, $maskStream);
            }
        }
        $operatorBytes = implode("\n", $maskStream->getOperators());
        // The BBox lives in the form's OWN space, i.e. before the content
        // stream's own `cm`. Map the user-space region through the base
        // matrix so an externally-supplied mapping is accounted for.
        $corners = [
            [$region['minX'], $region['minY']],
            [$region['minX'] + $region['width'], $region['minY'] + $region['height']],
        ];
        if ($baseMatrix !== null) {
            [$a, $b, $c, $d, $e, $f] = $baseMatrix;
            foreach ($corners as $i => [$cx, $cy]) {
                $corners[$i] = [$a * $cx + $c * $cy + $e, $b * $cx + $d * $cy + $f];
            }
        }
        $form = new FormXObject(
            new PdfArray([
                new PdfNumber(min($corners[0][0], $corners[1][0])),
                new PdfNumber(min($corners[0][1], $corners[1][1])),
                new PdfNumber(max($corners[0][0], $corners[1][0])),
                new PdfNumber(max($corners[0][1], $corners[1][1])),
            ]),
            $operatorBytes,
        );
        // Transparency group with the DeviceGray colour space so the
        // luminance of the painted pixels becomes the mask's alpha.
        $group = new GroupAttributes('Transparency');
        $group->cs = new PdfName('DeviceGray');
        $form->group = $group;
        $this->writer->register($form);

        // SVG 2 §14.5: `mask-type="alpha"` uses the painted pixels'
        // alpha channel directly; the default `luminance` (also
        // covering the old `mask-type` absent case) uses the
        // luminance of the RGB pixels. PDF maps these to the SoftMask
        // `/S /Alpha` and `/S /Luminosity` modes.
        $maskSubtype = strtolower(trim($referent->getAttribute('mask-type') ?? ''));
        $smask = new SoftMask(
            $subtypeOverride ?? ($maskSubtype === 'alpha' ? 'Alpha' : 'Luminosity'),
            new PdfReference($form->objectNumber),
        );
        // Backdrop colour black ([0]) so anywhere the mask content
        // doesn't paint stays hidden — matches SVG 2's "outside the
        // mask region the alpha is 0" semantic.
        $smask->bc = new PdfArray([new PdfNumber(0)]);

        $gstate = new ExtGState();
        $gstate->sMask = $smask;
        $this->writer->register($gstate);

        $name = 'GS_mask_' . $gstate->objectNumber;
        $resources = $this->page->corePage()->resources;
        if ($resources !== null) {
            $resources->extGState[$name] = new PdfReference($gstate->objectNumber);
        }
        return $name;
    }

    /**
     * Compute the rectangular region that the mask covers, in user
     * space. SVG 2 §14.5.4 defaults:
     *
     *  - `maskUnits="objectBoundingBox"` (default): `x=-10%, y=-10%,
     *    width=120%, height=120%` of the masked element's bounding
     *    box. The 10% pad on each side is what makes the mask
     *    naturally reach a hair beyond the painted geometry so
     *    anti-aliased edges aren't clipped.
     *  - `maskUnits="userSpaceOnUse"`: SVG defaults to the viewport
     *    rect (-10% etc. of the viewport), which we don't have direct
     *    access to here — fall back to the masked element's bbox
     *    (matches 3R+8 behaviour for unset attributes).
     *
     * @param array{minX: float, minY: float, width: float, height: float} $elementBbox
     * @return array{minX: float, minY: float, width: float, height: float}
     */
    private static function computeMaskRegion(Mask $mask, array $elementBbox): array
    {
        $bboxMode = $mask->maskUnits() === 'objectBoundingBox';

        if ($bboxMode) {
            $x = $mask->x() ?? -0.1;
            $y = $mask->y() ?? -0.1;
            $w = $mask->width() ?? 1.2;
            $h = $mask->height() ?? 1.2;
            return [
                'minX' => $elementBbox['minX'] + $x * $elementBbox['width'],
                'minY' => $elementBbox['minY'] + $y * $elementBbox['height'],
                'width' => $w * $elementBbox['width'],
                'height' => $h * $elementBbox['height'],
            ];
        }

        return [
            'minX' => $mask->x() ?? $elementBbox['minX'],
            'minY' => $mask->y() ?? $elementBbox['minY'],
            'width' => $mask->width() ?? $elementBbox['width'],
            'height' => $mask->height() ?? $elementBbox['height'],
        ];
    }

    /**
     * CSS Masking 1 §6 — `clip-path: <basic-shape> || <geometry-box>` on
     * an SVG graphics element. Returns the resolved outline (in the
     * element's user space, y down) or null when the element has no such
     * clip-path, the shape is one we don't model, or the reference box
     * can't be computed.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}|null
     */
    private function resolveShapeClipPath(Element $element): ?array
    {
        $raw = $element->clipPathValue();
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === 'none' || str_starts_with(strtolower($trimmed), 'url(')) {
            return null;
        }
        try {
            $value = (new ValueParser())->parseFromString($trimmed);
        } catch (\Throwable) {
            return null;
        }
        // `<basic-shape> || <geometry-box>` parses as a ValueList in either
        // order; a bare shape parses as itself. A bare `<geometry-box>`
        // clips to that box's edge.
        $shape = null;
        $refBox = 'border-box';
        $geometryBoxes = [
            'content-box', 'padding-box', 'border-box', 'margin-box',
            'fill-box', 'stroke-box', 'view-box',
        ];
        if ($value instanceof ValueList) {
            foreach ($value->values as $part) {
                if ($part instanceof BasicShape) {
                    $shape = $part;
                } elseif ($part instanceof CssKeyword
                    && in_array(strtolower($part->name), $geometryBoxes, true)
                ) {
                    $refBox = strtolower($part->name);
                }
            }
            if ($shape === null) {
                return null;
            }
        } elseif ($value instanceof BasicShape) {
            $shape = $value;
        } elseif ($value instanceof CssKeyword
            && in_array(strtolower($value->name), $geometryBoxes, true)
        ) {
            $refBox = strtolower($value->name);
        } else {
            // Not a shape, not a geometry box — an unparseable or unknown
            // value. CSS Masking 1 §6.1 leaves the element unclipped.
            return null;
        }
        $box = $this->clipReferenceBox($element, $refBox);
        if ($box === null || $box['width'] <= 0.0 || $box['height'] <= 0.0) {
            return null;
        }
        if ($shape === null) {
            // Bare `<geometry-box>`: the box's own edge is the clip.
            return [
                'fillRule' => 'nonzero',
                'commands' => [
                    ['M', $box['minX'], $box['minY']],
                    ['L', $box['minX'] + $box['width'], $box['minY']],
                    ['L', $box['minX'] + $box['width'], $box['minY'] + $box['height']],
                    ['L', $box['minX'], $box['minY'] + $box['height']],
                    ['Z'],
                ],
            ];
        }
        return BasicShapePath::build(
            $shape,
            $box['minX'],
            $box['minY'],
            $box['width'],
            $box['height'],
        );
    }

    /**
     * CSS Masking 1 §6 / CSS Box 3 — the `<geometry-box>` an SVG element's
     * basic shape is measured against, in the element's user space.
     *
     * An SVG graphics element has no CSS layout box, so `content-box`,
     * `padding-box`, `border-box` and `margin-box` all reduce to
     * `fill-box` — its object bounding box. `stroke-box` grows that by
     * half the stroke width on every side, and `view-box` is the nearest
     * SVG viewport, anchored at the user-space origin.
     *
     * @return array{minX: float, minY: float, width: float, height: float}|null
     */
    private function clipReferenceBox(Element $element, string $refBox): ?array
    {
        if ($refBox === 'view-box') {
            $viewport = $this->currentViewport();
            if ($viewport['w'] <= 0.0 || $viewport['h'] <= 0.0) {
                return null;
            }
            return [
                'minX' => 0.0,
                'minY' => 0.0,
                'width' => $viewport['w'],
                'height' => $viewport['h'],
            ];
        }
        return BoundingBox::compute($element, includeStroke: $refBox === 'stroke-box');
    }

    /**
     * Emit a resolved basic-shape outline as a PDF clipping path. The
     * commands are already in the element's user space, which is the space
     * the stream is painting in, so they go through verbatim.
     *
     * @param array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>} $outline
     */
    private static function emitShapeClip(array $outline, ContentStream $stream): void
    {
        foreach ($outline['commands'] as $command) {
            switch ($command[0]) {
                case 'M':
                    $stream->moveTo((float) $command[1], (float) $command[2]);
                    break;
                case 'L':
                    $stream->lineTo((float) $command[1], (float) $command[2]);
                    break;
                case 'C':
                    $stream->curveTo(
                        (float) $command[1],
                        (float) $command[2],
                        (float) $command[3],
                        (float) $command[4],
                        (float) $command[5],
                        (float) $command[6],
                    );
                    break;
                default:
                    $stream->closePath();
                    break;
            }
        }
        if ($outline['fillRule'] === 'evenodd') {
            $stream->clipEvenOdd();
        } else {
            $stream->clip();
        }
        $stream->endPath();
    }

    /**
     * Resolve an element's `clip-path="url(#id)"` reference to the
     * matching `<clipPath>` element. Returns null when no clip-path is
     * set, the reference can't be parsed, or the id doesn't resolve to
     * a clipPath — each case falls through to the unclipped paint per
     * SVG 2's "invalid → no clip" rule.
     */
    private function resolveClipPath(Element $element): ?ClipPath
    {
        if ($this->document === null) {
            return null;
        }
        $raw = $element->clipPathValue();
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === 'none') {
            return null;
        }
        if (preg_match('/^url\(\s*[\x22\x27]?#([^)\s\x22\x27]+)[\x22\x27]?\s*\)/i', $trimmed, $m) !== 1) {
            return null;
        }
        $referent = $this->document->findByFragment($m[1]);
        return $referent instanceof ClipPath ? $referent : null;
    }

    /**
     * Construct the PDF clipping region from the `<clipPath>` element's
     * geometry and emit `W`/`W*` + `n` so subsequent painting is
     * scoped to it. `clipPathUnits="objectBoundingBox"` is honoured by
     * sandwiching the path construction between a bbox-space `cm` and
     * its inverse — the clip region is "frozen" by `W` in user space,
     * so applying the inverse `cm` returns the CTM to its pre-clip
     * state without disturbing the established region.
     */
    private function applyClipPath(ClipPath $clipPath, Element $element, ContentStream $stream): void
    {
        $useBbox = $clipPath->clipPathUnits() === 'objectBoundingBox';
        $bbox = $useBbox ? BoundingBox::compute($element) : null;
        if ($useBbox && $bbox === null) {
            // bbox required for objectBoundingBox mode but unavailable
            // (e.g. `<path>` element with no bbox helper at 3R+3) —
            // fall back to no clip rather than emitting a broken clip.
            return;
        }

        $this->emitClipRegion(
            $clipPath,
            $stream,
            $bbox === null
                ? null
                : [$bbox['width'], 0.0, 0.0, $bbox['height'], $bbox['minX'], $bbox['minY']],
        );
    }

    /**
     * CSS Masking 1 §6.1 — emit the clipping region a `<clipPath>`
     * describes into `$stream` for a caller OUTSIDE the SVG pipeline
     * (the HTML painter's `clip-path: url(#id)`).
     *
     * The caller owns the coordinate system and the surrounding
     * `q` / `Q`: it must already have concatenated the matrix that maps
     * the clipPath's user space onto the page. On return the CTM is
     * unchanged and the clip region is established, so the caller can
     * keep painting in its own space.
     *
     * `$objectBoundingBox` is the referencing element's bounding box
     * `[x, y, width, height]` **in that user space**, used only when the
     * clipPath declares `clipPathUnits="objectBoundingBox"`; pass null
     * to force `userSpaceOnUse`. `$viewport` resolves percentage lengths
     * on the clipPath's children (SVG 2 §10.3) — for an HTML element
     * that is the initial viewport, not any SVG viewport.
     *
     * @param array{w: float, h: float} $viewport
     * @param array{0: float, 1: float, 2: float, 3: float}|null $objectBoundingBox
     */
    public function emitClipPathRegion(
        SvgDocument $document,
        ClipPath $clipPath,
        ContentStream $stream,
        array $viewport,
        ?array $objectBoundingBox = null,
    ): void {
        $useBbox = $clipPath->clipPathUnits() === 'objectBoundingBox';
        if ($useBbox && $objectBoundingBox === null) {
            return;
        }
        $previousDocument = $this->document;
        $this->document = $document;
        $this->viewportStack[] = $viewport;
        try {
            $this->emitClipRegion(
                $clipPath,
                $stream,
                $useBbox && $objectBoundingBox !== null
                    ? [
                        $objectBoundingBox[2],
                        0.0,
                        0.0,
                        $objectBoundingBox[3],
                        $objectBoundingBox[0],
                        $objectBoundingBox[1],
                    ]
                    : null,
            );
        } finally {
            array_pop($this->viewportStack);
            $this->document = $previousDocument;
        }
    }

    /**
     * Shared body of {@see applyClipPath} / {@see emitClipPathRegion}:
     * build the clipPath children's geometry under `$bboxMatrix` (the
     * `objectBoundingBox` reification, null for `userSpaceOnUse`) plus
     * the clipPath's own `transform`, then freeze it with `W`/`W*` + `n`.
     *
     * @param array{float, float, float, float, float, float}|null $bboxMatrix
     */
    private function emitClipRegion(
        ClipPath $clipPath,
        ContentStream $stream,
        ?array $bboxMatrix,
    ): void {
        // CSS Masking 1 §6.1 / SVG 2 §14.4 — `clip-path` ON the
        // `<clipPath>` element itself further restricts the region it
        // describes: the effective clip is the INTERSECTION of this
        // clipPath's child geometry with the region its own reference
        // describes. PDF clipping is already intersective (successive
        // `W`/`n` pairs narrow the region), so emitting the referenced
        // region first and this clipPath's geometry second composes
        // correctly without any explicit path intersection.
        //
        // The nested region is emitted BEFORE this clipPath's bbox /
        // transform `cm`s, so it resolves in the user space of the
        // element that referenced us — which is where a nested
        // clipPath's own coordinates live. `$bboxMatrix` is threaded
        // through unchanged so an `objectBoundingBox` nested clipPath
        // reifies against the same referencing element's bbox.
        //
        // `$clipPathStack` breaks reference cycles (a clipPath that
        // reaches itself); per SVG 2's "invalid → no clip" rule the
        // self-reference is dropped rather than failing the element.
        $nested = $this->resolveClipPath($clipPath);
        if ($nested !== null && $nested !== $clipPath && !in_array($nested, $this->clipPathStack, true)) {
            $this->clipPathStack[] = $clipPath;
            try {
                $this->emitClipRegion($nested, $stream, $bboxMatrix);
            } finally {
                array_pop($this->clipPathStack);
            }
        }

        $clipTransform = $clipPath->transform()?->toMatrix();

        // Apply outer→inner: bbox first, then the clipPath's own
        // `transform`. Children paint in their authored coordinate
        // system; the cm composition reifies that into user space
        // for the W operator to capture.
        if ($bboxMatrix !== null) {
            $stream->concatMatrix(...$bboxMatrix);
        }
        if ($clipTransform !== null) {
            $stream->concatMatrix(...$clipTransform);
        }
        $before = count($stream->getOperators());
        foreach ($clipPath->children as $child) {
            if ($child instanceof Element) {
                $this->emitElementPath($child, $stream);
            }
        }
        if (count($stream->getOperators()) === $before) {
            // SVG 2 §14.4.1 / CSS Masking 1 §6.1 — a `<clipPath>` whose
            // children contribute no geometry (it is empty, or holds only
            // non-area-enclosing elements) clips EVERYTHING away: the
            // referencing element disappears. `W` with no current path is
            // undefined in PDF and consumers treat it as a no-op, which
            // would leave the element fully visible instead — so emit a
            // zero-extent rectangle to make the empty region explicit.
            $stream->rectangle(0.0, 0.0, 0.0, 0.0);
        }
        $rule = self::resolveClipRule($clipPath);
        if ($rule === 'evenodd') {
            $stream->clipEvenOdd();
        } else {
            $stream->clip();
        }
        $stream->endPath();
        // Undo the cms in reverse order so the CTM is back to the
        // pre-clip user space. This has to happen AFTER `W`/`n`: ISO
        // 32000-2 §8.2 admits only path-construction operators inside a
        // path object, so a `cm` between the path and its painting
        // operator makes the content stream malformed and consumers drop
        // the clip (an `objectBoundingBox` clipPath then either vanished
        // or failed to clip at all). Reordering is safe — the region
        // `W` establishes already lives in device space, so it survives
        // the CTM changes that follow.
        if ($clipTransform !== null) {
            $stream->concatMatrix(...self::inverseAffine($clipTransform));
        }
        if ($bboxMatrix !== null) {
            $stream->concatMatrix(...self::inverseAffine($bboxMatrix));
        }
    }

    /**
     * Inverse of a 3×2 affine matrix in PDF `[a b c d e f]` order. The
     * full 3×3 affine has bottom row `[0 0 1]` so we only need the six
     * SVG/PDF entries. Singular matrices (det == 0) shouldn't occur
     * for any transform we generate; if one does, returning identity
     * makes the worst-case behaviour "no inverse applied" rather than
     * a divide-by-zero.
     *
     * The top of {@see matrixStack} — the cumulative SVG→page transform in
     * effect for the element currently being painted.
     *
     * @return array{float, float, float, float, float, float}
     */
    private function currentMatrix(): array
    {
        return $this->matrixStack[count($this->matrixStack) - 1];
    }

    /**
     * Concatenate `$m` onto the current cumulative matrix (outer × inner, the
     * same order a `cm` operator composes onto the CTM) and push the result.
     * Callers must {@see popMatrix} when the corresponding graphics-state save
     * is restored.
     *
     * @param array{float, float, float, float, float, float} $m
     */
    private function pushMatrix(array $m): void
    {
        $this->matrixStack[] = self::multiplyAffine($this->currentMatrix(), $m);
    }

    private function popMatrix(): void
    {
        if (count($this->matrixStack) > 1) {
            array_pop($this->matrixStack);
        }
    }

    /**
     * Affine composition `m1 × m2` (m1 applied after m2), matching the PDF
     * `cm` convention and {@see \Phpdftk\Svg\Value\Transform}.
     *
     * @param array{float, float, float, float, float, float} $m1
     * @param array{float, float, float, float, float, float} $m2
     * @return array{float, float, float, float, float, float}
     */
    private static function multiplyAffine(array $m1, array $m2): array
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
     * @param array{float, float, float, float, float, float} $m
     * @return array{float, float, float, float, float, float}
     */
    private static function inverseAffine(array $m): array
    {
        [$a, $b, $c, $d, $e, $f] = $m;
        $det = $a * $d - $b * $c;
        if (abs($det) < 1.0e-12) {
            return [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        }
        return [
            $d / $det,
            -$b / $det,
            -$c / $det,
            $a / $det,
            ($c * $f - $d * $e) / $det,
            ($b * $e - $a * $f) / $det,
        ];
    }

    /**
     * `clip-rule` per SVG 2 §14.4.4. The attribute lives on the
     * `<clipPath>` element itself at 3R+3; per-child clip-rule support
     * (the spec allows overriding on individual children) lands later.
     *
     * @return 'nonzero'|'evenodd'
     */
    private static function resolveClipRule(ClipPath $clipPath): string
    {
        $raw = $clipPath->getAttribute('clip-rule');
        return strtolower(trim($raw ?? '')) === 'evenodd' ? 'evenodd' : 'nonzero';
    }

    /**
     * Emit just the geometry of an element — the path operators that
     * would have ended up before the fill/stroke op. Used by
     * `applyClipPath` to assemble a clipping region without painting.
     */
    private function emitElementPath(Element $element, ContentStream $stream): void
    {
        match (true) {
            $element instanceof Rect => $this->emitRectPath($element, $stream),
            $element instanceof Circle => $this->emitCirclePath($element, $stream),
            $element instanceof Ellipse => $this->emitEllipsePathFor($element, $stream),
            $element instanceof Polyline => $this->emitPolylinePath($element, $stream),
            $element instanceof Polygon => $this->emitPolygonPath($element, $stream),
            $element instanceof Path => $this->emitPathPath($element, $stream),
            // `<line>` and other non-area-enclosing children don't
            // contribute to a clip region per SVG 2 §14.4.1.
            default => null,
        };
    }

    /** @return bool whether any geometry was emitted. */
    private function emitRectPath(Rect $rect, ContentStream $stream): bool
    {
        $vp = $this->currentViewport();
        $x = $this->geometryLength($rect, 'x', $vp['w']) ?? 0.0;
        $y = $this->geometryLength($rect, 'y', $vp['h']) ?? 0.0;
        // A negative `width` / `height` is invalid, so the declaration
        // is ignored and the initial value 0 stands — nothing paints,
        // which the `> 0.0` guard already expresses.
        $w = $this->geometryLength($rect, 'width', $vp['w']) ?? 0.0;
        $h = $this->geometryLength($rect, 'height', $vp['h']) ?? 0.0;
        if ($w <= 0.0 || $h <= 0.0) {
            return false;
        }
        [$rx, $ry] = $this->rectCornerRadii($rect, $w, $h, $vp);
        if ($rx <= 0.0 || $ry <= 0.0) {
            $stream->rectangle($x, $y, $w, $h);
            return true;
        }
        self::emitRoundedRectPath($stream, $x, $y, $w, $h, $rx, $ry);
        return true;
    }

    /**
     * SVG 2 §10.4 — the used `rx` / `ry` of a `<rect>`.
     *
     * Both radii are `auto` by default, and an `auto` radius mirrors
     * the other one, so `rx="8"` alone rounds both axes by 8. A
     * NEGATIVE radius is invalid: the declaration is ignored, which
     * leaves `auto` in force. Percentages are §10.1 geometry
     * percentages — `rx` against the viewport WIDTH, `ry` against its
     * HEIGHT, never against the rectangle's own box. Finally each
     * radius is clamped to half its side, so an over-large radius
     * degenerates into a stadium instead of self-intersecting.
     *
     * @param array{w: float, h: float} $vp
     * @return array{float, float}
     */
    private function rectCornerRadii(Rect $rect, float $w, float $h, array $vp): array
    {
        $rx = $this->geometryLength($rect, 'rx', $vp['w']);
        $ry = $this->geometryLength($rect, 'ry', $vp['h']);
        if ($rx !== null && $rx < 0.0) {
            $rx = null;
        }
        if ($ry !== null && $ry < 0.0) {
            $ry = null;
        }
        $rx ??= $ry;
        $ry ??= $rx;
        if ($rx === null || $ry === null) {
            return [0.0, 0.0];
        }
        return [min($rx, $w / 2.0), min($ry, $h / 2.0)];
    }

    /**
     * A rounded rectangle as four straight edges joined by four
     * quarter-ellipse corners.
     *
     * PDF's `re` operator only draws square corners, so this is the
     * lowering. Corner arcs reuse the same `KAPPA` cubic approximation
     * `emitEllipsePath()` uses, which keeps a fully-rounded rect
     * (`rx = w/2`, `ry = h/2`) pixel-consistent with the `<ellipse>`
     * that describes the same shape.
     */
    private static function emitRoundedRectPath(
        ContentStream $stream,
        float $x,
        float $y,
        float $w,
        float $h,
        float $rx,
        float $ry,
    ): void {
        $kx = $rx * self::KAPPA;
        $ky = $ry * self::KAPPA;
        $right = $x + $w;
        $top = $y + $h;
        $stream
            ->moveTo($x + $rx, $y)
            ->lineTo($right - $rx, $y)
            ->curveTo($right - $rx + $kx, $y, $right, $y + $ry - $ky, $right, $y + $ry)
            ->lineTo($right, $top - $ry)
            ->curveTo($right, $top - $ry + $ky, $right - $rx + $kx, $top, $right - $rx, $top)
            ->lineTo($x + $rx, $top)
            ->curveTo($x + $rx - $kx, $top, $x, $top - $ry + $ky, $x, $top - $ry)
            ->lineTo($x, $y + $ry)
            ->curveTo($x, $y + $ry - $ky, $x + $rx - $kx, $y, $x + $rx, $y)
            ->closePath();
    }

    /**
     * The 1%-basis for a relative length unit: the supplied viewport
     * extent for `%`, and the document's OWN outermost viewport for the
     * `v*` units. An embedded resource's outermost viewport is the
     * `<image>` box it was given, which is exactly what makes `50vw`
     * inside an `<image href="…svg">` mean half that box.
     */
    /**
     * Evaluate a CSS math function (`calc()`, `min()`, `max()`,
     * `clamp()`, …) that appeared in a geometry attribute, in CSS px.
     *
     * Percentages resolve against `$basis`, which is the SAME per-axis
     * basis the plain-length path uses — the viewport width for `x` /
     * `width`, its height for `y` / `height`, and the normalized
     * diagonal for a circle's `r` (§10.1). `em` resolves against the
     * element's own computed `font-size`, which the cascade projection
     * has already put in reach.
     *
     * Returns null when the expression can't be reduced to a number —
     * an unsupported function, or a percentage with no basis. That
     * reads as an invalid declaration, so the caller falls back to the
     * property's initial value rather than to a half-evaluated one.
     */
    private function evaluateMathFunction(string $raw, Element $element, float $basis): ?float
    {
        $parsed = (new ValueParser())->parseFromString($raw);
        if (!$parsed instanceof Calc) {
            return null;
        }
        $root = $this->rootViewport ?? $this->currentViewport();
        $fontSize = $element->fontSize() ?? 16.0;
        $pixels = CalcEvaluator::evaluate($parsed, new LengthContext(
            parentFontSize: $fontSize,
            currentFontSize: $fontSize,
            viewportWidth: $root['w'],
            viewportHeight: $root['h'],
            percentageBasis: $basis,
        ));
        return is_nan($pixels) || is_infinite($pixels) ? null : $pixels;
    }

    private function relativeLengthBasis(string $unit, float $percentBasis): float
    {
        if ($unit === '%') {
            return $percentBasis;
        }
        $root = $this->rootViewport ?? $this->currentViewport();
        return match ($unit) {
            'vw' => $root['w'],
            'vh' => $root['h'],
            'vmin' => min($root['w'], $root['h']),
            'vmax' => max($root['w'], $root['h']),
            default => $percentBasis,
        };
    }

    /**
     * Width / height of the current viewport in viewBox units. Falls back
     * to the root SVG width/height attributes when no `viewBox` is set, and
     * to zero when neither is available — matching SVG 2's "no useful
     * viewport" outcome (percentages collapse to 0). Nested `<svg>` /
     * `<symbol>` viewports land later; this picks the document root, which
     * is what `background-image: url(...svg)` paints into.
     *
     * @return array{w: float, h: float}
     */
    private function currentViewport(): array
    {
        // Innermost nested `<svg>` viewport wins for percentage resolution.
        if ($this->viewportStack !== []) {
            return $this->viewportStack[count($this->viewportStack) - 1];
        }
        if ($this->document === null) {
            return ['w' => 0.0, 'h' => 0.0];
        }
        $viewBox = $this->document->viewBox();
        if ($viewBox !== null) {
            return ['w' => $viewBox[2], 'h' => $viewBox[3]];
        }
        $w = self::parseLengthPrefixForViewport($this->document->widthAttribute());
        $h = self::parseLengthPrefixForViewport($this->document->heightAttribute());
        if ($w !== null && $h !== null) {
            // Mirror SvgRenderer::resolveSourceRect's near-integral
            // snap (#143 / crbug.com/1392140): an SVG with
            // `width="99.99999"` resolves child `width="100%"` to
            // 100, matching the integer-snap browsers apply to
            // <img>-embedded SVG intrinsic dimensions. Without this,
            // the inner viewport stays at 99.99999 even after the
            // outer rendering box rounds to 100, so a `width="100%"`
            // child paints 0.00001 short of the box's right edge.
            return ['w' => (float) round($w), 'h' => (float) round($h)];
        }
        // Fall back to the caller's effective viewport (the destination
        // rect from `SvgRenderer::draw`) when document attributes alone
        // can't give a useful viewport — e.g. an SVG with only a
        // percentage `width` and no `height` or `viewBox`.
        if ($this->effectiveViewport !== null) {
            return $this->effectiveViewport;
        }
        return ['w' => $w ?? 0.0, 'h' => $h ?? 0.0];
    }

    private static function parseLengthPrefixForViewport(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+))\s*([%a-zA-Z]*)/', $raw, $m) !== 1) {
            return null;
        }
        // Percentage attributes carry no intrinsic viewport extent — they
        // resolve against the caller-supplied effective viewport (CSS
        // Images 3 §5.2). Reject so the fallback path picks up the
        // dst-derived viewport instead of mis-anchoring to the bare
        // percentage value.
        if ($m[2] === '%') {
            return null;
        }
        return (float) $m[1];
    }

    /** @return bool whether any geometry was emitted. */
    private function emitCirclePath(Circle $circle, ContentStream $stream): bool
    {
        $vp = $this->currentViewport();
        $cx = $this->geometryLength($circle, 'cx', $vp['w']) ?? 0.0;
        $cy = $this->geometryLength($circle, 'cy', $vp['h']) ?? 0.0;
        // SVG 2 §10.1: a percentage `r` resolves against the NORMALIZED
        // DIAGONAL, not either axis. A negative `r` is invalid — the
        // declaration is ignored and the initial 0 stands, so nothing
        // paints.
        $r = $this->geometryLength($circle, 'r', self::normalizedDiagonal($vp)) ?? 0.0;
        if ($r <= 0.0) {
            return false;
        }
        $this->emitEllipsePath($stream, $cx, $cy, $r, $r);
        return true;
    }

    /** @return bool whether any geometry was emitted. */
    private function emitEllipsePathFor(Ellipse $ellipse, ContentStream $stream): bool
    {
        $vp = $this->currentViewport();
        $cx = $this->geometryLength($ellipse, 'cx', $vp['w']) ?? 0.0;
        $cy = $this->geometryLength($ellipse, 'cy', $vp['h']) ?? 0.0;
        $rx = $this->geometryLength($ellipse, 'rx', $vp['w']);
        $ry = $this->geometryLength($ellipse, 'ry', $vp['h']);
        // SVG 2 §10.1 — `rx` / `ry` initial value is `auto`, and a
        // NEGATIVE radius is invalid: the declaration is ignored, which
        // leaves `auto` in force rather than collapsing the shape. Each
        // `auto` radius then mirrors the other, so
        // `<ellipse rx="-65" ry="65">` paints a circle of r=65.
        if ($rx !== null && $rx < 0.0) {
            $rx = null;
        }
        if ($ry !== null && $ry < 0.0) {
            $ry = null;
        }
        $rx ??= $ry;
        $ry ??= $rx;
        if ($rx === null || $ry === null || $rx <= 0.0 || $ry <= 0.0) {
            return false;
        }
        $this->emitEllipsePath($stream, $cx, $cy, $rx, $ry);
        return true;
    }

    /**
     * SVG 2 §10.1 — resolve one geometry property for `$element`.
     *
     * The value may be written as a presentation attribute or come from
     * the cascade (which {@see SvgCascadeProjector} projects into
     * `style`); {@see Element::geometryValue()} reads both. `$basis` is
     * the 1%-basis for the property's axis: viewport width for
     * `x`/`width`/`cx`/`rx`, height for `y`/`height`/`cy`/`ry`, and the
     * normalized diagonal for `r`.
     *
     * Returns null when the property is absent or is not a length (an
     * `auto` keyword, a `calc()` we don't evaluate here), so callers can
     * apply that property's own initial-value rule instead of guessing.
     */
    private function geometryLength(Element $element, string $property, float $basis): ?float
    {
        $raw = $element->geometryValue($property);
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        // SVG 2 §6.7 — a geometry presentation attribute is parsed as a
        // CSS value, so the CSS math functions are legal in it. They
        // can't match the numeric fast path below (they don't start
        // with a digit), so route them through the CSS evaluator first.
        if (preg_match('/^\s*(?:calc|min|max|clamp|abs|sign|hypot)\s*\(/i', $raw) === 1) {
            return $this->evaluateMathFunction($raw, $element, $basis);
        }
        // Prefix match, not anchored: mirrors the leniency the shape
        // accessors have always had for trailing junk (`"10 20"`).
        if (preg_match(
            '/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*([a-zA-Z%]*)/',
            $raw,
            $m,
        ) !== 1) {
            return null;
        }
        $value = (float) $m[1];
        $unit = strtolower($m[2]);
        if ($unit === '') {
            return $value;
        }
        if ($unit === '%' || $unit === 'vw' || $unit === 'vh' || $unit === 'vmin' || $unit === 'vmax') {
            return $value / 100.0 * $this->relativeLengthBasis($unit, $basis);
        }
        return $value * Element::absoluteUnitScale($unit);
    }

    /**
     * SVG 2 §10.1's "normalized diagonal" — `sqrt(w² + h²) / sqrt(2)`,
     * the 1%-basis for properties that are not tied to one axis (`r`).
     *
     * @param array{w: float, h: float} $viewport
     */
    private static function normalizedDiagonal(array $viewport): float
    {
        return sqrt($viewport['w'] ** 2 + $viewport['h'] ** 2) / M_SQRT2;
    }

    private function emitPolylinePath(Polyline $polyline, ContentStream $stream): void
    {
        $points = $polyline->points();
        if (count($points) >= 2) {
            $this->emitPolyPath($stream, $points, closed: false);
        }
    }

    private function emitPolygonPath(Polygon $polygon, ContentStream $stream): void
    {
        $points = $polygon->points();
        if (count($points) >= 3) {
            $this->emitPolyPath($stream, $points, closed: true);
        }
    }

    private function emitPathPath(Path $path, ContentStream $stream): void
    {
        $commands = $path->d()->commands;
        if ($commands === []) {
            return;
        }
        $state = new PathPainterState();
        foreach ($commands as $command) {
            $this->emitPathCommand($command, $stream, $state);
        }
    }

    /**
     * Register (or reuse) the `ExtGState` resource encoding this
     * element's effective opacity. Returns the resource name or null
     * when no `gs` op is needed (no Page reference, or every opacity
     * channel is already ≥ 0.999).
     */
    private function resolveOpacityState(Element $element): ?string
    {
        if ($this->page === null) {
            return null;
        }
        $opacity = $element->opacity() ?? 1.0;
        // SVG 2 §13.2 vs §15.2 — `fill-opacity` / `stroke-opacity`
        // modulate the element's OWN paint operations and reach
        // descendants by INHERITANCE, which the cascade projection
        // already carries. `opacity` is the group property that
        // composites a subtree as a unit.
        //
        // Folding all three into one ExtGState made a container's
        // fill-opacity stick to every descendant, and a child
        // declaring `fill-opacity: 1` had nothing to emit (its value
        // matched the default) so it could not escape. A container
        // therefore contributes only its group opacity here.
        $paints = self::paintsItsOwnGeometry($element);
        $fillOpacity = ($paints ? $element->fillOpacity() ?? 1.0 : 1.0) * $opacity;
        $strokeOpacity = ($paints ? $element->strokeOpacity() ?? 1.0 : 1.0) * $opacity;
        if ($fillOpacity >= 0.999 && $strokeOpacity >= 0.999) {
            return null;
        }
        return $this->page->ensureOpacityState($strokeOpacity, $fillOpacity);
    }

    /**
     * Whether the element emits paint operations of its own, as
     * opposed to being a container that only groups other elements.
     *
     * Mirrors the arms of {@see dispatchElement} that reach
     * `applyFillAndStroke`: only those elements have a fill or stroke
     * to modulate.
     */
    private static function paintsItsOwnGeometry(Element $element): bool
    {
        return $element instanceof Rect
            || $element instanceof Circle
            || $element instanceof Ellipse
            || $element instanceof Line
            || $element instanceof Polyline
            || $element instanceof Polygon
            || $element instanceof Path
            || $element instanceof TextElement
            || $element instanceof SvgImage;
    }

    /**
     * Whether the element carries any stroke parameter that would
     * leak past a sibling shape if emitted inline. Used to decide
     * whether to wrap the element's painting in `q`/`Q`.
     */
    private function needsStrokeParams(Element $element): bool
    {
        if ($element->stroke() === null || $element->stroke() instanceof None_) {
            return false;
        }
        if ($element->strokeWidth() !== null) {
            return true;
        }
        if ($element->strokeLinecap() !== null) {
            return true;
        }
        if ($element->strokeLinejoin() !== null) {
            return true;
        }
        if ($element->strokeMiterlimit() !== null) {
            return true;
        }
        if ($element->strokeDasharray() !== []) {
            return true;
        }
        if ($element->strokeDashoffset() !== null) {
            return true;
        }
        return false;
    }

    private function applyStrokeParams(Element $element, ContentStream $stream): void
    {
        $width = $element->strokeWidth();
        if ($width !== null) {
            $stream->setLineWidth($width);
        }
        $cap = $element->strokeLinecap();
        if ($cap !== null) {
            $stream->setLineCap(match ($cap) {
                'round' => 1,
                'square' => 2,
                default => 0, // butt
            });
        }
        $join = $element->strokeLinejoin();
        if ($join !== null) {
            $stream->setLineJoin(match ($join) {
                'round' => 1,
                'bevel' => 2,
                default => 0, // miter / miter-clip / arcs all fall back to PDF's miter
            });
        }
        $miterLimit = $element->strokeMiterlimit();
        if ($miterLimit !== null) {
            $stream->setMiterLimit($miterLimit);
        }
        $dash = $element->strokeDasharray();
        if ($dash !== []) {
            // SVG 2 §9.6 — `pathLength` recalibrates every
            // distance-along-the-path quantity, so the dash pattern is
            // authored in the declared units and scaled here.
            $scale = $this->pathLengthScale($element);
            if (is_infinite($scale)) {
                // `pathLength="0"` is a scaling factor of infinity: the
                // first dash covers the whole path, which is a solid
                // stroke. Emitting no `d` operator IS that stroke.
                return;
            }
            $offset = (int) round(($element->strokeDashoffset() ?? 0.0) * $scale);
            $stream->setDashPattern(
                array_map(static fn(float $d): float => $d * $scale, $dash),
                $offset,
            );
        }
    }

    /**
     * SVG 2 §9.6 — the factor that converts a distance expressed in
     * the author's declared `pathLength` units into user units.
     *
     * `1.0` when the element declares no `pathLength`, or when we
     * can't measure its geometry (an unmeasurable element must not
     * silently rescale its dashes). `INF` when the author declared
     * zero, which the spec defines as a scaling factor of infinity.
     */
    private function pathLengthScale(Element $element): float
    {
        $declared = $element->pathLength();
        if ($declared === null) {
            return 1.0;
        }
        $geometric = $this->geometricPathLength($element);
        if ($geometric === null || $geometric <= 0.0) {
            return 1.0;
        }
        if ($declared <= 0.0) {
            return INF;
        }
        return $geometric / $declared;
    }

    /**
     * The user agent's own computation of an element's path length, in
     * user units — the numerator of the `pathLength` scaling factor.
     *
     * Geometry comes from the same resolvers the painter emits from, so
     * a CSS-declared `r` (SVG 2 §10.1) measures the circle that
     * actually paints. Shapes measure ANALYTICALLY rather than from the
     * Bézier approximation the painter lowers them to: WPT's
     * `pathLength` reftests pair a shape against an equivalent
     * hand-written `<path>`, and the two only agree on the true
     * geometry.
     *
     * Returns null for elements that have no path (text, groups,
     * images) — `pathLength` has no meaning there.
     */
    private function geometricPathLength(Element $element): ?float
    {
        $vp = $this->currentViewport();
        if ($element instanceof Path) {
            return PathLengthMeasure::ofPathData($element->d());
        }
        if ($element instanceof Rect) {
            // The painter emits a plain `re`, so the perimeter is the
            // rectangle's, with no rounded-corner correction to make.
            $w = $this->geometryLength($element, 'width', $vp['w']) ?? 0.0;
            $h = $this->geometryLength($element, 'height', $vp['h']) ?? 0.0;
            if ($w <= 0.0 || $h <= 0.0) {
                return null;
            }
            return 2.0 * ($w + $h);
        }
        if ($element instanceof Circle) {
            $r = $this->geometryLength($element, 'r', self::normalizedDiagonal($vp)) ?? 0.0;
            return $r > 0.0 ? 2.0 * M_PI * $r : null;
        }
        if ($element instanceof Ellipse) {
            $rx = $this->geometryLength($element, 'rx', $vp['w']);
            $ry = $this->geometryLength($element, 'ry', $vp['h']);
            if ($rx !== null && $rx < 0.0) {
                $rx = null;
            }
            if ($ry !== null && $ry < 0.0) {
                $ry = null;
            }
            $rx ??= $ry;
            $ry ??= $rx;
            if ($rx === null || $ry === null || $rx <= 0.0 || $ry <= 0.0) {
                return null;
            }
            return self::ellipsePerimeter($rx, $ry);
        }
        if ($element instanceof Line) {
            return hypot($element->x2() - $element->x1(), $element->y2() - $element->y1());
        }
        if ($element instanceof Polyline) {
            return self::polylineLength($element->points(), closed: false);
        }
        if ($element instanceof Polygon) {
            return self::polylineLength($element->points(), closed: true);
        }
        return null;
    }

    /**
     * Ramanujan's second approximation to the ellipse perimeter —
     * relative error below 1e-9 for every eccentricity an SVG author
     * can write, and exact for the circle.
     */
    private static function ellipsePerimeter(float $rx, float $ry): float
    {
        $h = (($rx - $ry) ** 2) / (($rx + $ry) ** 2);
        return M_PI * ($rx + $ry)
            * (1.0 + (3.0 * $h) / (10.0 + sqrt(4.0 - 3.0 * $h)));
    }

    /**
     * @param list<array{float, float}> $points
     */
    private static function polylineLength(array $points, bool $closed): ?float
    {
        $count = count($points);
        if ($count < 2) {
            return null;
        }
        $total = 0.0;
        for ($i = 1; $i < $count; $i++) {
            $total += hypot(
                $points[$i][0] - $points[$i - 1][0],
                $points[$i][1] - $points[$i - 1][1],
            );
        }
        if ($closed) {
            $total += hypot(
                $points[0][0] - $points[$count - 1][0],
                $points[0][1] - $points[$count - 1][1],
            );
        }
        return $total > 0.0 ? $total : null;
    }

    private function dispatchElement(Element $element, ContentStream $stream): void
    {
        match (true) {
            $element instanceof Rect => $this->paintRect($element, $stream),
            $element instanceof Circle => $this->paintCircle($element, $stream),
            $element instanceof Ellipse => $this->paintEllipse($element, $stream),
            $element instanceof Line => $this->paintLine($element, $stream),
            $element instanceof Polyline => $this->paintPolyline($element, $stream),
            $element instanceof Polygon => $this->paintPolygon($element, $stream),
            $element instanceof Path => $this->paintPath($element, $stream),
            $element instanceof TextElement => $this->paintTextElement($element, $stream),
            $element instanceof \Phpdftk\Svg\NestedSvg => $this->paintNestedSvg($element, $stream),
            $element instanceof Use_ => $this->paintUse($element, $stream),
            $element instanceof SvgImage => $this->paintImage($element, $stream),
            // `<defs>` and `<symbol>` are referenceable containers: they
            // never paint themselves at the document level. `<use>`
            // expands `<defs>` / `<symbol>` referents; `clip-path` on
            // a painted element pulls in a `<clipPath>`; `mask` pulls
            // in a `<mask>`. Skipping them here also skips their
            // nested shape children, which is what the spec wants
            // (SVG 2 §5.5 / §5.6 / §14.4 / §14.5).
            $element instanceof Defs,
            $element instanceof Symbol,
            $element instanceof ClipPath,
            $element instanceof Mask,
            // SVG 2 §11.6 — `<marker>` is referenced via `marker-*`
            // properties on shapes; it never paints at the document
            // level. The painter pulls in a marker when it's
            // requested at a path vertex (future deliverable).
            $element instanceof \Phpdftk\Svg\Marker,
            // SVG 2 §13.3 — `<pattern>` is referenced via
            // `fill="url(#id)"` / `stroke="url(#id)"`; the painter
            // pulls in pattern content when a shape's paint
            // resolves to a pattern URL (future deliverable).
            $element instanceof \Phpdftk\Svg\Pattern,
            // SVG 2 Filter Effects §6.1 — `<filter>` is referenced
            // via `filter="url(#id)"` and applied via SoftMask. Never
            // paints at document level.
            $element instanceof \Phpdftk\Svg\Filter,
            // SVG 2 §6.3 — `<view>` is a fragment-targeted viewport
            // definition. Activated only when callers pass a view id;
            // never paints at document level.
            $element instanceof \Phpdftk\Svg\View,
            // SVG 2 §15.2 — `<script>` content is JS; never executes
            // server-side and never paints. Explicit skip prevents
            // any nested `<text>` etc. children from leaking into the
            // output stream.
            $element instanceof \Phpdftk\Svg\Script,
            // SVG 2 §19 — animation elements never paint at the
            // static print medium. Skip to avoid recursing into any
            // nested `<mpath>` etc. that would otherwise fall through
            // to the default container walk.
            $element instanceof \Phpdftk\Svg\Animation => null,
            // SVG 2 §6.4 — `<metadata>` carries RDF/XML or other
            // non-render metadata. Skip explicitly so any embedded
            // RDF text doesn't accidentally leak.
            $element instanceof \Phpdftk\Svg\Metadata => null,
            // SVG 2 §15.3 — `<title>` and `<desc>` are accessibility
            // metadata that never renders directly. Skip the recursive
            // walk so their text content doesn't leak into output.
            $element instanceof \Phpdftk\Svg\Title,
            $element instanceof \Phpdftk\Svg\Desc,
            // SVG 2 §11.6 — `<foreignObject>` holds non-SVG content
            // (HTML / MathML). Rendering that content requires a
            // separate pipeline; the typed class lets callers detect
            // and route it. Inside the SVG dispatch we skip the
            // foreign tree entirely to avoid painting GenericElement
            // children that aren't actual SVG shapes.
            $element instanceof \Phpdftk\Svg\ForeignObject => null,
            // SVG 2 §12.1.1 — `<a>` paints its children. The PDF link
            // annotation (which is what makes the rendered region
            // clickable) is a future concern that needs page-relative
            // bounding boxes; we paint the inner content faithfully
            // here so the visual stays correct.
            $element instanceof \Phpdftk\Svg\A_
                => $this->paintChildren($element, $stream),
            // SVG 2 §5.7 — `<switch>` picks the first child whose
            // conditional-processing attributes all evaluate true.
            $element instanceof \Phpdftk\Svg\Switch_
                => $this->paintSwitch($element, $stream),
            // `<g>` and any other container fall through here — the
            // recursive walk still descends into their children.
            default => $this->paintChildren($element, $stream),
        };
    }

    /**
     * SVG 2 §5.7 — paint the first child of `<switch>` whose
     * conditional-processing tests all evaluate true. Empty-test
     * children always pass. Stops after painting the first
     * matching child.
     */
    private function paintSwitch(\Phpdftk\Svg\Switch_ $switch, ContentStream $stream): void
    {
        foreach ($switch->children as $child) {
            if (!$child instanceof Element) {
                continue;
            }
            if (!$this->switchChildPasses($child)) {
                continue;
            }
            $this->paintElement($child, $stream);
            return;
        }
    }

    /**
     * Evaluate the conditional-processing attributes (SVG 2 §5.8) on
     * an element:
     *
     *   - `requiredFeatures` — legacy SVG 1.1 list of feature
     *     URIs. All listed URIs evaluate true here so the test
     *     never fails (matches major browser behaviour now).
     *   - `requiredExtensions` — author-supplied extension URIs.
     *     Any presence fails: print medium can't observe any
     *     UA-specific extensions. A PRESENT BUT EMPTY list also
     *     evaluates false, which §5.8.3 states outright — it is not
     *     "no requirements".
     *   - `systemLanguage` — comma-separated BCP 47 tags. The
     *     test passes when at least one tag prefix-matches the
     *     `xml:lang` (or `lang`) ancestor chain.
     *
     * These apply to ANY direct rendering element, not just the
     * children of a `<switch>`; `<switch>` layers its own
     * "first passing child only" rule on top.
     */
    private function switchChildPasses(Element $child): bool
    {
        if ($child->hasAttribute('requiredExtensions')) {
            return false;
        }
        if ($child->hasAttribute('systemLanguage')) {
            $needed = preg_split(
                '/[\s,]+/',
                strtolower(trim($child->getAttribute('systemLanguage') ?? '')),
            ) ?: [];
            $needed = array_filter($needed, static fn(string $s): bool => $s !== '');
            $documentLang = $this->resolveSystemLanguage($child);
            $matched = false;
            foreach ($needed as $tag) {
                if ($tag === $documentLang || str_starts_with($documentLang, $tag . '-')) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }
        return true;
    }

    /**
     * Walk ancestor `xml:lang` / `lang` attributes. Defaults to
     * `en` when nothing is set, matching the SVG default UA
     * behaviour for systemLanguage matching.
     */
    private function resolveSystemLanguage(Element $element): string
    {
        for ($n = $element; $n !== null; $n = $n->parent) {
            $lang = $n->getAttribute('xml:lang') ?? $n->getAttribute('lang');
            if ($lang !== null && $lang !== '') {
                return strtolower($lang);
            }
        }
        return 'en';
    }

    private function paintUse(Use_ $use, ContentStream $stream): void
    {
        if ($this->document === null) {
            return;
        }
        // SVG 2 §5.6 — when the embedding pipeline has already
        // materialised the shadow tree (the HTML cascade does, so the
        // instance INHERITS from the `<use>`), paint that clone. Falling
        // back to re-resolving the `href` would paint the referenced
        // element as it is styled where it sits, losing everything the
        // `<use>` contributed.
        $referent = self::materialisedUseInstance($use) ?? $use->resolve($this->document);
        if ($referent === null) {
            return;
        }
        // SVG 2 §5.6.2 — referencing an ancestor of the `<use>` (or the
        // `<use>` itself) is an invalid circular reference: the element
        // is not rendered. Unguarded this recursed until the C stack
        // gave out and took the process with it (SIGSEGV), so a
        // malformed or hostile document could kill the renderer.
        for ($n = $use->parent; $n !== null; $n = $n->parent) {
            if ($n === $referent) {
                return;
            }
        }
        if ($referent === $use || $this->useExpansionsInProgress->contains($referent)) {
            return;
        }
        $this->useExpansionsInProgress->attach($referent, true);
        try {
            $this->paintUseResolved($use, $referent, $stream);
        } finally {
            $this->useExpansionsInProgress->detach($referent);
        }
    }

    /**
     * The body of {@see paintUse} once the referent is known to be a
     * legal, non-circular target.
     */
    private function paintUseResolved(Use_ $use, Element $referent, ContentStream $stream): void
    {
        // SVG 2 §5.6 — the `<use>`'s `x` / `y` translate the referent's
        // coordinate system. Width / height overrides on `<symbol>`
        // referents resolve through viewBox-to-viewport mapping; that
        // mapping needs target-rectangle context we don't have until
        // 3R, so 3Q honours the translation only.
        $x = $use->x();
        $y = $use->y();
        // SVG 2 §5.6.1 — when the referent is a nested `<svg>`, the use's
        // width/height (if both set) become that svg's viewport.
        $useW = $use->width();
        $useH = $use->height();
        $override = null;
        if ($referent instanceof \Phpdftk\Svg\ViewportElement) {
            // Per axis, and only for a POSITIVE override — a zero or
            // absent dimension leaves the referenced element's own
            // value (or the 100 % default) in force.
            $override = [
                'w' => $useW !== null && $useW > 0.0 ? $useW : null,
                'h' => $useH !== null && $useH > 0.0 ? $useH : null,
            ];
            if ($override['w'] === null && $override['h'] === null) {
                $override = null;
            }
        }
        if ($x === 0.0 && $y === 0.0 && $override === null) {
            $this->paintUseReferent($referent, $stream);
            return;
        }
        $stream->saveGraphicsState();
        if ($x !== 0.0 || $y !== 0.0) {
            $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, $x, $y);
        }
        $prevOverride = $this->pendingUseViewport;
        $this->pendingUseViewport = $override;
        $this->paintUseReferent($referent, $stream);
        $this->pendingUseViewport = $prevOverride;
        $stream->restoreGraphicsState();
    }

    /**
     * The pre-built `<use>` instance, if the embedding pipeline
     * materialised one. Identified by the marker attribute
     * {@see \Phpdftk\HtmlToPdf\Box\BoxGenerator::USE_INSTANCE_ATTRIBUTE},
     * which is spelled out here rather than imported: `svg-to-pdf` must
     * not depend on `html-to-pdf` (the dependency runs the other way).
     */
    private static function materialisedUseInstance(Use_ $use): ?Element
    {
        foreach ($use->children as $child) {
            if ($child instanceof Element
                && $child->getAttribute('data-phpdftk-use-instance') !== null
            ) {
                return $child;
            }
        }
        return null;
    }

    /**
     * `<symbol>` referents have their children painted directly per
     * SVG 2 §5.5 — referencing them via `<use>` is the only way they
     * paint at all. Everything else routes through normal dispatch.
     */
    private function paintUseReferent(Element $referent, ContentStream $stream): void
    {
        if ($referent instanceof Symbol) {
            // SVG 2 §5.5 / §5.6.2 — the instance a `<use>` generates for
            // a `<symbol>` behaves as an `<svg>`: it establishes a
            // viewport, maps any `viewBox` into it, and clips to it.
            // Painting the children bare let a symbol's content spill
            // across the whole document.
            $this->paintViewportInstance($referent, $stream);
            return;
        }
        $this->paintElement($referent, $stream);
    }

    private function paintImage(SvgImage $image, ContentStream $stream): void
    {
        $href = $image->href();
        if ($href === null) {
            return;
        }
        // SVG 2 §8.6 — an `<image>` href may carry a URL fragment
        // (`…#view`) selecting a `<view>` inside the referenced SVG.
        // The fragment is never part of the resource bytes, so it is
        // split off before the payload is decoded / read.
        [$locator, $fragment] = self::splitHrefFragment($href);
        // 3Q: filesystem hrefs.
        // 3R+18: `data:` URIs decoded and materialised to a temp
        // file so the same `ImageParser` + `PdfWriter::addImage`
        // flow (which only accepts paths) can embed them.
        // 4F.1: `http(s)://` hrefs route through the optional
        // `ResourceLoader` injected at construction. SSRF guard,
        // redirect handling, content-length cap, and MIME sniffing
        // all live in the loader; we just hand off the URL and
        // materialise the bytes to a temp file like the data:
        // path. When no loader is configured, http(s):// drops
        // silently per the original 3R+18 posture so existing
        // callers don't change behaviour.
        $sourcePath = $locator;
        $bytes = null;
        if (str_starts_with($locator, 'data:')) {
            $decoded = self::decodeDataUri($locator);
            if ($decoded === null) {
                return;
            }
            $bytes = $decoded['bytes'];
            $fragment = $decoded['fragment'];
        } elseif (str_starts_with($locator, 'http://') || str_starts_with($locator, 'https://')) {
            if ($this->resourceLoader === null) {
                return;
            }
            $bytes = $this->fetchHttpHref($locator);
            if ($bytes === null) {
                return;
            }
        } elseif (str_contains($locator, '://')) {
            return;
        } else {
            // Filesystem href: sniff the head only, so a large raster
            // still streams through `ImageParser` + `addImage` rather
            // than being slurped into memory here.
            try {
                $head = LocalFilesystem::readPrefix($sourcePath, 4096, 'image');
            } catch (\Throwable) {
                return;
            }
            if (self::looksLikeSvg($head)) {
                try {
                    $bytes = LocalFilesystem::readFile($sourcePath, 'svg image');
                } catch (\Throwable) {
                    return;
                }
            }
        }

        // SVG 2 §8.6 — an `<image>` referencing an SVG resource
        // establishes a viewport for it and renders the referenced
        // document's own tree into it. There is no raster to embed,
        // so this never reaches `PdfWriter::addImage` (which rejects
        // SVG bytes outright).
        if ($bytes !== null && self::looksLikeSvg($bytes)) {
            $this->paintEmbeddedSvg($image, $bytes, $fragment, $stream);
            return;
        }

        if ($this->writer === null || $this->page === null) {
            return;
        }
        $tempPath = null;
        if ($bytes !== null) {
            $tempPath = self::materialiseBytes($bytes);
            if ($tempPath === null) {
                return;
            }
            $sourcePath = $tempPath;
        }
        try {
            $this->paintImageFromPath($sourcePath, $image, $stream);
        } finally {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Split an `<image>` href into its resource locator and URL
     * fragment. RFC 3986 makes `#` the fragment delimiter for every
     * URI form including `data:` (a literal `#` in a data payload has
     * to be percent-encoded), so the FIRST `#` wins.
     *
     * Bare filesystem paths are exempt unless the pre-`#` prefix is
     * itself a readable file — a `#` is a legal character in a
     * filename and splitting one off would break existing local
     * hrefs.
     *
     * @return array{0: string, 1: string|null}
     */
    private static function splitHrefFragment(string $href): array
    {
        if (str_starts_with($href, 'data:')) {
            // A `data:` URI carries its payload inline, so splitting on
            // a bare `#` would truncate any document that inlines an
            // unencoded hex colour (`fill='#0f0'`) — technically
            // malformed per RFC 3986, but common enough that silently
            // blanking those documents is worse than ignoring a
            // fragment. {@see decodeDataUri} peels the fragment off the
            // decoded bytes instead, where it can tell the two apart.
            return [$href, null];
        }
        $hash = strpos($href, '#');
        if ($hash === false) {
            return [$href, null];
        }
        $locator = substr($href, 0, $hash);
        $fragment = substr($href, $hash + 1);
        if ($locator === '') {
            return [$href, null];
        }
        $isUrl = str_starts_with($href, 'http://') || str_starts_with($href, 'https://');
        if (!$isUrl && !is_file($locator)) {
            return [$href, null];
        }
        return [$locator, $fragment === '' ? null : $fragment];
    }

    /**
     * SVG sniffer mirroring {@see ImageParser}'s: a case-insensitive
     * `<svg` within the head, and the document must open with an XML
     * declaration, a comment, a doctype, or the `<svg` tag itself.
     * Rules out arbitrary markup where `<svg` merely appears inside.
     */
    private static function looksLikeSvg(string $bytes): bool
    {
        $head = substr($bytes, 0, 4096);
        if (stripos($head, '<svg') === false) {
            return false;
        }
        $trimmed = ltrim($head, " \t\r\n");
        return str_starts_with($trimmed, '<?xml')
            || str_starts_with($trimmed, '<!--')
            || stripos($trimmed, '<!doctype') === 0
            || stripos($trimmed, '<svg') === 0;
    }

    /**
     * SVG 2 §8.6 — render an SVG resource referenced by `<image>`.
     *
     * The element establishes a new viewport at `(x, y, width,
     * height)` with `overflow: hidden`, and the referenced document is
     * a SEPARATE document: nothing inherits into it, and its ids live
     * in their own space. That is why the tree is painted by a fresh
     * `Translator` rather than this one — `findById` on the host
     * document must not resolve against the embedded tree, or vice
     * versa.
     *
     * Sizing follows the CSS Images 3 §5.3 default sizing algorithm:
     * an omitted `width` / `height` on the `<image>` is derived from
     * the referenced document's intrinsic size, or from the other axis
     * via its intrinsic ratio.
     *
     * The viewBox-to-viewport mapping uses, in order of precedence,
     * the `preserveAspectRatio` on the `<image>` element (SVG 2 §8.6
     * makes it override the referenced root's), then the one on an
     * activated `<view>`, then the referenced root's own.
     */
    private function paintEmbeddedSvg(
        SvgImage $image,
        string $bytes,
        ?string $fragment,
        ContentStream $stream,
    ): void {
        if ($this->embeddedSvgDepth >= self::MAX_EMBEDDED_SVG_DEPTH) {
            return;
        }
        try {
            $document = (new SvgDocumentParser())->parse($bytes);
        } catch (\Throwable) {
            // Unparseable resource — SVG 2 §8.6's "no image available".
            return;
        }

        $viewBox = $document->viewBox();
        $referencedPar = $document->getAttribute('preserveAspectRatio');
        // SVG 2 §18.3 — `resource.svg#viewId` activates that `<view>`,
        // whose `viewBox` / `preserveAspectRatio` stand in for the
        // root element's for this reference.
        if ($fragment !== null) {
            $view = $document->findByFragment($fragment);
            if ($view instanceof SvgView) {
                $viewBox = $view->viewBox() ?? $viewBox;
                $referencedPar = $view->preserveAspectRatio() ?? $referencedPar;
            }
        }

        [$intrinsicW, $intrinsicH] = self::embeddedSvgIntrinsicSize($document, $viewBox);
        $ratio = $intrinsicW > 0.0 && $intrinsicH > 0.0
            ? $intrinsicW / $intrinsicH
            : ($viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0
                ? $viewBox[2] / $viewBox[3]
                : null);

        $vp = $this->currentViewport();
        $x = $this->resolveViewportLength($image->getAttribute('x'), $vp['w'], 0.0);
        $y = $this->resolveViewportLength($image->getAttribute('y'), $vp['h'], 0.0);
        $w = self::resolveImageExtent($image->getAttribute('width'), $vp['w']);
        $h = self::resolveImageExtent($image->getAttribute('height'), $vp['h']);
        if ($w === null && $h === null) {
            if ($intrinsicW > 0.0 && $intrinsicH > 0.0) {
                $w = $intrinsicW;
                $h = $intrinsicH;
            } else {
                // CSS Images 3 §5.3 default sizing with NO specified
                // size: the default object size for an `<image>` is
                // the nearest SVG viewport. A resource carrying only
                // an intrinsic ratio is contained within it; one with
                // neither size nor ratio simply takes it. This is the
                // `<image href="…svg"/>` case, where a referenced
                // document whose root is itself `width: auto;
                // height: auto` has no concrete size of its own.
                [$w, $h] = self::containWithin($vp['w'], $vp['h'], $ratio);
            }
        } elseif ($w === null) {
            $w = $ratio !== null ? ($h ?? 0.0) * $ratio : $intrinsicW;
        } elseif ($h === null) {
            $h = $ratio !== null && $ratio > 0.0 ? $w / $ratio : $intrinsicH;
        }
        if ($w === null || $h === null || $w <= 0.0 || $h <= 0.0) {
            // A zero-sized viewport disables rendering (SVG 2 §8.6).
            return;
        }

        // Source rect. A referenced root without a `viewBox` has one
        // synthesised from its intrinsic size; with neither, the
        // document simply lays out at the viewport's own size (1:1),
        // which is what makes `width="100%"` / viewport units inside
        // the referenced document resolve against the image box.
        if ($viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0) {
            $srcW = $viewBox[2];
            $srcH = $viewBox[3];
        } elseif ($intrinsicW > 0.0 && $intrinsicH > 0.0) {
            $srcW = $intrinsicW;
            $srcH = $intrinsicH;
        } else {
            $srcW = $w;
            $srcH = $h;
        }

        $par = $image->preserveAspectRatio() ?? $referencedPar ?? '';
        [$scaleX, $scaleY, $offsetX, $offsetY] = self::viewBoxTransform($par, $srcW, $srcH, $w, $h);

        $stream->saveGraphicsState();
        $placement = [1.0, 0.0, 0.0, 1.0, $x, $y];
        $stream->concatMatrix(...$placement);
        $stream->rectangle(0.0, 0.0, $w, $h);
        $stream->clip();
        $stream->endPath();
        $mapping = [$scaleX, 0.0, 0.0, $scaleY, $offsetX, $offsetY];
        $stream->concatMatrix(...$mapping);

        // The cumulative SVG→page matrix the child inherits, so any
        // gradient inside the embedded document reconstructs the right
        // `/Matrix` (PDF resolves pattern matrices against DEFAULT
        // page space, not the fill-time CTM).
        $baseMatrix = self::multiplyAffine(
            self::multiplyAffine($this->currentMatrix(), $placement),
            $mapping,
        );

        $child = new self($this->resourceLoader, $this->documentFontProvider);
        $child->embeddedSvgDepth = $this->embeddedSvgDepth + 1;
        $this->cascadeProjector->project($document);
        $child->paint(
            $document,
            $stream,
            $this->page,
            $this->writer,
            compensateTextFlip: $this->compensateTextFlip,
            effectiveViewport: ['w' => $srcW, 'h' => $srcH],
            baseMatrix: $baseMatrix,
        );
        $stream->restoreGraphicsState();
    }

    /**
     * Intrinsic width / height of a referenced SVG root per CSS Images
     * 3 §4: the `width` / `height` attributes when they are absolute
     * lengths, with a missing axis derived from the other plus the
     * `viewBox` ratio. Percentage attributes carry no intrinsic
     * extent. Returns `[0.0, 0.0]` when neither axis resolves.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}|null $viewBox
     * @return array{0: float, 1: float}
     */
    private static function embeddedSvgIntrinsicSize(
        SvgDocument $document,
        ?array $viewBox,
    ): array {
        $w = self::parseLengthPrefixForViewport($document->widthAttribute());
        $h = self::parseLengthPrefixForViewport($document->heightAttribute());
        $ratio = $viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0
            ? $viewBox[2] / $viewBox[3]
            : null;
        if ($w !== null && $h === null && $ratio !== null) {
            $h = $w / $ratio;
        } elseif ($h !== null && $w === null && $ratio !== null) {
            $w = $h * $ratio;
        }
        if ($w === null || $h === null || $w <= 0.0 || $h <= 0.0) {
            return [0.0, 0.0];
        }
        return [$w, $h];
    }

    /**
     * An `<image>` `width` / `height` attribute as a used length:
     * `null` for absent / `auto` / negative (all of which mean "derive
     * it"), a percentage resolved against the enclosing viewport, and
     * anything else through the element's own unit-aware parse.
     */
    /**
     * CSS Images 3 §5.3's contain constraint: the largest rectangle
     * with `$ratio` (width ÷ height) that fits inside `$w` × `$h`. A
     * null ratio has nothing to preserve, so the box is taken whole.
     *
     * @return array{0: float, 1: float}
     */
    private static function containWithin(float $w, float $h, ?float $ratio): array
    {
        if ($ratio === null || $ratio <= 0.0) {
            return [$w, $h];
        }
        $widthAtFullHeight = $h * $ratio;
        return $widthAtFullHeight <= $w
            ? [$widthAtFullHeight, $h]
            : [$w, $w / $ratio];
    }

    private static function resolveImageExtent(?string $raw, float $viewport): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || strtolower($trimmed) === 'auto') {
            return null;
        }
        if (preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*%$/', $trimmed, $m) === 1) {
            $value = ((float) $m[1]) / 100.0 * $viewport;
            return $value < 0.0 ? null : $value;
        }
        $plain = self::parseLengthPrefixForViewport($trimmed);
        if ($plain === null || $plain < 0.0) {
            return null;
        }
        return $plain;
    }

    private function paintImageFromPath(string $path, SvgImage $image, ContentStream $stream): void
    {
        // Resolve intrinsic source dimensions before registering so we
        // can fall back to them when the SVG omits width / height.
        // `ImageParser::parse` is cheap (header-only read) and
        // bypassing the writer keeps this self-contained.
        try {
            $info = ImageParser::parse($path);
        } catch (\Throwable) {
            return;
        }
        if ($this->writer === null || $this->page === null) {
            return;
        }
        try {
            $resourceName = $this->writer->addImage($path, $this->page);
        } catch (\Throwable) {
            // Missing file / unparseable bytes / unsupported format —
            // SVG 2 §12.6's "no image available" outcome is to paint
            // nothing.
            return;
        }

        $x = $image->x();
        $y = $image->y();
        $w = $image->width();
        $h = $image->height();
        $intrinsicW = (float) $info->width;
        $intrinsicH = (float) $info->height;
        // SVG 2 §12.6 fallback ladder for missing width / height: when
        // one dimension is given the other is scaled to preserve the
        // intrinsic aspect ratio; when both are absent the intrinsic
        // dimensions are used directly. A zero-size intrinsic image
        // still paints nothing.
        if ($intrinsicW <= 0.0 || $intrinsicH <= 0.0) {
            return;
        }
        if ($w === null && $h === null) {
            $w = $intrinsicW;
            $h = $intrinsicH;
        } elseif ($w === null) {
            // height set, width follows the intrinsic aspect.
            $w = ($h ?? 0.0) * ($intrinsicW / $intrinsicH);
        } elseif ($h === null) {
            $h = $w * ($intrinsicH / $intrinsicW);
        }
        if ($w <= 0.0 || $h <= 0.0) {
            return;
        }

        // PDF Do paints the image inside a unit square at (0, 0) → (1, 1).
        // The transformation matrix translates + scales it to the SVG
        // rectangle. Y is flipped so the image's top-left lands at
        // (x, y) — PDF's image space is y-down within the unit square,
        // SVG's image element is y-down too, so the flip cancels them
        // out and the image renders right-side-up at the SVG-stated
        // position.
        $stream->saveGraphicsState();
        $stream->concatMatrix($w, 0.0, 0.0, -$h, $x, $y + $h);
        $stream->doXObject($resourceName);
        $stream->restoreGraphicsState();
    }

    /**
     * Decode a `data:` URI per RFC 2397.
     *
     *  - `data:image/png;base64,iVBOR…` → base64 payload
     *  - `data:image/svg+xml,<svg…>`     → percent-decoded payload
     *  - `data:,hello`                    → empty mime, percent-decoded
     *
     * Returns `null` if the URI is malformed or the base64 won't
     * decode. The MIME type is returned for the caller's information
     * but is not authoritative — `ImageParser::parse` still sniffs the
     * actual bytes to determine the PDF colour space + filter.
     *
     * A URL fragment (`…svg%3e#view`) is peeled off here rather than
     * by {@see splitHrefFragment}, because only after decoding can a
     * real fragment be told apart from a `#` the author inlined in the
     * payload. Base64 data has an alphabet that excludes `#`, so the
     * first one delimits; percent-encoded data can contain anything,
     * so a fragment is recognised only when it is the whole remainder
     * after the document's final `>` — which an inline `fill='#0f0'`
     * never is.
     *
     * @return array{bytes: string, mime: string, fragment: string|null}|null
     */
    private static function decodeDataUri(string $uri): ?array
    {
        if (!str_starts_with($uri, 'data:')) {
            return null;
        }
        $rest = substr($uri, 5);
        $commaPos = strpos($rest, ',');
        if ($commaPos === false) {
            return null;
        }
        $meta = substr($rest, 0, $commaPos);
        $data = substr($rest, $commaPos + 1);
        $isBase64 = false;
        $mime = '';
        if ($meta !== '') {
            $parts = explode(';', $meta);
            $mime = $parts[0];
            foreach (array_slice($parts, 1) as $param) {
                if ($param === 'base64') {
                    $isBase64 = true;
                }
            }
        }
        $fragment = null;
        if ($isBase64) {
            $hash = strpos($data, '#');
            if ($hash !== false) {
                $fragment = substr($data, $hash + 1);
                $data = substr($data, 0, $hash);
            }
            $bytes = base64_decode($data, true);
            if ($bytes === false) {
                return null;
            }
        } else {
            $bytes = rawurldecode($data);
            if (preg_match('/^(?<doc>.*>)\s*#(?<fragment>[^>#\s]+)\s*$/s', $bytes, $m) === 1) {
                $bytes = $m['doc'];
                $fragment = $m['fragment'];
            }
        }
        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'fragment' => ($fragment === null || $fragment === '') ? null : $fragment,
        ];
    }

    /**
     * Write decoded `<image>` payload bytes to a temp file that lives
     * only for the duration of `paintImage`. Returns the path on
     * success or `null` if either the temp file couldn't be opened or
     * the write failed — both fall back to the SVG "no image
     * available" outcome. Only the raster path needs this; SVG
     * payloads are painted straight from the byte string.
     */
    private static function materialiseBytes(string $bytes): ?string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'svg-img-');
        if ($tmpPath === false) {
            return null;
        }
        try {
            LocalFilesystem::writeFile($tmpPath, $bytes);
            return $tmpPath;
        } catch (\Throwable) {
            @unlink($tmpPath);
            return null;
        }
    }

    /**
     * 4F.1 — fetch an `http(s)://` `<image>` href through the
     * injected ResourceLoader. Returns the response bytes on success
     * or null on any failure (SSRF policy violation, network error,
     * non-2xx, body cap exceeded) — all of which surface as the SVG 2
     * §12.6 "no image available" outcome.
     */
    private function fetchHttpHref(string $href): ?string
    {
        if ($this->resourceLoader === null) {
            return null;
        }
        try {
            $result = $this->resourceLoader->fetch($href);
        } catch (SsrfBlockedException | FetchFailedException) {
            return null;
        }
        return $result->bytes;
    }

    private function paintTextElement(TextElement $text, ContentStream $stream): void
    {
        // Without writer + page references the resolver can't register a
        // font, so the text silently drops. Matches the same standalone
        // posture the gradient painter uses at 3O.
        if ($this->fontResolver === null) {
            return;
        }
        $content = self::collectTextContent($text);
        if ($content === '') {
            return;
        }

        // SVG 2 default fill for `<text>` is black; the existing
        // applyFillPaint path covers that, but we apply it *before*
        // entering the text object so the colour persists across the
        // Tj sequence (PDF text objects share the page graphics state).
        $fill = $text->fill();
        if (!($fill instanceof None_)) {
            $this->applyFillPaint($fill, $text, $stream);
        }

        $families = $text->fontFamily();
        $weight = $text->fontWeight();
        $style = $text->fontStyle();
        // SVG 2 §11.5 delegates font selection to CSS Fonts 4 wholesale,
        // so `<text>` inside an HTML document must select from the same
        // face set as the surrounding HTML - `@font-face` families
        // included. The embedding document offers those through the
        // provider; only when it has nothing for this family stack do we
        // fall back to the standard-14 mapping.
        $document = $this->documentFontProvider?->resolveDocumentFont($families, $weight, $style);
        $font = $document ?? new DocumentFont($this->fontResolver->resolve($families, $weight, $style));
        $this->activeFont = $font;
        $size = $text->fontSize() ?? 16.0;

        // SVG 2 §11.6 list-valued positioning. `<text x="10 20 30">ABC</text>`
        // positions each glyph individually. When any list has > 1 entry
        // the painter walks character-by-character; otherwise it falls
        // through to the cheaper single-Tj path. Single-value rotate
        // also takes the per-glyph code path so the rotation lands in
        // the text matrix.
        $xList = $text->x();
        $yList = $text->y();
        $dxList = $text->dx();
        $dyList = $text->dy();
        $rotateList = $text->rotate();
        $perGlyph = count($xList) > 1
            || count($yList) > 1
            || $dxList !== []
            || $dyList !== []
            || $rotateList !== [];

        // CSS Text Decoration 4 §6 — paint text-shadow layers BEHIND
        // the real text. Each layer is a sharp-offset copy of the
        // glyph run in the shadow colour. Blur-radius is parsed but
        // not currently rasterised (matches the html-to-pdf path,
        // which also ignores blur). The first listed shadow paints on
        // top of later shadows, so we reverse for back-to-front order.
        // Per-glyph positioning is intentionally NOT shadowed at v1 -
        // the WPT corpus only exercises single-position <text> with
        // shadows, and per-glyph shadows would compound complexity
        // without coverage to validate them.
        if (!$perGlyph) {
            $fillFallback = $fill instanceof SolidColor ? $fill->color : null;
            $shadows = self::parseTextShadow($text->textShadow(), $fillFallback);
            foreach (array_reverse($shadows) as $shadow) {
                $stream->saveGraphicsState();
                $this->setFillColor($stream, $shadow['color']);
                $stream->beginText()->setFont($font->font, $size);
                $sx = ($xList[0] ?? 0.0) + $shadow['offsetX'];
                $sy = ($yList[0] ?? 0.0) + $shadow['offsetY'];
                if ($this->compensateTextFlip) {
                    $stream->setTextMatrix(1.0, 0.0, 0.0, -1.0, $sx, $sy);
                } else {
                    $stream->moveTextPosition($sx, $sy);
                }
                $this->showTextRun($stream, $content);
                $stream->endText();
                $stream->restoreGraphicsState();
            }
        }

        $stream->beginText()->setFont($font->font, $size);
        if (!$perGlyph) {
            $x = $xList[0] ?? 0.0;
            $y = $yList[0] ?? 0.0;
            if ($this->compensateTextFlip) {
                // Under an outer Y-flip CTM (`SvgRenderer::draw` applies
                // one), `Td` would render glyphs upside-down. Setting Tm
                // with `d = -1` flips text space so the combined
                // `Tm · CTM` cancels the outer flip and glyphs render
                // upright at the SVG-stated baseline.
                $stream->setTextMatrix(1.0, 0.0, 0.0, -1.0, $x, $y);
            } else {
                $stream->moveTextPosition($x, $y);
            }
            $this->showTextRun($stream, $content);
        } else {
            $this->paintTextPerGlyph($content, $xList, $yList, $dxList, $dyList, $rotateList, $stream);
        }
        $stream->endText();
    }

    /**
     * Emit one `Tj` for `$text` against {@see $activeFont}.
     *
     * Composite (Type 0 / CID) fonts - the shape every embedded
     * `@font-face` takes - carry no single-byte text encoder, so
     * `showText()` would pass UTF-8 bytes straight through and the
     * viewer would read them as raw CIDs. Those go through
     * `showUnicodeText()` with the post-subset GID map instead. The
     * standard-14 Type 1 fonts keep the plain `showText()` path, where
     * the font handle's encoder does the UTF-8 -> byte translation.
     */
    private function showTextRun(ContentStream $stream, string $text): void
    {
        $font = $this->activeFont;
        if ($font !== null && $font->unicodeToGid !== []) {
            $stream->showUnicodeText($text, $font->unicodeToGid);
            return;
        }
        $stream->showText($text);
    }

    /**
     * Parse the raw CSS `text-shadow` value into renderable shadow
     * layers per CSS Text Decoration 4 §6.
     *
     * Grammar:
     *
     *     <text-shadow> = none | <shadow># (comma-separated layers)
     *     <shadow> = <length>{2,3} <color>?
     *
     * The two lengths are offset-x and offset-y; the optional third
     * is blur-radius (parsed and validated as non-negative, but the
     * painter does not currently rasterise blur — same posture as
     * the html-to-pdf path's `collectTextShadowLayers`). When a layer
     * omits the colour, it falls back to `$fillFallback` (the text's
     * own fill colour) and finally to opaque black.
     *
     * Returns an empty list for `null` input, `none`, or any layer
     * that fails to parse - SVG 2's "invalid → ignored" semantics
     * applied per-shadow keeps a malformed shadow from poisoning
     * earlier valid ones.
     *
     * @return list<array{offsetX: float, offsetY: float, color: ColorInterface}>
     */
    private static function parseTextShadow(?string $raw, ?ColorInterface $fillFallback): array
    {
        if ($raw === null) {
            return [];
        }
        $items = self::splitTextShadowItems($raw);
        $layers = [];
        foreach ($items as $item) {
            $layer = self::parseTextShadowLayer($item, $fillFallback);
            if ($layer !== null) {
                $layers[] = $layer;
            }
        }
        return $layers;
    }

    /**
     * Split a `text-shadow` value on top-level commas, preserving
     * commas inside `rgb(...)` / `rgba(...)` / `hsl(...)` etc.
     *
     * @return list<string>
     */
    private static function splitTextShadowItems(string $raw): array
    {
        $items = [];
        $depth = 0;
        $buffer = '';
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $ch = $raw[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth = max(0, $depth - 1);
            }
            if ($ch === ',' && $depth === 0) {
                $items[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') {
            $items[] = $buffer;
        }
        return array_values(array_filter(array_map('trim', $items), static fn($s) => $s !== ''));
    }

    /**
     * Parse a single shadow layer. Returns null on any structural
     * malformation (fewer than two lengths, blur < 0, more than three
     * lengths, unparseable colour token).
     *
     * @return array{offsetX: float, offsetY: float, color: ColorInterface}|null
     */
    private static function parseTextShadowLayer(string $raw, ?ColorInterface $fillFallback): ?array
    {
        // Tokenise on whitespace, but keep `rgb(1, 2, 3)` etc. as a
        // single token. Lengths are simple; the colour may contain
        // spaces inside its parens.
        $tokens = self::tokenizeTextShadowLayer($raw);
        if ($tokens === []) {
            return null;
        }
        $lengths = [];
        $colorRaw = null;
        foreach ($tokens as $token) {
            $length = self::parseLengthToken($token);
            if ($length !== null) {
                if (count($lengths) >= 3) {
                    return null;
                }
                $lengths[] = $length;
                continue;
            }
            if ($colorRaw !== null) {
                return null;
            }
            $colorRaw = $token;
        }
        if (count($lengths) < 2) {
            return null;
        }
        if (count($lengths) === 3 && $lengths[2] < 0.0) {
            // CSS Text Decoration 4: blur-radius must be non-negative.
            return null;
        }
        $color = null;
        if ($colorRaw !== null) {
            $color = SvgColor::parse($colorRaw);
            if ($color === null) {
                return null;
            }
        } else {
            $color = $fillFallback ?? new RgbColor(0.0, 0.0, 0.0);
        }
        return [
            'offsetX' => $lengths[0],
            'offsetY' => $lengths[1],
            'color' => $color,
        ];
    }

    /**
     * Whitespace-tokenise a single shadow layer keeping function
     * forms (`rgb(0, 128, 0)`) as one token.
     *
     * @return list<string>
     */
    private static function tokenizeTextShadowLayer(string $raw): array
    {
        $tokens = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $ch = $raw[$i];
            if ($ch === '(') {
                $depth++;
                $buffer .= $ch;
                continue;
            }
            if ($ch === ')') {
                $depth = max(0, $depth - 1);
                $buffer .= $ch;
                continue;
            }
            if ($depth === 0 && (ctype_space($ch) || $ch === ',')) {
                if ($buffer !== '') {
                    $tokens[] = $buffer;
                    $buffer = '';
                }
                continue;
            }
            $buffer .= $ch;
        }
        if ($buffer !== '') {
            $tokens[] = $buffer;
        }
        return $tokens;
    }

    /**
     * Parse a CSS length token used inside `text-shadow`. Supports
     * unitless (`0`), `px`, `pt`, `em`, and a couple of absolute
     * physical units. Returns null when the token doesn't look like
     * a length so the caller treats it as the colour slot instead.
     * Unit support is intentionally narrow - text-shadow in real
     * documents almost always uses `px` (or `0`).
     */
    private static function parseLengthToken(string $raw): ?float
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+))([a-zA-Z%]*)$/', $trimmed, $m) !== 1) {
            return null;
        }
        $value = (float) $m[1];
        $unit = strtolower($m[2]);
        return match ($unit) {
            '', 'px', 'pt' => $value,
            'in' => $value * 72.0,
            'cm' => $value * (72.0 / 2.54),
            'mm' => $value * (72.0 / 25.4),
            // em / rem / vw / vh / % aren't resolvable without a
            // containing context — reject so an unknown unit doesn't
            // silently land at 0.
            default => null,
        };
    }

    /**
     * Per-glyph positioning per SVG 2 §11.6: walk content character-by-
     * character, emit one `Tm` per explicitly-positioned glyph, then
     * batch the remaining characters as a single `Tj` so their natural
     * advance handles the trailing positioning.
     *
     * Sticky semantics: when a glyph specifies `x[i]` but not `y[i]`
     * (or vice-versa), the unspecified component carries over from the
     * previous glyph's position. SVG 2 actually defines this as "the
     * previous glyph's effective position" which requires knowing the
     * per-glyph advance — we don't have font metrics here at 3R+5, so
     * we use the last explicit value instead. The result is correct
     * for the common case where `x` and `y` have matching lengths.
     *
     * `dx[i]` / `dy[i]` are additive deltas applied to the resolved
     * `(stickyX, stickyY)` — SVG 2 §11.6 specifies them as relative
     * offsets from the glyph's natural position. Without font metrics
     * "natural position" collapses to "sticky position", so we treat
     * dx/dy as deltas from sticky. The deltas accumulate into sticky
     * so subsequent glyphs without their own dx/dy inherit the shift,
     * matching the common renderer behaviour for stacked offsets like
     * super/subscript adjustments.
     *
     * @param list<float> $xList
     * @param list<float> $yList
     * @param list<float> $dxList
     * @param list<float> $dyList
     * @param list<float> $rotateList
     */
    private function paintTextPerGlyph(
        string $content,
        array $xList,
        array $yList,
        array $dxList,
        array $dyList,
        array $rotateList,
        ContentStream $stream,
    ): void {
        $chars = mb_str_split($content);
        if ($chars === []) {
            return;
        }
        $explicitCount = max(
            count($xList),
            count($yList),
            count($dxList),
            count($dyList),
            count($rotateList),
        );
        $stickyX = $xList[0] ?? 0.0;
        $stickyY = $yList[0] ?? 0.0;
        $stickyRotate = $rotateList[0] ?? 0.0;

        $emitted = 0;
        foreach ($chars as $i => $char) {
            if ($i >= $explicitCount) {
                break;
            }
            $stickyX = $xList[$i] ?? $stickyX;
            $stickyY = $yList[$i] ?? $stickyY;
            $stickyRotate = $rotateList[$i] ?? $stickyRotate;
            if (isset($dxList[$i])) {
                $stickyX += $dxList[$i];
            }
            if (isset($dyList[$i])) {
                $stickyY += $dyList[$i];
            }

            $this->emitTextMatrix($stickyX, $stickyY, $stickyRotate, $stream);
            $this->showTextRun($stream, $char);
            $emitted++;
        }
        if ($emitted < count($chars)) {
            // Remaining glyphs ride the auto-advance from the last
            // positioned glyph — emit them as a single `Tj` so the
            // PDF reader inter-glyph kerning still applies.
            $this->showTextRun($stream, implode('', array_slice($chars, $emitted)));
        }
    }

    /**
     * Set the text matrix for a single positioned glyph. Combines the
     * per-glyph rotation with the optional outer-flip compensation
     * established by `SvgRenderer::draw`. Algebra:
     *
     *  Without flip: Tm = T(x,y) · R(θ) = [cosθ sinθ -sinθ cosθ x y]
     *  With    flip: Tm = T(x,y) · F · R(θ) = [cosθ sinθ sinθ -cosθ x y]
     *
     * where `F = [1 0 0 -1 0 0]` is the y-axis flip.
     */
    private function emitTextMatrix(float $x, float $y, float $rotateDegrees, ContentStream $stream): void
    {
        $rad = deg2rad($rotateDegrees);
        $cos = cos($rad);
        $sin = sin($rad);
        if ($this->compensateTextFlip) {
            $stream->setTextMatrix($cos, $sin, $sin, -$cos, $x, $y);
        } else {
            $stream->setTextMatrix($cos, $sin, -$sin, $cos, $x, $y);
        }
    }

    /**
     * Concatenate all `Phpdftk\Svg\Text` (data) descendants in document
     * order. SVG 2's whitespace handling is complex (xml:space="preserve"
     * vs the default collapse); 3P keeps things simple by emitting the
     * source bytes verbatim and leaving whitespace policy to the future
     * cascade-aware text painter.
     */
    private static function collectTextContent(Element $element): string
    {
        $out = '';
        foreach ($element->children as $child) {
            if ($child instanceof TextNode) {
                $out .= $child->data;
                continue;
            }
            if ($child instanceof Element) {
                $out .= self::collectTextContent($child);
            }
        }
        return $out;
    }

    private function paintPath(Path $path, ContentStream $stream): void
    {
        $commands = $path->d()->commands;
        if ($commands === []) {
            return;
        }
        $state = new PathPainterState();
        foreach ($commands as $command) {
            $this->emitPathCommand($command, $stream, $state);
        }
        $this->applyFillAndStroke($path, $stream);
    }

    private function emitPathCommand(
        PathCommand $command,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        match (true) {
            $command instanceof MoveTo => $this->emitMoveTo($command, $stream, $state),
            $command instanceof LineTo => $this->emitLineTo($command, $stream, $state),
            $command instanceof HorizontalLineTo => $this->emitHorizontalLineTo($command, $stream, $state),
            $command instanceof VerticalLineTo => $this->emitVerticalLineTo($command, $stream, $state),
            $command instanceof CurveTo => $this->emitCurveTo($command, $stream, $state),
            $command instanceof SmoothCurveTo => $this->emitSmoothCurveTo($command, $stream, $state),
            $command instanceof QuadraticCurveTo => $this->emitQuadraticCurveTo($command, $stream, $state),
            $command instanceof SmoothQuadraticCurveTo => $this->emitSmoothQuadraticCurveTo($command, $stream, $state),
            $command instanceof ArcTo => $this->emitArcTo($command, $stream, $state),
            $command instanceof ClosePath => $this->emitClosePath($stream, $state),
            // Sealed-via-convention: 3rd-party impls of `PathCommand` are
            // not part of the SVG spec, so we no-op silently rather than
            // throw — same posture the parser uses for unknown content.
            default => null,
        };
    }

    private function emitMoveTo(MoveTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->moveTo($x, $y);
        $state->moveTo($x, $y);
    }

    private function emitLineTo(LineTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->lineTo($x, $y);
        $state->lineTo($x, $y);
    }

    private function emitHorizontalLineTo(
        HorizontalLineTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        $x = $cmd->absolute ? $cmd->x : $state->currentX + $cmd->x;
        $stream->lineTo($x, $state->currentY);
        $state->lineTo($x, $state->currentY);
    }

    private function emitVerticalLineTo(
        VerticalLineTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        $y = $cmd->absolute ? $cmd->y : $state->currentY + $cmd->y;
        $stream->lineTo($state->currentX, $y);
        $state->lineTo($state->currentX, $y);
    }

    private function emitCurveTo(CurveTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$x1, $y1] = $this->resolvePoint($cmd->x1, $cmd->y1, $cmd->absolute, $state);
        [$x2, $y2] = $this->resolvePoint($cmd->x2, $cmd->y2, $cmd->absolute, $state);
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->curveTo($x1, $y1, $x2, $y2, $x, $y);
        $state->currentX = $x;
        $state->currentY = $y;
        $state->recordCubicControl($x2, $y2);
    }

    private function emitSmoothCurveTo(
        SmoothCurveTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        [$x1, $y1] = $state->reflectedCubicControl();
        [$x2, $y2] = $this->resolvePoint($cmd->x2, $cmd->y2, $cmd->absolute, $state);
        [$x, $y] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $stream->curveTo($x1, $y1, $x2, $y2, $x, $y);
        $state->currentX = $x;
        $state->currentY = $y;
        $state->recordCubicControl($x2, $y2);
    }

    /**
     * PDF has no native quadratic curve operator. Lift to cubic via the
     * standard `C1 = P0 + 2/3·(P1-P0)`, `C2 = P2 + 2/3·(P1-P2)` formula —
     * mathematically exact, no approximation error.
     */
    private function emitQuadraticCurveTo(
        QuadraticCurveTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        [$qx, $qy] = $this->resolvePoint($cmd->x1, $cmd->y1, $cmd->absolute, $state);
        [$ex, $ey] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $this->emitQuadraticAsCubic($state->currentX, $state->currentY, $qx, $qy, $ex, $ey, $stream);
        $state->currentX = $ex;
        $state->currentY = $ey;
        $state->recordQuadraticControl($qx, $qy);
    }

    private function emitSmoothQuadraticCurveTo(
        SmoothQuadraticCurveTo $cmd,
        ContentStream $stream,
        PathPainterState $state,
    ): void {
        [$qx, $qy] = $state->reflectedQuadraticControl();
        [$ex, $ey] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $this->emitQuadraticAsCubic($state->currentX, $state->currentY, $qx, $qy, $ex, $ey, $stream);
        $state->currentX = $ex;
        $state->currentY = $ey;
        $state->recordQuadraticControl($qx, $qy);
    }

    private function emitArcTo(ArcTo $cmd, ContentStream $stream, PathPainterState $state): void
    {
        [$endX, $endY] = $this->resolvePoint($cmd->x, $cmd->y, $cmd->absolute, $state);
        $segments = ArcToCubic::convert(
            $state->currentX,
            $state->currentY,
            $cmd->rx,
            $cmd->ry,
            $cmd->xAxisRotation,
            $cmd->largeArc,
            $cmd->sweep,
            $endX,
            $endY,
        );
        if ($segments === []) {
            // Degenerate: zero-length or zero-radius. Per SVG 2 §9.5.1,
            // an arc with a zero radius is rendered as a straight line.
            if ($cmd->rx === 0.0 || $cmd->ry === 0.0) {
                $stream->lineTo($endX, $endY);
                $state->lineTo($endX, $endY);
            }
            return;
        }
        foreach ($segments as $segment) {
            $stream->curveTo(
                $segment['x1'],
                $segment['y1'],
                $segment['x2'],
                $segment['y2'],
                $segment['x'],
                $segment['y'],
            );
        }
        $state->currentX = $endX;
        $state->currentY = $endY;
        $state->clearControlPoints();
    }

    private function emitClosePath(ContentStream $stream, PathPainterState $state): void
    {
        $stream->closePath();
        $state->closeSubpath();
    }

    private function emitQuadraticAsCubic(
        float $p0x,
        float $p0y,
        float $p1x,
        float $p1y,
        float $p2x,
        float $p2y,
        ContentStream $stream,
    ): void {
        $twoThirds = 2.0 / 3.0;
        $c1x = $p0x + $twoThirds * ($p1x - $p0x);
        $c1y = $p0y + $twoThirds * ($p1y - $p0y);
        $c2x = $p2x + $twoThirds * ($p1x - $p2x);
        $c2y = $p2y + $twoThirds * ($p1y - $p2y);
        $stream->curveTo($c1x, $c1y, $c2x, $c2y, $p2x, $p2y);
    }

    /**
     * @return array{float, float}
     */
    private function resolvePoint(float $x, float $y, bool $absolute, PathPainterState $state): array
    {
        if ($absolute) {
            return [$x, $y];
        }
        return [$state->currentX + $x, $state->currentY + $y];
    }

    // The paint* entry points below share their geometry with the
    // emit*Path methods the clip-region builder uses. Keeping ONE
    // resolver per shape is what makes SVG 2 §10.1 geometry (CSS-
    // declared `cx` / `r` / `width` / …) reach BOTH the painter and
    // `clip-path`; the two used to compute coordinates separately and
    // only the clip side was ever taught about the cascade.

    private function paintRect(Rect $rect, ContentStream $stream): void
    {
        if (!$this->emitRectPath($rect, $stream)) {
            return;
        }
        $this->applyFillAndStroke($rect, $stream);
    }

    private function paintCircle(Circle $circle, ContentStream $stream): void
    {
        if (!$this->emitCirclePath($circle, $stream)) {
            return;
        }
        $this->applyFillAndStroke($circle, $stream);
    }

    private function paintEllipse(Ellipse $ellipse, ContentStream $stream): void
    {
        if (!$this->emitEllipsePathFor($ellipse, $stream)) {
            return;
        }
        $this->applyFillAndStroke($ellipse, $stream);
    }

    private function paintLine(Line $line, ContentStream $stream): void
    {
        // Lines never enclose an area; only stroke is meaningful. Skip
        // entirely when stroke resolves to no paint — emitting a stroke
        // op with no colour would otherwise draw a black line by
        // accident.
        $stroke = $line->stroke();
        if ($stroke === null || $stroke instanceof None_) {
            return;
        }
        if (!$this->applyStrokePaint($stroke, $line, $stream)) {
            return;
        }
        $stream->moveTo($line->x1(), $line->y1())
            ->lineTo($line->x2(), $line->y2())
            ->stroke();
    }

    private function paintPolyline(Polyline $polyline, ContentStream $stream): void
    {
        $points = $polyline->points();
        if (count($points) < 2) {
            return;
        }
        $this->emitPolyPath($stream, $points, closed: false);
        $this->applyFillAndStroke($polyline, $stream);
    }

    private function paintPolygon(Polygon $polygon, ContentStream $stream): void
    {
        $points = $polygon->points();
        if (count($points) < 3) {
            return;
        }
        $this->emitPolyPath($stream, $points, closed: true);
        $this->applyFillAndStroke($polygon, $stream);
    }

    /**
     * Standard 4-cubic-Bézier ellipse approximation. Maximum radial
     * error against the true ellipse is ~0.027 % — well below print
     * resolution for any reasonable PDF size.
     */
    private function emitEllipsePath(
        ContentStream $stream,
        float $cx,
        float $cy,
        float $rx,
        float $ry,
    ): void {
        $kx = $rx * self::KAPPA;
        $ky = $ry * self::KAPPA;
        $stream
            ->moveTo($cx + $rx, $cy)
            ->curveTo($cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry)
            ->curveTo($cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy)
            ->curveTo($cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry)
            ->curveTo($cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy)
            ->closePath();
    }

    /**
     * @param list<array{float, float}> $points
     */
    private function emitPolyPath(ContentStream $stream, array $points, bool $closed): void
    {
        $first = $points[0];
        $stream->moveTo($first[0], $first[1]);
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $stream->lineTo($points[$i][0], $points[$i][1]);
        }
        if ($closed) {
            $stream->closePath();
        }
    }

    /**
     * Resolve the element's fill and stroke and emit the right PDF
     * paint operator combination. Defaults follow SVG 2 §13.2.1 — black
     * fill, no stroke — so a bare `<rect width=… height=…/>` paints as
     * a filled black rectangle.
     */
    private function applyFillAndStroke(Element $element, ContentStream $stream): void
    {
        $fill = $element->fill();
        $stroke = $element->stroke();

        // SVG 2 §13.3 — when `fill` resolves to a `<pattern>`, the pattern
        // tile paints clipped to the shape (replacing the flat-colour
        // fill). Handle it before the plain paint path: the current path
        // just built by the caller is consumed as the clip region.
        $pattern = $this->resolveFillPattern($fill);
        if ($pattern !== null) {
            $this->paintPatternFill($pattern, $element, $stream, $stroke);
            return;
        }

        $hasFill = $this->applyFillPaint($fill, $element, $stream);
        $hasStroke = $this->applyStrokePaint($stroke, $element, $stream);

        $rule = $element->fillRule() ?? 'nonzero';

        if ($hasFill && $hasStroke) {
            $rule === 'evenodd' ? $stream->fillAndStrokeEvenOdd() : $stream->fillAndStroke();
            return;
        }
        if ($hasFill) {
            $rule === 'evenodd' ? $stream->fillEvenOdd() : $stream->fill();
            return;
        }
        if ($hasStroke) {
            $stream->stroke();
            return;
        }
        // Path constructed but nothing wants to paint it — discard so
        // we don't bake a leftover current-path into the graphics state.
        $stream->endPath();
    }

    /** Upper bound on tile repetitions, guarding against pathological patterns. */
    private const int MAX_PATTERN_TILES = 4096;

    /**
     * Resolve a `fill` paint to the `<pattern>` it references, or null
     * when it isn't a `url(#id)` pointing at a pattern (gradients and
     * flat colours take the ordinary paint path).
     */
    private function resolveFillPattern(?Paint $paint): ?Pattern
    {
        if (!$paint instanceof Url || $this->document === null) {
            return null;
        }
        $target = $this->document->findByFragment($paint->id);
        if (!$target instanceof Pattern) {
            return null;
        }
        $resolved = $this->resolvePatternTemplate($target);
        // CSS Transforms 1 §11 — a non-invertible `patternTransform`
        // invalidates the paint server, so the caller takes the
        // ordinary paint path and lands on the `<paint>` fallback.
        $transform = $resolved->patternTransform();
        if ($transform !== null && !$transform->isInvertible()) {
            return null;
        }
        return $resolved;
    }

    /**
     * SVG 2 §13.3 — resolve a `<pattern>`'s template chain.
     *
     * A pattern may reference another via `href` (or the legacy
     * `xlink:href`, which `Pattern::href()` already deprioritises).
     * Attributes the referencing pattern does not itself specify are
     * inherited from the referenced one, and a pattern with no element
     * children of its own paints the referenced pattern's children.
     * That is what makes
     *
     *     <pattern id="Copy" href="#Base"></pattern>
     *
     * render exactly like `#Base`.
     *
     * Returns `$pattern` unchanged when it has no `href` (the common
     * case, so the walk costs nothing). Otherwise it returns a synthetic
     * merged pattern; the merged children keep their ORIGINAL parents so
     * style inheritance still reads the defining pattern's cascade.
     *
     * Cycles (`a -> b -> a`) terminate at the first repeat, so a
     * malformed document can't spin the renderer.
     */
    private function resolvePatternTemplate(Pattern $pattern): Pattern
    {
        if ($pattern->href() === null || $this->document === null) {
            return $pattern;
        }
        /** @var list<Pattern> $chain */
        $chain = [];
        $seen = [];
        $current = $pattern;
        while (count($chain) < self::MAX_PATTERN_TEMPLATE_DEPTH) {
            $id = spl_object_id($current);
            if (isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;
            $chain[] = $current;
            $href = $current->href();
            if ($href === null || !str_starts_with($href, '#')) {
                break;
            }
            $next = $this->document->findByFragment(substr($href, 1));
            if (!$next instanceof Pattern) {
                break;
            }
            $current = $next;
        }
        if (count($chain) < 2) {
            return $pattern;
        }
        $merged = new Pattern();
        // Nearest definition wins: seed from the far end of the chain and
        // let each closer link overwrite, so the referencing pattern's own
        // attributes end up on top.
        foreach (array_reverse($chain) as $link) {
            foreach (self::PATTERN_INHERITED_ATTRIBUTES as $name) {
                $value = $link->getAttribute($name);
                if ($value !== null) {
                    $merged->setAttribute($name, $value);
                }
            }
        }
        foreach ($chain as $link) {
            if (self::hasElementChildren($link)) {
                $merged->children = $link->children;
                break;
            }
        }
        return $merged;
    }

    /**
     * SVG 2 §13.3 — paint `$pattern` into `$element`'s fill region.
     *
     * The caller has just constructed the shape's path on the stream; we
     * turn it into a clip (`W n`) and replicate the pattern tile across
     * the shape's bounding box inside that clip. Because this runs inside
     * the element's own `q cm … Q` transform wrap (see paintElement), the
     * tiles inherit the element's user-space transform automatically —
     * which is why we tile as ordinary geometry rather than emitting a
     * PDF tiling pattern (whose `/Matrix` is anchored to the page's
     * default coordinate system and ignores the fill-time CTM).
     *
     * A stroke, if present, re-emits the path after the fill (the clip
     * consumed the original current path).
     */
    private function paintPatternFill(
        Pattern $pattern,
        Element $element,
        ContentStream $stream,
        ?Paint $stroke,
    ): void {
        $bbox = BoundingBox::compute($element);
        $tile = $bbox !== null ? $this->resolvePatternTile($pattern, $bbox) : null;
        // Whitespace between the tags of an otherwise empty `<pattern>`
        // parses as a text node, so "has children" has to mean "has
        // ELEMENT children" - otherwise `<pattern id="x">\n</pattern>`
        // reads as content-bearing and paints an empty tile.
        $hasContent = self::hasElementChildren($pattern);
        if ($bbox === null || $tile === null || !$hasContent) {
            // Nothing paintable — drop the current path (avoid a stray
            // fill) but still honour a stroke channel if the shape has one.
            $stream->endPath();
            $this->strokeAfterPattern($element, $stream, $stroke);
            return;
        }

        $stream->saveGraphicsState();
        $stream->clip()->endPath(); // W n — shape path becomes the clip
        $this->emitPatternTiles($pattern, $tile, $bbox, $stream);
        $stream->restoreGraphicsState();

        $this->strokeAfterPattern($element, $stream, $stroke);
    }

    /**
     * Whether `$element` has at least one child ELEMENT - as opposed to
     * the whitespace text node that source formatting leaves inside an
     * empty container.
     */
    private static function hasElementChildren(Element $element): bool
    {
        foreach ($element->children as $child) {
            if ($child instanceof Element) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve the pattern's tile rectangle in the referencing element's
     * user space. `patternUnits="objectBoundingBox"` (the default) reads
     * x/y/width/height as fractions of the element bbox; `userSpaceOnUse`
     * reads them as plain user units.
     *
     * @param array{minX: float, minY: float, width: float, height: float} $bbox
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private function resolvePatternTile(Pattern $pattern, array $bbox): ?array
    {
        if ($pattern->patternUnits() === 'objectBoundingBox') {
            $x = $bbox['minX'] + $pattern->x() * $bbox['width'];
            $y = $bbox['minY'] + $pattern->y() * $bbox['height'];
            $w = $pattern->width() * $bbox['width'];
            $h = $pattern->height() * $bbox['height'];
        } else {
            $x = $pattern->x();
            $y = $pattern->y();
            $w = $pattern->width();
            $h = $pattern->height();
        }
        return $w > 0.0 && $h > 0.0 ? ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h] : null;
    }

    /**
     * Replicate the pattern tile across the shape's bounding box. Each
     * cell is translated onto the tile grid, clipped to the tile rect so
     * content can't bleed into neighbours (SVG 2 §13.3), and its children
     * painted. The base cell (i=j=0) paints children at their authored
     * coordinates; further cells translate by whole tile steps.
     *
     * @param array{x: float, y: float, w: float, h: float} $tile
     * @param array{minX: float, minY: float, width: float, height: float} $bbox
     */
    private function emitPatternTiles(
        Pattern $pattern,
        array $tile,
        array $bbox,
        ContentStream $stream,
    ): void {
        $pw = $tile['w'];
        $ph = $tile['h'];
        $i0 = (int) floor(($bbox['minX'] - $tile['x']) / $pw);
        $i1 = (int) ceil(($bbox['minX'] + $bbox['width'] - $tile['x']) / $pw);
        $j0 = (int) floor(($bbox['minY'] - $tile['y']) / $ph);
        $j1 = (int) ceil(($bbox['minY'] + $bbox['height'] - $tile['y']) / $ph);
        if (($i1 - $i0) * ($j1 - $j0) > self::MAX_PATTERN_TILES) {
            return;
        }
        for ($j = $j0; $j < $j1; $j++) {
            for ($i = $i0; $i < $i1; $i++) {
                $stream->saveGraphicsState();
                $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, $i * $pw, $j * $ph);
                $stream->rectangle($tile['x'], $tile['y'], $pw, $ph);
                $stream->clip()->endPath();
                $this->paintChildren($pattern, $stream);
                $stream->restoreGraphicsState();
            }
        }
    }

    /**
     * Stroke the shape after a pattern fill. The pattern path was
     * consumed by the clip, so re-emit it and stroke when the element
     * carries a real stroke paint.
     */
    private function strokeAfterPattern(Element $element, ContentStream $stream, ?Paint $stroke): void
    {
        if ($stroke === null || $stroke instanceof None_) {
            return;
        }
        if (!$this->applyStrokePaint($stroke, $element, $stream)) {
            return;
        }
        $this->emitElementPath($element, $stream);
        $stream->stroke();
    }

    /**
     * Configure the fill colour and report whether the element wants a
     * fill at all. Default (null paint) = SVG-spec black fill; explicit
     * `none` = no fill; `currentColor` resolves to black at 3K (the
     * cascade-resolved `color` lands later). Gradient/pattern `url(#…)`
     * is deferred to 3O.
     */
    private function applyFillPaint(?Paint $paint, Element $element, ContentStream $stream): bool
    {
        if ($paint instanceof None_) {
            return false;
        }
        if ($paint instanceof Url) {
            if ($this->gradientPainter?->applyAsFill($paint->id, $element, $stream, $this->currentMatrix()) ?? false) {
                return true;
            }
            // SVG 2 §13.2 — the reference didn't resolve to a usable
            // paint server, so the fallback paint applies. With NO
            // fallback the element is in error and is simply not
            // rendered: several WPT fixtures stack a correct shape
            // underneath and rely on the broken one staying invisible,
            // so falling through to the black default here would paint
            // over them.
            if ($paint->fallback === null) {
                return false;
            }
            return $this->applyFillPaint($paint->fallback, $element, $stream);
        }
        if ($paint instanceof SolidColor) {
            $this->setFillColor($stream, $paint->color);
            return true;
        }
        if ($paint instanceof CurrentColor) {
            $this->setFillColor($stream, $this->currentColorOf($element));
            return true;
        }
        // null → SVG 2 §13.2.1 default of black.
        $stream->setFillColorRGB(0.0, 0.0, 0.0);
        return true;
    }

    /**
     * CSS Color 4 §3.2 — the used value of `currentColor` is the
     * computed value of the element's own `color` property, whose
     * initial value is black.
     *
     * `color` inherits, and the cascade projection is what carries an
     * ancestor's declaration down to the element, so this reads the
     * element's own resolved value rather than walking parents here.
     */
    private function currentColorOf(Element $element): ColorInterface
    {
        $raw = $element->colorValue();
        if ($raw !== null) {
            $color = SvgColor::parse($raw);
            if ($color !== null) {
                return $color;
            }
        }
        return new RgbColor(0, 0, 0);
    }

    /**
     * Configure the stroke colour and report whether the element wants
     * to stroke. Default (null paint) = SVG-spec "no stroke"; explicit
     * `none` = no stroke.
     */
    private function applyStrokePaint(?Paint $paint, Element $element, ContentStream $stream): bool
    {
        if ($paint === null || $paint instanceof None_) {
            return false;
        }
        if ($paint instanceof Url) {
            if ($this->gradientPainter?->applyAsStroke($paint->id, $element, $stream, $this->currentMatrix()) ?? false) {
                return true;
            }
            // SVG 2 §13.2, as in applyFillPaint(): fallback, or don't
            // stroke at all.
            if ($paint->fallback === null) {
                return false;
            }
            return $this->applyStrokePaint($paint->fallback, $element, $stream);
        }
        if ($paint instanceof SolidColor) {
            $this->setStrokeColor($stream, $paint->color);
            return true;
        }
        $this->setStrokeColor($stream, $this->currentColorOf($element));
        return true;
    }

    private function setFillColor(ContentStream $stream, ColorInterface $color): void
    {
        match (true) {
            $color instanceof RgbColor => $stream->setFillRgbColor($color),
            $color instanceof CmykColor => $stream->setFillCmykColor($color),
            $color instanceof GrayColor => $stream->setFillGrayColor($color),
            default => $stream->setFillColorRGB(0.0, 0.0, 0.0),
        };
    }

    private function setStrokeColor(ContentStream $stream, ColorInterface $color): void
    {
        match (true) {
            $color instanceof RgbColor => $stream->setStrokeRgbColor($color),
            $color instanceof CmykColor => $stream->setStrokeCmykColor($color),
            $color instanceof GrayColor => $stream->setStrokeGrayColor($color),
            default => $stream->setStrokeColorRGB(0.0, 0.0, 0.0),
        };
    }
}
