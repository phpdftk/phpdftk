<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Painter;

use Phpdftk\Css\Cascade\WritingMode;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\ColorConverter;
use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\HueInterpolation;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Layout\BoxGeometry;
use Phpdftk\HtmlToPdf\Layout\InlineFragment;
use Phpdftk\HtmlToPdf\Layout\LineBox;
use Phpdftk\HtmlToPdf\Layout\MultiColumnLayout;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Font\RegisteredFont;
use Phpdftk\Pdf\Writer\Font as WriterFont;
use Phpdftk\Pdf\Writer\Page as WriterPage;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\ResourceLoader\Exception\FetchFailedException;
use Phpdftk\ResourceLoader\Exception\SsrfBlockedException;
use Phpdftk\ResourceLoader\ResourceLoader as HttpResourceLoader;

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
         * Optional broader sandbox the resolved path must remain
         * under. Defaults to `baseDir`. Set wider when relative
         * URLs are expected to escape `baseDir` via `..` walks —
         * e.g. WPT refs in `reference/` loading `../support/img.png`.
         */
        private readonly ?string $sandboxRoot = null,
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
         * Map of `lowercase font-family → OpenTypeData`. Parallel to
         * `$registeredFonts` (which carries PDF-side handles); this
         * carries the parsed-font side so inline foreign-content
         * painters can hand the full font data to embedded
         * renderers (e.g. paintInlineMath threads the math element's
         * resolved font into MathmlRenderer so it picks up the
         * font's MATH-table constants like FractionRuleThickness).
         *
         * Keyed by lowercase family name to match how Renderer
         * builds its `$fontMap` from @font-face and
         * RendererOptions::fontMap.
         *
         * @var array<string, \Phpdftk\FontParser\FontFaceData>
         */
        private readonly array $fontDataByFamily = [],
        /**
         * Page width in PDF user-space units. Used by per-axis
         * `overflow-x` / `overflow-y` clipping to extend the clip
         * rect across the unconstrained axis. Defaults to a value
         * large enough that any reasonable page-width effectively
         * disables horizontal clipping when only the Y axis clips.
         */
        private readonly float $pageWidth = 100000.0,
        /**
         * Optional `phpdftk/resource-loader` for `http(s)://`
         * `<img src>`, `<picture><source>`, `<iframe src>` etc.
         * hrefs. When null (the default — preserves existing
         * behaviour byte-for-byte), network hrefs drop silently per
         * the same SVG 2 §12.6 / image-loading no-image outcome
         * pattern. When supplied, the loader runs (with its SSRF
         * guard, redirect handling, body cap, and MIME sniffing)
         * and the embedded bytes get materialised to a temp file
         * the same way `data:` URLs do.
         */
        private readonly ?HttpResourceLoader $resourceLoader = null,
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

    /**
     * Cache `src` → parsed SvgDocument (or `false` when parsing failed)
     * so each unique SVG background-image is only read + parsed once.
     *
     * @var array<string, \Phpdftk\Svg\SvgDocument|false>
     */
    private array $svgDocumentCache = [];

    /**
     * Lazy-built SvgRenderer for SVG background-image painting. SVG
     * resources (gradients, fonts) register on the page on first draw.
     */
    private ?\Phpdftk\SvgToPdf\SvgRenderer $svgRenderer = null;

    /**
     * Lazy-built adapter that converts an inline-SVG HTML DOM subtree
     * into a typed SvgDocument the renderer can paint. Caches its
     * results by element identity so a multi-page document only pays
     * the parse cost once per inline SVG.
     */
    private ?\Phpdftk\HtmlToPdf\Svg\InlineSvgAdapter $inlineSvgAdapter = null;

    /**
     * Sibling of $inlineSvgAdapter for MathML. Both adapters share
     * {@see \Phpdftk\HtmlToPdf\ForeignContent\DomXmlSerializer} but
     * keep their own caches so a fixture with both inline SVG and
     * inline MathML doesn't conflate them.
     */
    private ?\Phpdftk\HtmlToPdf\Mathml\InlineMathmlAdapter $inlineMathmlAdapter = null;

    /**
     * Lazy-built MathML renderer. Same lifecycle as $svgRenderer —
     * holds a reference to the writer + page once, registers the
     * standard fonts on first draw.
     */
    private ?\Phpdftk\MathmlToPdf\MathmlRenderer $mathmlRenderer = null;

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

    /**
     * Box whose background was propagated to the canvas this paint
     * pass (CSS Backgrounds 3 §3.11.2) — either the root or, when the
     * root's background is transparent and the root is an HTML
     * document, the first body child. `paintBackground` skips this box
     * to avoid double-painting at the box's geometry.
     */
    private ?Box $propagatedBgBox = null;

    /**
     * Body box whose `overflow` propagated to the root canvas this
     * paint pass (CSS Overflow 3 §3.3). When the root's overflow is
     * `visible` and the body's isn't, the body's value propagates to
     * the root and the body's OWN overflow is treated as `visible`
     * for paint purposes. `shouldOverflowClip` suppresses the body's
     * descendant clip so the test/ref pair (which sets the
     * post-propagation state on `html` directly) renders identically.
     */
    private ?Box $propagatedOverflowBox = null;

    /**
     * Root box that received the propagated overflow this paint pass.
     * `axisClips` consults this to apply the body's overflow keyword
     * to the root (instead of the root's own visible default) so the
     * canvas gets the spec-mandated post-propagation behaviour.
     */
    private ?Box $propagatedOverflowRoot = null;

    public function paint(Box $root, ContentStream $stream): void
    {
        $this->collectedLinks = [];
        $this->imageNameCache = [];
        // CSS Backgrounds 3 §3.11.2 — if the root has a non-transparent
        // background, paint the entire canvas with it BEFORE walking the
        // tree (so descendants paint on top). When the root is
        // transparent but its body child carries a background, the body
        // propagates to the canvas instead. The propagated box's own
        // paint-background pass is suppressed by the propagatedBgBox check.
        $this->paintCanvasBackgroundFromRoot($root, $stream);
        // CSS Overflow 3 §3.3 — overflow propagation. When the root's
        // overflow is `visible` and the body's isn't, the body's
        // overflow value propagates to the root and the body's own
        // overflow becomes `visible`. Both sides matter: we suppress
        // the body's descendant clip AND apply the body's overflow
        // keyword to the root so the root clips at its content area
        // (which in our auto-height renderer wraps the body's outer
        // box). `shouldOverflowClip` / `axisClips` consult the
        // propagated-* tracking to redirect the per-axis clip.
        $this->resolveOverflowPropagation($root);
        $this->paintBox($root, $stream);
        $this->propagatedBgBox = null;
        $this->propagatedOverflowBox = null;
        $this->propagatedOverflowRoot = null;
    }

    private function resolveOverflowPropagation(Box $root): void
    {
        if ($this->boxIsPaintContained($root)) {
            return;
        }
        if (!$this->boxOverflowIsVisible($root)) {
            // Root itself constrains — no propagation per spec; the
            // root's overflow applies at the root and the body's
            // overflow applies at the body normally.
            return;
        }
        $body = $this->findBodyChild($root);
        if ($body === null || $this->boxIsPaintContained($body)) {
            return;
        }
        if (!$this->boxOverflowIsVisible($body)) {
            $this->propagatedOverflowBox = $body;
            $this->propagatedOverflowRoot = $root;
        }
    }

    private function boxOverflowIsVisible(Box $box): bool
    {
        return !$this->axisClips($box, 'x') && !$this->axisClips($box, 'y');
    }

    private function paintCanvasBackgroundFromRoot(Box $root, ContentStream $stream): void
    {
        $source = $root;
        // CSS Containment 3 §4.4 — `contain: paint` (or `contain:
        // layout`, which implies paint containment in the propagation
        // sense) on the root element creates a stacking + paint
        // boundary, so neither the root's nor the body's background
        // propagates to the canvas. Bail out before doing any canvas
        // paint when the root is paint-contained.
        if ($this->boxIsPaintContained($source)) {
            return;
        }
        if (!$this->boxHasPaintableBackground($source)) {
            // CSS Backgrounds 3 §3.11.2 second paragraph — when the root
            // element of an HTML/XHTML document has a transparent
            // background, the canvas uses the *first body child's*
            // background instead, and the body itself paints
            // transparent. Skipping the body lookup for non-HTML root
            // elements is harmless: only HTML structure has a `<body>`.
            $body = $this->findBodyChild($root);
            if ($body === null
                || !$this->boxHasPaintableBackground($body)
                // CSS Containment 3 §4.4 — a paint-contained body does
                // not propagate either. The body's bg paints at the
                // body's own geometry, and the canvas stays at the
                // initial value (transparent).
                || $this->boxIsPaintContained($body)
            ) {
                return;
            }
            $source = $body;
        }
        $this->propagatedBgBox = $source;
        $color = $this->resolveColorWithCurrentColor(
            $source->style->get('background-color'),
            $source,
        );
        $bgImage = $source->style->get('background-image');
        $hasColor = $color instanceof Color && $color->a > 0.0;
        $hasImage = $bgImage instanceof \Phpdftk\Css\Value\Url;
        $hasGradient = $bgImage instanceof \Phpdftk\Css\Value\LinearGradient;
        $hasRadial = $bgImage instanceof \Phpdftk\Css\Value\RadialGradient;
        // The canvas rect is the entire page in PDF user-space — the
        // painter's `pageHeight` is the top, `pageWidth` the right edge.
        // Layout-Y 0 corresponds to the page top; emitRect handles the
        // CSS-to-PDF Y flip internally.
        if ($hasColor) {
            $this->emitRect($stream, 0.0, 0.0, $this->pageWidth, $this->pageHeight, fill: $color);
        }
        if ($hasImage) {
            $sizeValue = $source->style->get('background-size');
            $positionValue = $source->style->get('background-position');
            $repeatValue = $source->style->get('background-repeat');
            // CSS 2.1 §14.2 / Backgrounds 3 §3.11.2 — a propagated root
            // background PAINTS over the whole canvas but is POSITIONED /
            // tiled as if painted for the source element's own box (its
            // padding box, the default `background-origin`). So the image
            // anchors at the element's margin offset, not the page corner
            // (e.g. `repeat-x top left` on an `html` with `margin: 1in`
            // puts the stripe 1in down, not at y=0).
            $this->paintBackgroundImage(
                $bgImage,
                $stream,
                0.0,
                0.0,
                $this->pageWidth,
                $this->pageHeight,
                $sizeValue,
                $positionValue,
                $repeatValue,
                $this->propagatedOriginRect($source),
            );
        }
        if ($hasGradient) {
            $this->paintLinearGradient($bgImage, $stream, 0.0, 0.0, $this->pageWidth, $this->pageHeight);
        }
        if ($hasRadial) {
            $this->paintRadialGradient($bgImage, $stream, 0.0, 0.0, $this->pageWidth, $this->pageHeight);
        }
        if ($bgImage instanceof \Phpdftk\Css\Value\ConicGradient) {
            $this->paintConicGradient($bgImage, $stream, 0.0, 0.0, $this->pageWidth, $this->pageHeight);
        }
    }

    /**
     * The background positioning area for a propagated root/body
     * background: the source element's padding box (the default
     * `background-origin`), in layout-Y coordinates. `paintBackgroundImage`
     * anchors `background-position` / tiling to this rect while painting
     * over the whole canvas.
     *
     * @return array{x: float, top: float, width: float, height: float}
     */
    private function propagatedOriginRect(Box $source): array
    {
        $g = $source->geometry;
        return [
            'x' => $g->x - $g->paddingLeft,
            'top' => $g->y - $g->paddingTop,
            'width' => $g->paddingLeft + $g->width + $g->paddingRight,
            'height' => $g->paddingTop + $g->height + $g->paddingBottom,
        ];
    }

    private function boxHasPaintableBackground(Box $box): bool
    {
        $color = $this->resolveColorWithCurrentColor(
            $box->style->get('background-color'),
            $box,
        );
        $bgImage = $box->style->get('background-image');
        if ($color instanceof Color && $color->a > 0.0) {
            return true;
        }
        // Any recognised background-image layer (url, gradient, image(),
        // or an image-set() wrapping one) makes the background paintable.
        // Delegating to extractBackgroundLayers keeps this in lockstep with
        // what the paint loop will actually draw.
        return $this->extractBackgroundLayers($bgImage) !== [];
    }

    /**
     * If `$value` is the `currentcolor` keyword, resolve it against
     * the box's `color` property per CSS Color 3 §3.2 / CSS Color 4
     * §3.6. Other values (already-typed `Color`, `null`, other
     * keywords) pass through unchanged. The painter calls this at
     * any property that documents `currentcolor` as a valid value
     * (`background-color`, `border-*-color` initials, etc.).
     */
    private function resolveColorWithCurrentColor(?\Phpdftk\Css\Value\Value $value, Box $box): ?\Phpdftk\Css\Value\Value
    {
        // CSS Color 5 §5 — `light-dark(<light>, <dark>)` picks the
        // arm matching the using element's `color-scheme`. Inspect the
        // box's resolved `color-scheme` to decide: when the cascaded
        // value lists `dark` (e.g. `color-scheme: dark` or `color-
        // scheme: light dark` with dark first), pick the dark arm.
        // Anything else (including the `normal` initial or
        // `color-scheme: light`) falls back to the light arm — the
        // spec's default.
        if ($value instanceof \Phpdftk\Css\Value\LightDark) {
            $value = $this->preferredLightDarkArm($box, $value);
        }
        if ($value instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($value->name) === 'currentcolor'
        ) {
            $current = $box->style->get('color');
            if ($current instanceof \Phpdftk\Css\Value\LightDark) {
                $current = $this->preferredLightDarkArm($box, $current);
            }
            $value = $current instanceof Color ? $current : null;
        }
        if ($value instanceof \Phpdftk\Css\Value\RelativeColor) {
            $value = $this->resolveRelativeColor($value, $box);
        }
        if ($value instanceof Color && $value->space !== \Phpdftk\Css\Value\ColorSpace::sRGB) {
            // CSS Color 4 §17 — every wide-gamut / polar / Lab-family
            // value stores its native components on the Color struct
            // (`color(display-p3 …)` etc.). Convert to sRGB before the
            // painter emits `rg` so PDF's DeviceRGB sees in-gamut
            // values; out-of-gamut components clip at the boundary.
            return \Phpdftk\Css\Value\ColorConverter::toSrgb($value);
        }
        return $value;
    }

    /**
     * Pick the appropriate arm of a `light-dark()` expression based on
     * the box's resolved `color-scheme`. CSS Color 5 §5 — the spec
     * default is the light arm; `color-scheme: dark` (or a list whose
     * first preferred scheme is dark) selects the dark arm.
     */
    private function preferredLightDarkArm(Box $box, \Phpdftk\Css\Value\LightDark $value): \Phpdftk\Css\Value\Value
    {
        $scheme = $box->style->get('color-scheme');
        $isDark = false;
        if ($scheme instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($scheme->name) === 'dark'
        ) {
            $isDark = true;
        } elseif ($scheme instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($scheme->values as $entry) {
                if (!$entry instanceof \Phpdftk\Css\Value\Keyword) {
                    continue;
                }
                $name = strtolower($entry->name);
                if ($name === 'dark') {
                    $isDark = true;
                    break;
                }
                if ($name === 'light') {
                    break;
                }
            }
        }
        return $isDark ? $value->dark : $value->light;
    }

    /**
     * CSS Color 5 §4 relative-color resolution. Returns the resolved
     * Color or null when the relative-color expression can't be
     * statically evaluated.
     *
     * The common case the WPT relative-currentcolor cluster exercises
     * is `<colorFn>(from currentColor c1 c2 c3)` where the components
     * are the bare slot identifiers (`l a b`, `r g b`, …) — that just
     * round-trips the source through the target color space.
     */
    private function resolveRelativeColor(\Phpdftk\Css\Value\RelativeColor $rc, Box $box): ?Color
    {
        $source = $rc->source;
        if ($source instanceof \Phpdftk\Css\Value\Keyword) {
            $name = strtolower($source->name);
            if ($name === 'transparent') {
                $source = new Color(0.0, 0.0, 0.0, 0.0);
            } elseif ($name === 'currentcolor') {
                $current = $box->style->get('color');
                if (!$current instanceof Color) {
                    return null;
                }
                $source = $current;
            } else {
                return null;
            }
        }
        // When every component is just its space's slot identifier
        // (e.g. `lab(from X l a b)`), the relative-color expression
        // is the identity round-trip — return the source unchanged
        // and let the painter's sRGB toSrgb path do the final
        // conversion.
        $candidates = $this->relativeColorSlotIdents($rc->space);
        foreach ($candidates as $slotIdents) {
            if ($this->isIdentMatching($rc->component1, $slotIdents[0])
                && $this->isIdentMatching($rc->component2, $slotIdents[1])
                && $this->isIdentMatching($rc->component3, $slotIdents[2])
                && $this->isAlphaSlotOrOne($rc->alpha)
            ) {
                return $source;
            }
        }
        // Slot permutations — every component is a bare slot
        // identifier from SOME valid slot trio, but the slots are
        // shuffled (e.g. `rgb(from currentColor g r b)` from WPT
        // relative-currentcolor-rgb-02). Map each component to the
        // source's value for that slot and rebuild the target color.
        foreach ($candidates as $slotIdents) {
            $permuted = $this->resolveSlotPermutation($rc, $source, $slotIdents);
            if ($permuted !== null) {
                return $permuted;
            }
        }
        // More general relative expressions (literal substitutions,
        // calc() over slot identifiers, alpha overrides) aren't
        // modelled yet — return null so the rule falls through to
        // its initial.
        return null;
    }

    /**
     * Resolve a relative-color expression whose components are bare
     * slot identifiers but possibly shuffled. Returns a new Color
     * with the source's slot values rearranged, or null when any
     * component isn't a recognised slot identifier from the given
     * trio.
     *
     * @param array{0:string,1:string,2:string} $slotIdents
     */
    private function resolveSlotPermutation(
        \Phpdftk\Css\Value\RelativeColor $rc,
        Color $source,
        array $slotIdents,
    ): ?Color {
        // Build slot-name → source-channel-value lookup. For each
        // syntactic slot trio we convert the sRGB-stored source into
        // the matching color model so the slot identifiers refer to
        // the right channels. The result is then converted back to
        // the source's storage space.
        if ($slotIdents === ['r', 'g', 'b']) {
            $sourceChannels = ['r' => $source->r, 'g' => $source->g, 'b' => $source->b];
            $rebuild = static fn(float $c1, float $c2, float $c3, float $a)
                => new Color($c1, $c2, $c3, $a, $source->space);
        } elseif ($slotIdents === ['h', 's', 'l']) {
            // CSS Color 4 §6 — sRGB→HSL. The slots refer to source's
            // HSL components; result rebuilds via hslToRgb. Hue is
            // stored as degrees per CSS, saturation and lightness as
            // 0–1 fractions.
            [$h, $s, $l] = self::srgbToHsl($source->r, $source->g, $source->b);
            $sourceChannels = ['h' => $h, 's' => $s, 'l' => $l];
            $rebuild = static function (float $c1, float $c2, float $c3, float $a) use ($source): Color {
                [$r, $g, $b] = self::hslToRgb($c1 / 360.0, $c2, $c3);
                return new Color($r, $g, $b, $a, $source->space);
            };
        } elseif ($slotIdents === ['h', 'w', 'b']) {
            [$h, $w, $blk] = self::srgbToHwb($source->r, $source->g, $source->b);
            $sourceChannels = ['h' => $h, 'w' => $w, 'b' => $blk];
            $rebuild = static function (float $c1, float $c2, float $c3, float $a) use ($source): Color {
                [$r, $g, $b] = self::hwbToRgb($c1, $c2, $c3);
                return new Color($r, $g, $b, $a, $source->space);
            };
        } else {
            return null;
        }
        if (!$this->isAlphaSlotOrOne($rc->alpha)) {
            return null;
        }
        $resolved = [];
        foreach ([$rc->component1, $rc->component2, $rc->component3] as $i => $comp) {
            // Component is EITHER a slot identifier (mapped above) OR
            // a bare literal number — WPT relative-currentcolor-hsl-02
            // uses `hsl(from currentColor 120 s l)` where the hue is
            // a literal `120` (degrees).
            if ($comp instanceof \Phpdftk\Css\Value\Keyword) {
                $name = strtolower($comp->name);
                if (!isset($sourceChannels[$name])) {
                    return null;
                }
                $resolved[$i] = $sourceChannels[$name];
                continue;
            }
            if ($comp instanceof \Phpdftk\Css\Value\Number) {
                $resolved[$i] = $comp->value;
                continue;
            }
            if ($comp instanceof \Phpdftk\Css\Value\Integer) {
                $resolved[$i] = (float) $comp->value;
                continue;
            }
            if ($comp instanceof \Phpdftk\Css\Value\Percentage) {
                // hsl saturation / lightness as a percentage maps to
                // [0, 1]; for hue or other components the spec keeps
                // the percentage verbatim but no test we hit takes
                // that path, so leave the simple 0..1 conversion.
                $resolved[$i] = $comp->value / 100.0;
                continue;
            }
            return null;
        }
        $alpha = $rc->alpha instanceof \Phpdftk\Css\Value\Number
            ? $rc->alpha->value
            : $source->a;
        return $rebuild($resolved[0], $resolved[1], $resolved[2], $alpha);
    }

    /**
     * CSS Color 4 §6 — sRGB → HSL. Returns hue in degrees, saturation
     * and lightness in [0, 1].
     *
     * @return array{0:float,1:float,2:float}
     */
    private static function srgbToHsl(float $r, float $g, float $b): array
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $l = ($max + $min) / 2.0;
        if ($delta === 0.0) {
            return [0.0, 0.0, $l];
        }
        $s = $l > 0.5 ? $delta / (2.0 - $max - $min) : $delta / ($max + $min);
        if ($max === $r) {
            $h = (($g - $b) / $delta) + ($g < $b ? 6.0 : 0.0);
        } elseif ($max === $g) {
            $h = (($b - $r) / $delta) + 2.0;
        } else {
            $h = (($r - $g) / $delta) + 4.0;
        }
        return [$h * 60.0, $s, $l];
    }

    /**
     * CSS Color 4 §8 — sRGB → HWB. Returns hue in degrees, whiteness
     * and blackness in [0, 1].
     *
     * @return array{0:float,1:float,2:float}
     */
    private static function srgbToHwb(float $r, float $g, float $b): array
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $h = self::srgbToHsl($r, $g, $b)[0];
        return [$h, $min, 1.0 - $max];
    }

    /**
     * CSS Color 4 §6 — HSL → sRGB, with hue in [0, 1) (fraction of a
     * full turn), saturation and lightness in [0, 1].
     *
     * @return array{0:float,1:float,2:float}
     */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            return [$l, $l, $l];
        }
        $q = $l < 0.5 ? $l * (1.0 + $s) : $l + $s - $l * $s;
        $p = 2.0 * $l - $q;
        $toRgb = static function (float $t) use ($p, $q): float {
            if ($t < 0.0) {
                $t += 1.0;
            }
            if ($t > 1.0) {
                $t -= 1.0;
            }
            if ($t < 1.0 / 6.0) {
                return $p + ($q - $p) * 6.0 * $t;
            }
            if ($t < 0.5) {
                return $q;
            }
            if ($t < 2.0 / 3.0) {
                return $p + ($q - $p) * (2.0 / 3.0 - $t) * 6.0;
            }
            return $p;
        };
        return [
            $toRgb($h + 1.0 / 3.0),
            $toRgb($h),
            $toRgb($h - 1.0 / 3.0),
        ];
    }

    /**
     * CSS Color 4 §8 — HWB → sRGB. Hue in degrees, whiteness and
     * blackness in [0, 1].
     *
     * @return array{0:float,1:float,2:float}
     */
    private static function hwbToRgb(float $h, float $w, float $b): array
    {
        if ($w + $b >= 1.0) {
            $gray = $w / ($w + $b);
            return [$gray, $gray, $gray];
        }
        [$rh, $gh, $bh] = self::hslToRgb($h / 360.0, 1.0, 0.5);
        return [
            $rh * (1.0 - $w - $b) + $w,
            $gh * (1.0 - $w - $b) + $w,
            $bh * (1.0 - $w - $b) + $w,
        ];
    }

    /**
     * Slot identifier name CANDIDATES for a relative-color
     * expression's target space. The CSS Color 5 parser maps the
     * function name to a storage space (`hsl(from …)` and `hwb(from
     * …)` both land at sRGB), so we accept any slot triple that's
     * syntactically valid for THE SYNTAX a sRGB-stored relative
     * color could have been written with.
     *
     * Returns an empty array when the space isn't a named family
     * we model.
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    private function relativeColorSlotIdents(\Phpdftk\Css\Value\ColorSpace $space): array
    {
        return match ($space) {
            // The sRGB-family spaces accept rgb/hsl/hwb syntax depending
            // on the relative-color function name; all three are valid
            // here because we collapsed them onto a single storage space.
            \Phpdftk\Css\Value\ColorSpace::sRGB,
            \Phpdftk\Css\Value\ColorSpace::sRGBLinear => [
                ['r', 'g', 'b'],
                ['h', 's', 'l'],
                ['h', 'w', 'b'],
            ],
            \Phpdftk\Css\Value\ColorSpace::DisplayP3,
            \Phpdftk\Css\Value\ColorSpace::DisplayP3Linear,
            \Phpdftk\Css\Value\ColorSpace::A98RGB,
            \Phpdftk\Css\Value\ColorSpace::A98RGBLinear,
            \Phpdftk\Css\Value\ColorSpace::ProPhotoRGB,
            \Phpdftk\Css\Value\ColorSpace::ProPhotoRGBLinear,
            \Phpdftk\Css\Value\ColorSpace::Rec2020,
            \Phpdftk\Css\Value\ColorSpace::Rec2020Linear
                => [['r', 'g', 'b']],
            \Phpdftk\Css\Value\ColorSpace::HWB => [['h', 'w', 'b']],
            \Phpdftk\Css\Value\ColorSpace::HSL => [['h', 's', 'l']],
            \Phpdftk\Css\Value\ColorSpace::Lab => [['l', 'a', 'b']],
            \Phpdftk\Css\Value\ColorSpace::Lch => [['l', 'c', 'h']],
            \Phpdftk\Css\Value\ColorSpace::OKLab => [['l', 'a', 'b']],
            \Phpdftk\Css\Value\ColorSpace::OKLCH => [['l', 'c', 'h']],
            \Phpdftk\Css\Value\ColorSpace::XYZ,
            \Phpdftk\Css\Value\ColorSpace::XYZD65,
            \Phpdftk\Css\Value\ColorSpace::XYZD50 => [['x', 'y', 'z']],
        };
    }

    private function isIdentMatching(\Phpdftk\Css\Value\Value $value, string $name): bool
    {
        return $value instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($value->name) === $name;
    }

    private function isAlphaSlotOrOne(\Phpdftk\Css\Value\Value $value): bool
    {
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return abs($value->value - 1.0) < 1e-9;
        }
        return $this->isIdentMatching($value, 'alpha');
    }

    private function findBodyChild(Box $root): ?Box
    {
        foreach ($root->children as $child) {
            if ($child->element !== null && strtolower($child->element->localName) === 'body') {
                // CSS Backgrounds 3 §3.11.2 — the special body→canvas
                // background propagation applies to the body's PRINCIPAL
                // box. A `display: contents` body generates no principal
                // box (only its children are hoisted), so it cannot
                // propagate; the hoisted text/child boxes still carry the
                // body element + its cascaded background, so match them
                // here would wrongly flood the canvas. Skip it.
                $disp = $child->style->get('display');
                if ($disp instanceof Keyword && strtolower($disp->name) === 'contents') {
                    continue;
                }
                return $child;
            }
        }
        return null;
    }

    /**
     * Return true when the box's `contain` property includes a value
     * that creates a paint-containment boundary — either `paint` or
     * `strict` or `content` (which include paint by definition), or
     * the multi-keyword shorthand listing one of those terms. CSS
     * Containment 3 §2.4 / §4.4. Per CSS Backgrounds 3 §3.11.2, when
     * the root or body is paint-contained the body→canvas background
     * propagation is suppressed.
     */
    private function boxIsPaintContained(Box $box): bool
    {
        $contain = $box->style->get('contain');
        if ($contain instanceof \Phpdftk\Css\Value\Keyword) {
            return $this->containKeywordImpliesPaint($contain->name);
        }
        if ($contain instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($contain->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Keyword
                    && $this->containKeywordImpliesPaint($v->name)
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function containKeywordImpliesPaint(string $keyword): bool
    {
        // CSS Containment 3 — `paint`, `layout`, `size`, AND `style`
        // each block ancestor propagation of properties like
        // `background` and `overflow` to the root (§4.1 for layout;
        // §3.4 for style explicitly lists the body→canvas
        // background propagation as one of the things style
        // containment blocks; size containment makes the element
        // fully independent of its contents which has the same
        // propagation-blocking effect for `<html>` / `<body>`
        // backgrounds — covered by the `contain-{html,body}-bg-003/4`
        // WPT fixtures). `strict` = layout+paint+style+size and
        // `content` = layout+paint+style both include several of
        // these so they get the same treatment.
        return $keyword === 'paint'
            || $keyword === 'layout'
            || $keyword === 'size'
            || $keyword === 'style'
            || $keyword === 'strict'
            || $keyword === 'content';
    }

    /**
     * CSS Contain §2.3 — does `contain` force PAINT containment, which
     * clips descendants to the box's overflow-clip-edge (the padding
     * box) on BOTH axes, overriding an explicit `overflow: visible`?
     * Only `paint`, `content` (= layout+paint+style), and `strict`
     * (= layout+paint+style+size) include paint containment.
     *
     * Deliberately NARROWER than {@see containKeywordImpliesPaint},
     * whose keyword test also returns true for `layout` / `size` /
     * `style` (for the background-propagation use case). Clipping a
     * `contain: layout` box would be a spec error, so this predicate
     * excludes those keywords.
     */
    private function containImpliesPaintClip(Box $box): bool
    {
        $contain = $box->style->get('contain');
        if ($contain instanceof \Phpdftk\Css\Value\Keyword) {
            return $this->containKeywordClips($contain->name);
        }
        if ($contain instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($contain->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Keyword
                    && $this->containKeywordClips($v->name)
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function containKeywordClips(string $keyword): bool
    {
        $k = strtolower($keyword);
        return $k === 'paint' || $k === 'strict' || $k === 'content';
    }

    /**
     * Paint order for a box's children, following the CSS 2.1 §9.9.1 /
     * Appendix E stacking order for the common flat case:
     *
     *   - Positioned children (`position` ≠ `static`) with a negative
     *     `z-index` paint first (most-negative first) — behind the
     *     box's non-positioned in-flow descendants.
     *   - Everything with `z-index: auto` / `0` (positioned or not) keeps
     *     document order.
     *   - Positioned children with a positive `z-index` paint last.
     *
     * Grid / flex items also take `z-index` even when not positioned
     * (CSS Grid 1 §4.4, Flexbox 1 §5.4), so their children are bucketed
     * by z-index regardless of `position`.
     *
     * The sort is stable (document-order tiebreak) and is skipped
     * entirely — returning the children array untouched — whenever no
     * child carries a non-zero effective z-index, so the overwhelming
     * majority of boxes emit byte-identical paint output. This does not
     * model nested stacking contexts (a positive-z grandchild of a
     * negative-z child), which needs a full context tree; the flat
     * reorder covers the ubiquitous "z-index: -1 backdrop" pattern.
     *
     * @return list<Box>
     */
    private function paintOrderChildren(Box $box): array
    {
        $children = $box->children;
        if (count($children) < 2) {
            return $children;
        }
        $display = $box->style->get('display');
        $isGridOrFlex = $display instanceof Keyword
            && (str_contains(strtolower($display->name), 'grid')
                || str_contains(strtolower($display->name), 'flex'));
        $indexed = [];
        $anyReorder = false;
        foreach ($children as $i => $child) {
            // `z-index` takes effect on positioned boxes and on grid/flex
            // items; for any other child it is ignored (layer 0) so the
            // child stays wherever document order put it.
            $z = ($isGridOrFlex || $this->isPositioned($child))
                ? $this->zIndexOf($child)
                : 0;
            // CSS Grid 1 §4.2 / Flexbox 1 §5.4 — grid / flex items paint in
            // "order-modified document order" within each z-index bucket:
            // the `order` property reorders paint (a higher `order` paints
            // later / on top), not just layout. It only applies to grid /
            // flex items; its initial value is 0, so a non-zero `order`
            // triggers the reorder just as a non-zero `z-index` does.
            $order = $isGridOrFlex ? $this->orderOf($child) : 0;
            // CSS 2.1 Appendix E — within one z-index bucket the paint
            // sub-order is: in-flow non-positioned block-level boxes
            // (step 3), then non-positioned floats (step 4), then in-flow
            // non-positioned inline-level boxes (step 5). Document order
            // alone paints a float behind any later in-flow block that
            // overlaps it (a left float under a following full-page block);
            // lifting floats to sub-layer 1 fixes that, while inline-level
            // siblings go to sub-layer 2 so they still paint over the float.
            // Positioned boxes keep their existing ordering (their z-index
            // bucket already governs them).
            $layer = 0;
            if (!$this->isPositioned($child)) {
                if ($this->isFloat($child)) {
                    $layer = 1;
                } elseif ($child instanceof \Phpdftk\HtmlToPdf\Box\InlineBox
                    || $child instanceof \Phpdftk\HtmlToPdf\Box\AtomicInlineBox
                    || $child instanceof \Phpdftk\HtmlToPdf\Box\TextBox
                    || $child instanceof \Phpdftk\HtmlToPdf\Box\LineBreakBox
                    // An anonymous wrapper around a run of inline content IS
                    // that inline content for painting purposes (Appendix E
                    // steps 5/7), so it belongs above floats. A float among
                    // inline siblings makes `mixesBlockAndInline` true and
                    // wraps the whole run, which then sat in sub-layer 0 and
                    // let the float paint over every line box in it.
                    //
                    // `element === null` is the discriminator: the OTHER
                    // AnonymousBlockBox site — the block-in-inline promotion
                    // in BoxGenerator — passes the real element, and that
                    // wrapper genuinely is block-level.
                    || ($child instanceof \Phpdftk\HtmlToPdf\Box\AnonymousBlockBox
                        && $child->element === null)
                ) {
                    $layer = 2;
                }
            } else {
                // Appendix E step 8 — a POSITIONED descendant with `z-index:
                // auto` or `0` paints above ALL in-flow content of the same
                // stacking context (steps 3-7), whatever document order says.
                // Leaving it in sub-layer 0 let a following in-flow sibling
                // cover it: the ubiquitous "absolutely positioned green
                // overlay declared before the red block" pattern rendered
                // entirely red. A non-zero z-index is already decisive on its
                // own, so this only settles the auto / 0 case.
                $layer = 3;
            }
            // Only a float (sub-layer 1) genuinely reorders against
            // document order; block (0) and inline (2) never coexist as
            // direct siblings (mixed content is anonymous-block-wrapped),
            // so those alone must not trip the reorder and disturb the
            // byte-identical fast path.
            if ($z !== 0 || $order !== 0 || $layer === 1 || $layer === 3) {
                $anyReorder = true;
            }
            $indexed[] = [$i, $z, $layer, $order, $child];
        }
        if (!$anyReorder) {
            return $children;
        }
        usort(
            $indexed,
            static fn(array $a, array $b): int => ($a[1] <=> $b[1])
                ?: ($a[2] <=> $b[2])
                ?: ($a[3] <=> $b[3])
                ?: ($a[0] <=> $b[0]),
        );
        return array_map(static fn(array $e): Box => $e[4], $indexed);
    }

    /**
     * Whether the box is CSS-positioned (`relative` / `absolute` /
     * `fixed` / `sticky`) — the precondition for `z-index` to take
     * effect outside grid / flex layout (CSS 2.1 §9.9.1).
     */
    private function isPositioned(Box $box): bool
    {
        $p = $box->style->get('position');
        if (!$p instanceof Keyword) {
            return false;
        }
        return match (strtolower($p->name)) {
            'relative', 'absolute', 'fixed', 'sticky' => true,
            default => false,
        };
    }

    /**
     * Whether the box is a CSS float (`float: left | right` and the
     * flow-relative aliases). Drives the Appendix E paint sub-layer so a
     * float paints over in-flow block siblings.
     */
    private function isFloat(Box $box): bool
    {
        $f = $box->style->get('float');
        if (!$f instanceof Keyword) {
            return false;
        }
        return match (strtolower($f->name)) {
            'left', 'right', 'inline-start', 'inline-end' => true,
            default => false,
        };
    }

    /** Integer `order` (grid / flex item), or 0 for missing / non-numeric. */
    private function orderOf(Box $box): int
    {
        $o = $box->style->get('order');
        if ($o instanceof \Phpdftk\Css\Value\Integer) {
            return $o->value;
        }
        if ($o instanceof \Phpdftk\Css\Value\Number) {
            return (int) $o->value;
        }
        return 0;
    }

    /** Integer `z-index`, or 0 for `auto` / missing / non-integer. */
    private function zIndexOf(Box $box): int
    {
        $z = $box->style->get('z-index');
        if ($z instanceof \Phpdftk\Css\Value\Integer) {
            return $z->value;
        }
        if ($z instanceof \Phpdftk\Css\Value\Number) {
            return (int) $z->value;
        }
        // CSS Values 4 §10.11 — `calc()` in an <integer> context rounds the
        // result to the nearest integer (halves toward +∞): `z-index: calc(3/2)`
        // is 2, not dropped to 0. Depending on the cascade path the calc arrives
        // either unevaluated (Calc) or already reduced to a numeric Length.
        if ($z instanceof \Phpdftk\Css\Value\Calc) {
            $v = \Phpdftk\Css\Cascade\CalcEvaluator::evaluate($z, new \Phpdftk\Css\Cascade\LengthContext());
            return (int) floor($v + 0.5);
        }
        if ($z instanceof \Phpdftk\Css\Value\Length) {
            return (int) floor($z->value + 0.5);
        }
        return 0;
    }

    private function paintBox(Box $box, ContentStream $stream, ?Box $parent = null): void
    {
        // Off-page skip: the box's layout-Y range doesn't overlap this
        // page's range. We still must descend into children for the
        // `<a href>` link-rect collection (which uses the page constant
        // to compute PDF-Y), but skip the heavy paint operations.
        if ($this->boxEntirelyOffPage($box) && !$this->hasMotionPath($box)) {
            return;
        }
        // CSS Masking 1 §4 — a `mask-image` masks the box (and its subtree)
        // by the mask's alpha/luminance. Install a soft mask over the whole
        // box paint (ISO 32000-2 §11.6.5.2). A `url(<image>)` mask builds an
        // Alpha/Luminosity soft mask from the resolved image; a
        // `<linear-gradient>` mask builds one from the gradient's alpha.
        $maskGsName = $this->buildBoxImageMaskGsName($box, $stream)
            ?? $this->buildBoxGradientMaskGsName($box, $stream);
        // CSS Masking 1 §4.1 — a `mask-image` layer that cannot be resolved
        // to a mask image is empty (transparent black), masking the element
        // AND its subtree fully out. When we could NOT build a soft mask for
        // a single `url(...)` layer (unresolvable source, or a mask box model
        // we don't model yet), fall back to that "definitively failed" blank.
        if ($maskGsName === null && $this->maskHidesElement($box)) {
            return;
        }
        if ($maskGsName !== null) {
            $stream->saveGraphicsState();
            $stream->setGraphicsState($maskGsName);
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
        $hasTransform = $this->applyBoxTransform($box, $stream, $parent);
        // CSS Transforms 2 §15 — `backface-visibility: hidden`
        // suppresses paint when the cumulative 3D rotation around
        // the X / Y axis flips the box past 90° (cos(θ) < 0). The
        // 2D-projected matrix already has the cos-flatten baked in,
        // so we approximate by checking whether ANY X/Y rotation in
        // the transform list exceeds 90°.
        $hidden = $this->isVisibilityHidden($box)
            || $this->isBackfaceHidden($box);
        // CSS 2.1 §11.1.2 `clip` — clip an abspos element (its own paint AND
        // descendants) to `rect(top, right, bottom, left)` of its border box.
        // Pushed before any drawing so the rect can crop the box itself, and
        // popped after the children loop.
        $clipRect = $this->resolveClipRect($box);
        if ($clipRect !== null) {
            $stream->saveGraphicsState();
            $clipPdfY = $this->pageHeight - $clipRect['y'] - $clipRect['h'];
            $stream->rectangle($clipRect['x'], $clipPdfY, $clipRect['w'], $clipRect['h']);
            $stream->clip();
            $stream->endPath();
        }
        // CSS Masking 1 §6 `clip-path: <basic-shape>` — clip to a shape
        // resolved against the border box, wrapping the box + descendants.
        $clipPathApplied = $this->applyClipPath($box, $stream);
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
            // Filter Effects 1 §16.1 — `filter: drop-shadow(...)`
            // paints an offset rect behind the box (below the
            // background, like an outset box-shadow). Other filter
            // primitives (`blur`, `grayscale`, etc.) require raster
            // pre-painting and are intentionally not honoured.
            $this->paintFilterDropShadow($box, $stream);
            // CSS Backgrounds 3 §6.1.1 — paint stack from bottom up:
            // outset shadows → background → inset shadows → border.
            $this->paintBoxShadow($box, $stream, insetOnly: false);
            $this->paintBackground($box, $stream);
            $this->paintBoxShadow($box, $stream, insetOnly: true);
            // CSS Tables 3 §4.3 / painting order — a `border-collapse: collapse`
            // cell's (collapsed) border belongs to the table and paints AFTER
            // the cell's content, so a descendant can't overpaint it. Defer it
            // to the late phase below; separated-border cells paint here.
            $deferBorder = $this->isCollapsedBorderCell($box);
            if (!$deferBorder) {
                $this->paintBorders($box, $stream);
            }
            if ($originalGeo !== null) {
                $box->geometry = $originalGeo;
            }
            $this->paintOutline($box, $stream);
            $this->paintColumnRules($box, $stream);
            $this->paintGridGapRules($box, $stream);
            $this->paintFlexGapRules($box, $stream);
            $this->paintImage($box, $stream, $parent);
            $this->paintListMarker($box, $stream);
            // CSS Overflow 3 §3 — a box's own inline content is clipped
            // by its own `overflow`, exactly as its descendants are
            // below. Without this the text escapes the box it lives in.
            // Backgrounds, borders and the outline stay OUTSIDE the
            // clip: a box's own border and outline are not clipped by
            // its overflow.
            if ($this->shouldOverflowClip($box)) {
                $stream->saveGraphicsState();
                $this->emitOverflowClipPath($stream, $box);
                $this->paintLineBoxes($box, $stream);
                $stream->restoreGraphicsState();
            } else {
                $this->paintLineBoxes($box, $stream);
            }
            $this->collectBlockLinkRect($box);
        }
        // CSS 2.1 §17.5.1 — column-group and column backgrounds sit
        // above the table's own background and below the row groups /
        // rows / cells that follow as children.
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
            $this->paintTableColumnBackgrounds($box, $stream);
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
        $mcFragment = $box->multiColumn;
        if ($mcFragment !== null && $mcFragment->fragmented) {
            // CSS Multi-column 1 §3.3 — `column-fill: auto`: the content was
            // laid out in one tall column; slice it into columns here.
            $this->paintFragmentedColumns($box, $stream, $mcFragment);
        } else {
            foreach ($this->paintOrderChildren($box) as $child) {
                $this->paintBox($child, $stream, $box);
            }
        }
        if ($overflowClip) {
            $stream->restoreGraphicsState();
        }
        // Late phase: a deferred `border-collapse: collapse` cell border paints
        // over the cell's content (CSS Tables 3 §4.3), after the overflow clip
        // is released so the border ring itself is not clipped.
        if ($deferBorder ?? false) {
            $this->paintBorders($box, $stream);
        }
        if ($clipPathApplied) {
            $stream->restoreGraphicsState();
        }
        if ($clipRect !== null) {
            $stream->restoreGraphicsState();
        }
        if ($hasTransform) {
            $stream->restoreGraphicsState();
        }
        if ($opacityGsName !== null) {
            $stream->restoreGraphicsState();
        }
        if ($maskGsName !== null) {
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
        // A NEGATIVE margin makes `outerHeight()` smaller than the box's
        // own height — and can make it negative outright, which inverted
        // this interval and reported the box as entirely above the page.
        // A `margin-top: -70px` box 16px tall has an outer height of -54,
        // so `bottom` landed above `top` and the box was culled from the
        // content stream altogether. Normalise the interval so the test
        // asks about the range the box actually occupies.
        $extent = $g->outerHeight();
        $top = min($g->y, $g->y + $extent);
        $bottom = max($g->y, $g->y + $extent);
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
    private function applyBoxTransform(Box $box, ContentStream $stream, ?Box $parent = null): bool
    {
        // CSS Transforms 2 §5 — the individual transform properties apply in
        // order (translate, rotate, scale) BEFORE the `transform` list, all
        // sharing one transform-origin. Build a combined function list.
        $functions = $this->individualTransformFunctions($box);
        // CSS Motion Path 1 §4 — `offset-path` contributes between the
        // individual properties and `transform`, per the CTM algorithm in
        // CSS Transforms 2 §CTM.
        $functions = array_merge($functions, $this->motionPathFunctions($box, $parent));
        $value = $box->style->get('transform');
        if ($value instanceof \Phpdftk\Css\Value\Transform) {
            $functions = array_merge($functions, $value->functions);
        }
        if ($functions === []) {
            return false;
        }
        $matrix = $this->composeTransformMatrix(new \Phpdftk\Css\Value\Transform($functions), $box);
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

    /** Whether this box is positioned along an `offset-path`. */
    private function hasMotionPath(Box $box): bool
    {
        return $this->motionRay($box) !== null
            || $this->motionShape($box) !== null
            || $this->motionCoordBox($box) !== null;
    }

    /**
     * The `<basic-shape>` in this box's `offset-path`, if any. As with
     * `ray()`, a trailing `<coord-box>` puts the cascade in a ValueList.
     */
    private function motionShape(Box $box): ?\Phpdftk\Css\Value\BasicShape
    {
        $value = $box->style->get('offset-path');
        if ($value instanceof \Phpdftk\Css\Value\BasicShape) {
            return $value;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $component) {
                if ($component instanceof \Phpdftk\Css\Value\BasicShape) {
                    return $component;
                }
            }
        }
        return null;
    }

    /**
     * The `ray()` in this box's `offset-path`, if any. `offset-path` may
     * carry a trailing coordinate box (`ray(0deg) padding-box`), in which
     * case the cascade holds a ValueList.
     */
    private function motionRay(Box $box): ?\Phpdftk\Css\Value\Ray
    {
        $value = $box->style->get('offset-path');
        if ($value instanceof \Phpdftk\Css\Value\Ray) {
            return $value;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $component) {
                if ($component instanceof \Phpdftk\Css\Value\Ray) {
                    return $component;
                }
            }
        }
        return null;
    }

    /**
     * CSS Motion Path 1 §2 — the transform functions that place a box on
     * its `offset-path`.
     *
     * The element's `offset-anchor` is moved onto the point at
     * `offset-distance` along the path, and rotated by `offset-rotate`.
     * `applyBoxTransform` wraps the returned list in
     * `T(origin) … T(-origin)`, so the composition
     * `T(P-O) · R(φ) · T(O-A)` reduces to `T(P) · R(φ) · T(-A)` — mapping
     * the anchor exactly onto the path point.
     *
     * @return list<\Phpdftk\Css\Value\TransformFunction>
     */
    private function motionPathFunctions(Box $box, ?Box $parent): array
    {
        $placement = $this->motionPlacement($box, $parent);
        if ($placement === null) {
            return [];
        }
        [$px, $py, $tangent] = $placement;
        $phi = $this->resolveMotionRotation($box, $tangent);

        [$ox, $oy] = $this->transformOriginCss($box);
        [$ax, $ay] = $this->motionAnchorCss($box, $ox, $oy);

        $functions = [$this->pixelTranslate($px - $ox, $py - $oy)];
        if ($phi !== 0.0) {
            $functions[] = new \Phpdftk\Css\Value\RotateTransform($phi);
        }
        if ($ox !== $ax || $oy !== $ay) {
            $functions[] = $this->pixelTranslate($ox - $ax, $oy - $ay);
        }
        return $functions;
    }


    /**
     * Where on its `offset-path` a box sits, in CSS page coordinates,
     * together with the path tangent in degrees.
     *
     * @return array{float, float, float}|null
     */
    private function motionPlacement(Box $box, ?Box $parent): ?array
    {
        [$cbX, $cbY, $cbW, $cbH] = $this->motionReferenceRect($box, $parent);

        $ray = $this->motionRay($box);
        if ($ray !== null) {
            $degrees = $this->rayAngleDegrees($ray);
            if ($degrees === null) {
                return null;
            }
            [$sx, $sy] = $this->rayOrigin($box, $ray, $cbX, $cbY, $cbW, $cbH);
            $length = $this->rayLength($box, $ray, $degrees, $sx, $sy, $cbX, $cbY, $cbW, $cbH);
            $distance = $this->resolveMotionDistance($box, $length);

            // CSS ray angles are compass BEARINGS: 0deg points UP and
            // positive angles turn clockwise. In CSS coordinates Y grows
            // downward, so the unit vector is (sin θ, -cos θ) — not the
            // (cos, sin) of an ordinary mathematical angle, which would be
            // 90° out. The tangent then trails the bearing by 90°.
            $theta = deg2rad($degrees);
            return [
                $sx + $distance * sin($theta),
                $sy - $distance * cos($theta),
                $degrees - 90.0,
            ];
        }

        $shape = $this->motionShape($box);
        if ($shape === null) {
            // A lone `<coord-box>` is a complete offset-path: the reference
            // rectangle itself. The reference box's own `border-radius`
            // should round it; square corners are the approximation here.
            $coordBox = $this->motionCoordBox($box);
            if ($coordBox === null) {
                return null;
            }
            return $this->pointAlongPolyline($box, $this->roundedRectPolyline(
                $cbX,
                $cbY,
                $cbW,
                $cbH,
                $this->referenceBoxRadii($parent ?? $box, $coordBox),
            ));
        }
        if ($shape instanceof \Phpdftk\Css\Value\PathShape) {
            // Unlike a <basic-shape>, `path()` is NOT resolved in the
            // reference box: its coordinates are the element's own, so the
            // path hangs off the box's border-box origin. `offset-path-
            // string-003` pins this down — a 200x300 box at (50, 80) with
            // `path('M 50 40')` has to land at (100, 120), which only the
            // element-relative origin produces.
            $g = $box->geometry;
            [$points, $closed] = $this->svgPathPolyline(
                $shape->pathData,
                $g->x - $g->paddingLeft - $g->borderLeft,
                $g->y - $g->paddingTop - $g->borderTop,
            );
            if ($points === []) {
                return null;
            }
            return $this->pointAlongPolyline($box, $points, $closed);
        }
        $points = $this->motionShapePolyline($box, $shape, $cbX, $cbY, $cbW, $cbH);
        if ($points === []) {
            return null;
        }
        return $this->pointAlongPolyline($box, $points);
    }

    /**
     * Walk a closed polyline to the point at `offset-distance`.
     *
     * Sampling every shape into a polyline gives one arc-length
     * parameterisation for all of them: an ellipse's quarter-perimeter has
     * no closed form, and a rounded rectangle mixes straight runs with
     * elliptical arcs, so a cumulative-length table is the honest way to
     * measure both.
     *
     * CSS Motion Path 1 §3.1 — a distance off either end of a CLOSED path
     * wraps around it, while an open path clamps to its endpoints.
     *
     * @param list<array{float, float}> $points
     * @return array{float, float, float}
     */
    private function pointAlongPolyline(Box $box, array $points, bool $closed = true): array
    {
        $count = count($points);
        $cumulative = [0.0];
        $total = 0.0;
        for ($i = 1; $i < $count; ++$i) {
            $total += hypot(
                $points[$i][0] - $points[$i - 1][0],
                $points[$i][1] - $points[$i - 1][1],
            );
            $cumulative[$i] = $total;
        }
        // A degenerate shape — `circle()` centred on an edge resolves
        // `closest-side` to zero — collapses to its single point.
        if ($total <= 0.0 || $count < 2) {
            return [$points[0][0], $points[0][1], 0.0];
        }

        $distance = $this->resolveMotionDistance($box, $total);
        if ($closed) {
            $distance = fmod($distance, $total);
            if ($distance < 0.0) {
                $distance += $total;
            }
        } else {
            $distance = max(0.0, min($total, $distance));
        }

        $index = 1;
        while ($index < $count - 1 && $cumulative[$index] < $distance) {
            ++$index;
        }
        [$x0, $y0] = $points[$index - 1];
        [$x1, $y1] = $points[$index];
        $span = $cumulative[$index] - $cumulative[$index - 1];
        $t = $span > 0.0 ? ($distance - $cumulative[$index - 1]) / $span : 0.0;

        return [
            $x0 + ($x1 - $x0) * $t,
            $y0 + ($y1 - $y0) * $t,
            rad2deg(atan2($y1 - $y0, $x1 - $x0)),
        ];
    }

    /**
     * Sample a `<basic-shape>` offset-path into a closed polyline, in CSS
     * page coordinates.
     *
     * Every shape starts where its conversion to a path starts and winds
     * clockwise: circles and ellipses from 3 o'clock, the rectangle family
     * from the top-left corner, and a polygon from its first vertex.
     *
     * @return list<array{float, float}>
     */
    private function motionShapePolyline(
        Box $box,
        \Phpdftk\Css\Value\BasicShape $shape,
        float $cbX,
        float $cbY,
        float $cbW,
        float $cbH,
    ): array {
        if ($shape instanceof \Phpdftk\Css\Value\CircleShape) {
            [$cx, $cy] = $this->shapeCentre(
                $box,
                $shape->centerX,
                $shape->centerY,
                $cbX,
                $cbY,
                $cbW,
                $cbH,
            );
            $radius = $this->shapeRadius(
                $shape->radius,
                $cx,
                $cy,
                $cbX,
                $cbY,
                $cbW,
                $cbH,
                null,
            );
            return $this->ellipsePolyline($cx, $cy, $radius, $radius);
        }
        if ($shape instanceof \Phpdftk\Css\Value\EllipseShape) {
            [$cx, $cy] = $this->shapeCentre(
                $box,
                $shape->centerX,
                $shape->centerY,
                $cbX,
                $cbY,
                $cbW,
                $cbH,
            );
            $rx = $this->shapeRadius($shape->radiusX, $cx, $cy, $cbX, $cbY, $cbW, $cbH, true);
            $ry = $this->shapeRadius($shape->radiusY, $cx, $cy, $cbX, $cbY, $cbW, $cbH, false);
            return $this->ellipsePolyline($cx, $cy, $rx, $ry);
        }
        if ($shape instanceof \Phpdftk\Css\Value\PolygonShape) {
            $points = [];
            foreach ($shape->vertices as $vertex) {
                $points[] = [
                    $cbX + $this->resolvePositionComponent($vertex[0], $cbW, true),
                    $cbY + $this->resolvePositionComponent($vertex[1], $cbH, false),
                ];
            }
            if (count($points) < 2) {
                return $points;
            }
            $points[] = $points[0];
            return $points;
        }

        $rect = $this->motionShapeRect($shape, $cbX, $cbY, $cbW, $cbH);
        if ($rect === null) {
            return [];
        }
        [$left, $top, $width, $height, $radii] = $rect;
        return $this->roundedRectPolyline($left, $top, $width, $height, $radii);
    }


    /**
     * The centre of a `circle()` / `ellipse()` offset-path.
     *
     * An omitted `at <position>` does NOT default to the reference box's
     * centre the way it does for `clip-path` — CSS Motion Path 1 §2 makes
     * `offset-position` the default, so `circle()` with
     * `offset-position: auto` is centred on the box's own corner.
     *
     * @return array{float, float}
     */
    private function shapeCentre(
        Box $box,
        ?\Phpdftk\Css\Value\Value $centerX,
        ?\Phpdftk\Css\Value\Value $centerY,
        float $cbX,
        float $cbY,
        float $cbW,
        float $cbH,
    ): array {
        if ($centerX !== null && $centerY !== null) {
            return [
                $cbX + $this->resolvePositionComponent($centerX, $cbW, true),
                $cbY + $this->resolvePositionComponent($centerY, $cbH, false),
            ];
        }
        return $this->offsetPositionPoint($box, $cbX, $cbY, $cbW, $cbH);
    }

    /**
     * `offset-position` in CSS page coordinates. `auto` is the box's own
     * border-box origin; `normal` — the initial value — is the centre of
     * the containing block.
     *
     * @return array{float, float}
     */
    private function offsetPositionPoint(
        Box $box,
        float $cbX,
        float $cbY,
        float $cbW,
        float $cbH,
    ): array {
        $position = $box->style->get('offset-position');
        if ($position instanceof Keyword && strtolower($position->name) === 'auto') {
            $g = $box->geometry;
            return [
                $g->x - $g->paddingLeft - $g->borderLeft,
                $g->y - $g->paddingTop - $g->borderTop,
            ];
        }
        if ($position instanceof \Phpdftk\Css\Value\ValueList
            && count($position->values) >= 2
        ) {
            return [
                $cbX + $this->resolvePositionComponent($position->values[0], $cbW, true),
                $cbY + $this->resolvePositionComponent($position->values[1], $cbH, false),
            ];
        }
        return [$cbX + $cbW / 2.0, $cbY + $cbH / 2.0];
    }

    /**
     * One radius of a `circle()` / `ellipse()`.
     *
     * `$horizontal` picks the axis for an ellipse; `null` marks a circle,
     * whose percentage radius resolves against the reference box's
     * diagonal per CSS Shapes 1 §5 rather than against one side.
     */
    private function shapeRadius(
        ?\Phpdftk\Css\Value\Value $radius,
        float $cx,
        float $cy,
        float $cbX,
        float $cbY,
        float $cbW,
        float $cbH,
        ?bool $horizontal,
    ): float {
        $left = abs($cx - $cbX);
        $right = abs($cbX + $cbW - $cx);
        $top = abs($cy - $cbY);
        $bottom = abs($cbY + $cbH - $cy);

        $keyword = null;
        if ($radius === null) {
            $keyword = 'closest-side';
        } elseif ($radius instanceof Keyword) {
            $keyword = strtolower($radius->name);
        }
        if ($keyword !== null) {
            $sides = $horizontal === null
                ? [$left, $right, $top, $bottom]
                : ($horizontal ? [$left, $right] : [$top, $bottom]);
            $corners = [
                hypot($left, $top),
                hypot($right, $top),
                hypot($left, $bottom),
                hypot($right, $bottom),
            ];
            return match ($keyword) {
                'farthest-side' => max($sides),
                'closest-corner' => min($corners),
                'farthest-corner' => max($corners),
                default => min($sides),
            };
        }
        if ($radius instanceof \Phpdftk\Css\Value\Percentage) {
            $basis = match ($horizontal) {
                true => $cbW,
                false => $cbH,
                // CSS Shapes 1 §5: sqrt(w² + h²) / sqrt(2).
                null => sqrt(($cbW * $cbW + $cbH * $cbH) / 2.0),
            };
            return abs($basis * $radius->value / 100.0);
        }
        return abs($this->resolvePositionComponent(
            $radius,
            $horizontal === false ? $cbH : $cbW,
            $horizontal !== false,
        ));
    }

    /**
     * Resolve `inset()` / `rect()` / `xywh()` to a rectangle plus its four
     * corner radii, in CSS page coordinates.
     *
     * @return array{float, float, float, float, list<array{float, float}>}|null
     */
    private function motionShapeRect(
        \Phpdftk\Css\Value\BasicShape $shape,
        float $cbX,
        float $cbY,
        float $cbW,
        float $cbH,
    ): ?array {
        $radiiSource = null;
        if ($shape instanceof \Phpdftk\Css\Value\InsetShape) {
            $edges = $this->expandBoxSides($shape->insets);
            $top = $this->resolvePositionComponent($edges[0], $cbH, false);
            $right = $this->resolvePositionComponent($edges[1], $cbW, true);
            $bottom = $this->resolvePositionComponent($edges[2], $cbH, false);
            $left = $this->resolvePositionComponent($edges[3], $cbW, true);
            $x0 = $cbX + $left;
            $y0 = $cbY + $top;
            $x1 = $cbX + $cbW - $right;
            $y1 = $cbY + $cbH - $bottom;
            $radiiSource = $shape->borderRadius;
        } elseif ($shape instanceof \Phpdftk\Css\Value\RectShape) {
            // `rect()` edges are offsets from the reference box's top-left,
            // not insets from each side, and `auto` means that box's own
            // edge.
            $edges = $this->expandBoxSides($shape->edges);
            $top = $this->motionRectEdge($edges[0], $cbH, false, 0.0);
            $right = $this->motionRectEdge($edges[1], $cbW, true, $cbW);
            $bottom = $this->motionRectEdge($edges[2], $cbH, false, $cbH);
            $left = $this->motionRectEdge($edges[3], $cbW, true, 0.0);
            $x0 = $cbX + $left;
            $y0 = $cbY + $top;
            $x1 = $cbX + $right;
            $y1 = $cbY + $bottom;
            $radiiSource = $shape->borderRadius;
        } elseif ($shape instanceof \Phpdftk\Css\Value\XywhShape) {
            $x0 = $cbX + $this->resolvePositionComponent($shape->x, $cbW, true);
            $y0 = $cbY + $this->resolvePositionComponent($shape->y, $cbH, false);
            $x1 = $x0 + $this->resolvePositionComponent($shape->width, $cbW, true);
            $y1 = $y0 + $this->resolvePositionComponent($shape->height, $cbH, false);
            $radiiSource = $shape->borderRadius;
        } else {
            return null;
        }

        $left = min($x0, $x1);
        $top = min($y0, $y1);
        $width = abs($x1 - $x0);
        $height = abs($y1 - $y0);
        $radii = $this->shapeCornerRadii($radiiSource, $width, $height);

        return [$left, $top, $width, $height, $radii];
    }

    /** One `rect()` edge, where `auto` falls back to the reference box. */
    private function motionRectEdge(
        \Phpdftk\Css\Value\Value $value,
        float $extent,
        bool $horizontal,
        float $auto,
    ): float {
        if ($value instanceof Keyword && strtolower($value->name) === 'auto') {
            return $auto;
        }
        return $this->resolvePositionComponent($value, $extent, $horizontal);
    }

    /**
     * Expand a 1-to-4 value box-side list to the full top/right/bottom/left
     * quartet.
     *
     * @param list<\Phpdftk\Css\Value\Value> $values
     * @return array{
     *     \Phpdftk\Css\Value\Value,
     *     \Phpdftk\Css\Value\Value,
     *     \Phpdftk\Css\Value\Value,
     *     \Phpdftk\Css\Value\Value
     * }
     */
    private function expandBoxSides(array $values): array
    {
        return match (count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            default => [$values[0], $values[1], $values[2], $values[3]],
        };
    }

    /**
     * The four `[rx, ry]` corner radii of a `round <border-radius>` clause,
     * clockwise from the top-left, scaled down together if adjacent radii
     * would overlap (CSS Backgrounds 3 §5.5).
     *
     * @param ?array<int, mixed> $radiusSource
     * @return list<array{float, float}>
     */
    private function shapeCornerRadii(?array $radiusSource, float $width, float $height): array
    {
        if ($radiusSource === null || $radiusSource === []) {
            return [[0.0, 0.0], [0.0, 0.0], [0.0, 0.0], [0.0, 0.0]];
        }
        $horizontal = [];
        $vertical = [];
        foreach ($radiusSource as $entry) {
            if (is_array($entry) && count($entry) >= 2) {
                $horizontal[] = $entry[0];
                $vertical[] = $entry[1];
                continue;
            }
            if ($entry instanceof \Phpdftk\Css\Value\Value) {
                $horizontal[] = $entry;
                $vertical[] = $entry;
            }
        }
        if ($horizontal === []) {
            return [[0.0, 0.0], [0.0, 0.0], [0.0, 0.0], [0.0, 0.0]];
        }
        $hx = $this->expandCornerList($horizontal);
        $vy = $this->expandCornerList($vertical);

        $radii = [];
        for ($i = 0; $i < 4; ++$i) {
            $radii[] = [
                abs($this->resolvePositionComponent($hx[$i], $width, true)),
                abs($this->resolvePositionComponent($vy[$i], $height, false)),
            ];
        }

        // Uniform down-scale when opposing radii overshoot an edge.
        $scale = 1.0;
        foreach ([
            [$radii[0][0] + $radii[1][0], $width],
            [$radii[3][0] + $radii[2][0], $width],
            [$radii[0][1] + $radii[3][1], $height],
            [$radii[1][1] + $radii[2][1], $height],
        ] as [$sum, $extent]) {
            if ($sum > 0.0 && $sum > $extent) {
                $scale = min($scale, $extent / $sum);
            }
        }
        if ($scale < 1.0) {
            foreach ($radii as $i => [$rx, $ry]) {
                $radii[$i] = [$rx * $scale, $ry * $scale];
            }
        }
        return $radii;
    }

    /**
     * Expand a 1-to-4 value corner list to all four corners.
     *
     * @param list<\Phpdftk\Css\Value\Value> $values
     * @return array{
     *     \Phpdftk\Css\Value\Value,
     *     \Phpdftk\Css\Value\Value,
     *     \Phpdftk\Css\Value\Value,
     *     \Phpdftk\Css\Value\Value
     * }
     */
    private function expandCornerList(array $values): array
    {
        return match (count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            default => [$values[0], $values[1], $values[2], $values[3]],
        };
    }

    /**
     * An ellipse sampled clockwise from 3 o'clock, closed.
     *
     * @return list<array{float, float}>
     */
    private function ellipsePolyline(float $cx, float $cy, float $rx, float $ry): array
    {
        if ($rx <= 0.0 && $ry <= 0.0) {
            return [[$cx, $cy]];
        }
        $steps = 720;
        $points = [];
        for ($i = 0; $i <= $steps; ++$i) {
            $angle = 2.0 * M_PI * $i / $steps;
            $points[] = [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
        }
        return $points;
    }

    /**
     * A rectangle sampled clockwise from the top-left, closed. Non-zero
     * corner radii become elliptical arcs, so the path starts where the
     * top edge does rather than at the (cut away) corner itself.
     *
     * @param list<array{float, float}> $radii
     * @return list<array{float, float}>
     */
    private function roundedRectPolyline(
        float $left,
        float $top,
        float $width,
        float $height,
        array $radii,
    ): array {
        $right = $left + $width;
        $bottom = $top + $height;
        if ($width <= 0.0 && $height <= 0.0) {
            return [[$left, $top]];
        }
        [$tl, $tr, $br, $bl] = $radii;
        $rounded = false;
        foreach ($radii as [$rx, $ry]) {
            if ($rx > 0.0 || $ry > 0.0) {
                $rounded = true;
                break;
            }
        }
        if (!$rounded) {
            return [
                [$left, $top],
                [$right, $top],
                [$right, $bottom],
                [$left, $bottom],
                [$left, $top],
            ];
        }

        $arcSteps = 24;
        $points = [[$left + $tl[0], $top]];
        // Each corner arc sweeps a quarter turn clockwise, from the end of
        // the incoming edge to the start of the outgoing one.
        foreach ([
            [$right - $tr[0], $top, $tr, $right - $tr[0], $top + $tr[1], -M_PI / 2.0],
            [$right, $bottom - $br[1], $br, $right - $br[0], $bottom - $br[1], 0.0],
            [$left + $bl[0], $bottom, $bl, $left + $bl[0], $bottom - $bl[1], M_PI / 2.0],
            [$left, $top + $tl[1], $tl, $left + $tl[0], $top + $tl[1], M_PI],
        ] as [$edgeX, $edgeY, $radius, $arcCx, $arcCy, $start]) {
            $points[] = [$edgeX, $edgeY];
            if ($radius[0] <= 0.0 && $radius[1] <= 0.0) {
                continue;
            }
            for ($i = 1; $i <= $arcSteps; ++$i) {
                $angle = $start + (M_PI / 2.0) * $i / $arcSteps;
                $points[] = [
                    $arcCx + $radius[0] * cos($angle),
                    $arcCy + $radius[1] * sin($angle),
                ];
            }
        }
        return $points;
    }


    /**
     * Flatten SVG path data into a polyline, in CSS page coordinates
     * anchored at the reference box's origin.
     *
     * Returns the points and whether the path closed, which decides
     * whether `offset-distance` wraps or clamps.
     *
     * A second subpath ends the walk: `offset-distance` measures one
     * continuous run, and stitching disjoint subpaths would invent
     * segments across the gaps that the author never drew.
     *
     * @return array{list<array{float, float}>, bool}
     */
    private function svgPathPolyline(string $pathData, float $originX, float $originY): array
    {
        $commands = \Phpdftk\Svg\Path\PathData::parse($pathData)->commands;

        $points = [];
        $x = 0.0;
        $y = 0.0;
        $startX = 0.0;
        $startY = 0.0;
        $closed = false;
        // Reflection state for the smooth curve commands.
        $lastCubic = null;
        $lastQuadratic = null;
        $started = false;

        $push = static function (float $px, float $py) use (&$points, $originX, $originY): void {
            $points[] = [$originX + $px, $originY + $py];
        };

        foreach ($commands as $command) {
            $absolute = $command->absolute;
            $baseX = $absolute ? 0.0 : $x;
            $baseY = $absolute ? 0.0 : $y;
            $cubic = null;
            $quadratic = null;

            if ($command instanceof \Phpdftk\Svg\Path\MoveTo) {
                if ($started) {
                    break;
                }
                $x = $baseX + $command->x;
                $y = $baseY + $command->y;
                $startX = $x;
                $startY = $y;
                $started = true;
                $push($x, $y);
            } elseif ($command instanceof \Phpdftk\Svg\Path\LineTo) {
                $x = $baseX + $command->x;
                $y = $baseY + $command->y;
                $push($x, $y);
            } elseif ($command instanceof \Phpdftk\Svg\Path\HorizontalLineTo) {
                $x = $baseX + $command->x;
                $push($x, $y);
            } elseif ($command instanceof \Phpdftk\Svg\Path\VerticalLineTo) {
                $y = $baseY + $command->y;
                $push($x, $y);
            } elseif ($command instanceof \Phpdftk\Svg\Path\CurveTo) {
                $c1x = $baseX + $command->x1;
                $c1y = $baseY + $command->y1;
                $c2x = $baseX + $command->x2;
                $c2y = $baseY + $command->y2;
                $ex = $baseX + $command->x;
                $ey = $baseY + $command->y;
                foreach ($this->cubicSamples($x, $y, $c1x, $c1y, $c2x, $c2y, $ex, $ey) as [$sx, $sy]) {
                    $push($sx, $sy);
                }
                $cubic = [$c2x, $c2y];
                $x = $ex;
                $y = $ey;
            } elseif ($command instanceof \Phpdftk\Svg\Path\SmoothCurveTo) {
                [$c1x, $c1y] = $lastCubic !== null
                    ? [2.0 * $x - $lastCubic[0], 2.0 * $y - $lastCubic[1]]
                    : [$x, $y];
                $c2x = $baseX + $command->x2;
                $c2y = $baseY + $command->y2;
                $ex = $baseX + $command->x;
                $ey = $baseY + $command->y;
                foreach ($this->cubicSamples($x, $y, $c1x, $c1y, $c2x, $c2y, $ex, $ey) as [$sx, $sy]) {
                    $push($sx, $sy);
                }
                $cubic = [$c2x, $c2y];
                $x = $ex;
                $y = $ey;
            } elseif ($command instanceof \Phpdftk\Svg\Path\QuadraticCurveTo) {
                $qx = $baseX + $command->x1;
                $qy = $baseY + $command->y1;
                $ex = $baseX + $command->x;
                $ey = $baseY + $command->y;
                foreach ($this->quadraticSamples($x, $y, $qx, $qy, $ex, $ey) as [$sx, $sy]) {
                    $push($sx, $sy);
                }
                $quadratic = [$qx, $qy];
                $x = $ex;
                $y = $ey;
            } elseif ($command instanceof \Phpdftk\Svg\Path\SmoothQuadraticCurveTo) {
                [$qx, $qy] = $lastQuadratic !== null
                    ? [2.0 * $x - $lastQuadratic[0], 2.0 * $y - $lastQuadratic[1]]
                    : [$x, $y];
                $ex = $baseX + $command->x;
                $ey = $baseY + $command->y;
                foreach ($this->quadraticSamples($x, $y, $qx, $qy, $ex, $ey) as [$sx, $sy]) {
                    $push($sx, $sy);
                }
                $quadratic = [$qx, $qy];
                $x = $ex;
                $y = $ey;
            } elseif ($command instanceof \Phpdftk\Svg\Path\ArcTo) {
                $ex = $baseX + $command->x;
                $ey = $baseY + $command->y;
                foreach ($this->arcSamples(
                    $x,
                    $y,
                    $command->rx,
                    $command->ry,
                    $command->xAxisRotation,
                    $command->largeArc,
                    $command->sweep,
                    $ex,
                    $ey,
                ) as [$sx, $sy]) {
                    $push($sx, $sy);
                }
                $x = $ex;
                $y = $ey;
            } elseif ($command instanceof \Phpdftk\Svg\Path\ClosePath) {
                $push($startX, $startY);
                $x = $startX;
                $y = $startY;
                $closed = true;
            }

            $lastCubic = $cubic;
            $lastQuadratic = $quadratic;
        }

        return [$points, $closed];
    }

    /**
     * Points along a cubic Bézier, excluding its start.
     *
     * @return list<array{float, float}>
     */
    private function cubicSamples(
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
    ): array {
        $steps = 32;
        $samples = [];
        for ($i = 1; $i <= $steps; ++$i) {
            $t = $i / $steps;
            $u = 1.0 - $t;
            $a = $u * $u * $u;
            $b = 3.0 * $u * $u * $t;
            $c = 3.0 * $u * $t * $t;
            $d = $t * $t * $t;
            $samples[] = [
                $a * $x0 + $b * $x1 + $c * $x2 + $d * $x3,
                $a * $y0 + $b * $y1 + $c * $y2 + $d * $y3,
            ];
        }
        return $samples;
    }

    /**
     * Points along a quadratic Bézier, excluding its start.
     *
     * @return list<array{float, float}>
     */
    private function quadraticSamples(
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
    ): array {
        $steps = 24;
        $samples = [];
        for ($i = 1; $i <= $steps; ++$i) {
            $t = $i / $steps;
            $u = 1.0 - $t;
            $samples[] = [
                $u * $u * $x0 + 2.0 * $u * $t * $x1 + $t * $t * $x2,
                $u * $u * $y0 + 2.0 * $u * $t * $y1 + $t * $t * $y2,
            ];
        }
        return $samples;
    }

    /**
     * Points along an SVG elliptical arc, excluding its start.
     *
     * Implements the endpoint-to-centre conversion of SVG 2 §B.2.4,
     * including the §B.2.5 correction that scales up radii too small to
     * span the two endpoints.
     *
     * @return list<array{float, float}>
     */
    private function arcSamples(
        float $x0,
        float $y0,
        float $rx,
        float $ry,
        float $rotationDegrees,
        bool $largeArc,
        bool $sweep,
        float $x1,
        float $y1,
    ): array {
        $rx = abs($rx);
        $ry = abs($ry);
        // Degenerate radii make the arc a straight line (SVG 2 §B.2.5).
        if ($rx == 0.0 || $ry == 0.0 || ($x0 == $x1 && $y0 == $y1)) {
            return [[$x1, $y1]];
        }

        $phi = deg2rad($rotationDegrees);
        $cosPhi = cos($phi);
        $sinPhi = sin($phi);

        $dx = ($x0 - $x1) / 2.0;
        $dy = ($y0 - $y1) / 2.0;
        $x1p = $cosPhi * $dx + $sinPhi * $dy;
        $y1p = -$sinPhi * $dx + $cosPhi * $dy;

        $lambda = ($x1p * $x1p) / ($rx * $rx) + ($y1p * $y1p) / ($ry * $ry);
        if ($lambda > 1.0) {
            $scale = sqrt($lambda);
            $rx *= $scale;
            $ry *= $scale;
        }

        $numerator = $rx * $rx * $ry * $ry
            - $rx * $rx * $y1p * $y1p
            - $ry * $ry * $x1p * $x1p;
        $denominator = $rx * $rx * $y1p * $y1p + $ry * $ry * $x1p * $x1p;
        $factor = $denominator == 0.0 ? 0.0 : sqrt(max(0.0, $numerator / $denominator));
        if ($largeArc === $sweep) {
            $factor = -$factor;
        }
        $cxp = $factor * $rx * $y1p / $ry;
        $cyp = -$factor * $ry * $x1p / $rx;

        $cx = $cosPhi * $cxp - $sinPhi * $cyp + ($x0 + $x1) / 2.0;
        $cy = $sinPhi * $cxp + $cosPhi * $cyp + ($y0 + $y1) / 2.0;

        $startAngle = atan2(($y1p - $cyp) / $ry, ($x1p - $cxp) / $rx);
        $endAngle = atan2((-$y1p - $cyp) / $ry, (-$x1p - $cxp) / $rx);
        $sweepAngle = $endAngle - $startAngle;
        if (!$sweep && $sweepAngle > 0.0) {
            $sweepAngle -= 2.0 * M_PI;
        } elseif ($sweep && $sweepAngle < 0.0) {
            $sweepAngle += 2.0 * M_PI;
        }

        $steps = max(8, (int) ceil(abs($sweepAngle) / (M_PI / 24.0)));
        $samples = [];
        for ($i = 1; $i <= $steps; ++$i) {
            $angle = $startAngle + $sweepAngle * $i / $steps;
            $ex = $rx * cos($angle);
            $ey = $ry * sin($angle);
            $samples[] = [
                $cx + $cosPhi * $ex - $sinPhi * $ey,
                $cy + $sinPhi * $ex + $cosPhi * $ey,
            ];
        }
        return $samples;
    }

    private function pixelTranslate(float $dx, float $dy): \Phpdftk\Css\Value\TranslateTransform
    {
        return new \Phpdftk\Css\Value\TranslateTransform(
            new \Phpdftk\Css\Value\Length($dx, \Phpdftk\Css\Value\LengthUnit::Px),
            new \Phpdftk\Css\Value\Length($dy, \Phpdftk\Css\Value\LengthUnit::Px),
        );
    }

    /**
     * A finite bearing in degrees. Enormous authored angles are wrapped
     * before they reach `sin()` / `cos()`, and non-finite values (which a
     * bad `calc()` can produce) disable the path rather than emitting a
     * NaN matrix that would corrupt the content stream.
     */
    private function rayAngleDegrees(\Phpdftk\Css\Value\Ray $ray): ?float
    {
        $degrees = $ray->angleDegrees();
        if ($degrees === null || !is_finite($degrees)) {
            return null;
        }
        return fmod($degrees, 360.0);
    }

    /**
     * The reference rectangle a ray is measured against — its containing
     * block, in CSS page coordinates.
     *
     * Approximated by the paint parent's content box. That is correct for
     * in-flow boxes; an absolutely-positioned box whose containing block is
     * a more distant positioned ancestor resolves against the wrong
     * rectangle, which matters only for percentage distances, `contain`,
     * and the size keywords.
     *
     * @return array{float, float, float, float}
     */
    private function motionReferenceRect(Box $box, ?Box $parent): array
    {
        $source = $parent ?? $box;
        $g = $source->geometry;

        // CSS Motion Path 1 §2 — an omitted `<coord-box>` is `border-box`.
        // The SVG boxes have no meaning for an HTML containing block, so
        // they fall back to it too.
        [$left, $top, $right, $bottom] = match ($this->motionCoordBox($box) ?? 'border-box') {
            'content-box' => [0.0, 0.0, 0.0, 0.0],
            'padding-box' => [
                $g->paddingLeft,
                $g->paddingTop,
                $g->paddingRight,
                $g->paddingBottom,
            ],
            'margin-box' => [
                $g->paddingLeft + $g->borderLeft + $g->marginLeft,
                $g->paddingTop + $g->borderTop + $g->marginTop,
                $g->paddingRight + $g->borderRight + $g->marginRight,
                $g->paddingBottom + $g->borderBottom + $g->marginBottom,
            ],
            default => [
                $g->paddingLeft + $g->borderLeft,
                $g->paddingTop + $g->borderTop,
                $g->paddingRight + $g->borderRight,
                $g->paddingBottom + $g->borderBottom,
            ],
        };

        return [
            $g->x - $left,
            $g->y - $top,
            $g->width + $left + $right,
            $g->height + $top + $bottom,
        ];
    }

    /**
     * The corner radii of the reference box, as a bare `<coord-box>`
     * offset-path inherits them.
     *
     * `border-radius` describes the BORDER box, so an inner coord box
     * shrinks each radius by the border and padding it sits inside
     * (CSS Backgrounds 3 §5.4), floored at zero.
     *
     * @return list<array{float, float}>
     */
    private function referenceBoxRadii(Box $source, string $coordBox): array
    {
        $g = $source->geometry;
        $inset = match ($coordBox) {
            'content-box' => [
                $g->borderLeft + $g->paddingLeft,
                $g->borderTop + $g->paddingTop,
                $g->borderRight + $g->paddingRight,
                $g->borderBottom + $g->paddingBottom,
            ],
            'padding-box' => [$g->borderLeft, $g->borderTop, $g->borderRight, $g->borderBottom],
            default => [0.0, 0.0, 0.0, 0.0],
        };
        [$left, $top, $right, $bottom] = $inset;

        // Percentage radii always resolve against the BORDER box, whichever
        // coord box the path itself uses.
        [$borderWidth, $borderHeight] = $this->borderBoxExtent($source);
        $radii = $this->borderRadiiXY($source, $borderWidth, $borderHeight);
        return [
            [max(0.0, $radii[0][0] - $left), max(0.0, $radii[0][1] - $top)],
            [max(0.0, $radii[1][0] - $right), max(0.0, $radii[1][1] - $top)],
            [max(0.0, $radii[2][0] - $right), max(0.0, $radii[2][1] - $bottom)],
            [max(0.0, $radii[3][0] - $left), max(0.0, $radii[3][1] - $bottom)],
        ];
    }

    /**
     * The `<coord-box>` named by this box's `offset-path`, if any.
     *
     * A `<coord-box>` may stand alone as the whole offset-path
     * (`offset-path: padding-box`), in which case it is both the reference
     * box and the path itself.
     */
    private function motionCoordBox(Box $box): ?string
    {
        $value = $box->style->get('offset-path');
        $components = $value instanceof \Phpdftk\Css\Value\ValueList
            ? $value->values
            : ($value !== null ? [$value] : []);
        foreach ($components as $component) {
            if (!($component instanceof Keyword)) {
                continue;
            }
            $name = strtolower($component->name);
            if (in_array($name, [
                'content-box',
                'padding-box',
                'border-box',
                'margin-box',
                'fill-box',
                'stroke-box',
                'view-box',
            ], true)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Where the ray starts, in CSS page coordinates.
     *
     * `ray(... at <position>)` wins. Otherwise `offset-position: auto`
     * starts at the box's own border-box top-left, an explicit
     * `offset-position` resolves in the containing block, and `normal`
     * (the initial value) falls back to the containing block's centre.
     *
     * @return array{float, float}
     */
    private function rayOrigin(
        Box $box,
        \Phpdftk\Css\Value\Ray $ray,
        float $cbX,
        float $cbY,
        float $cbW,
        float $cbH,
    ): array {
        if ($ray->atX !== null && $ray->atY !== null) {
            return [
                $cbX + $this->resolvePositionComponent($ray->atX, $cbW, true),
                $cbY + $this->resolvePositionComponent($ray->atY, $cbH, false),
            ];
        }
        $position = $box->style->get('offset-position');
        if ($position instanceof Keyword && strtolower($position->name) === 'auto') {
            $g = $box->geometry;
            return [
                $g->x - $g->paddingLeft - $g->borderLeft,
                $g->y - $g->paddingTop - $g->borderTop,
            ];
        }
        if ($position instanceof \Phpdftk\Css\Value\ValueList
            && count($position->values) >= 2
        ) {
            return [
                $cbX + $this->resolvePositionComponent($position->values[0], $cbW, true),
                $cbY + $this->resolvePositionComponent($position->values[1], $cbH, false),
            ];
        }
        return [$cbX + $cbW / 2.0, $cbY + $cbH / 2.0];
    }

    /**
     * CSS Motion Path 1 §2.2 — the ray's length.
     *
     * Only `sides` follows the angle, stopping where the ray leaves the
     * containing block. The other four ignore the angle entirely and
     * measure to a side or a corner.
     */
    private function rayLength(
        Box $box,
        \Phpdftk\Css\Value\Ray $ray,
        float $degrees,
        float $sx,
        float $sy,
        float $cbX,
        float $cbY,
        float $cbW,
        float $cbH,
    ): float {
        $left = $cbX;
        $right = $cbX + $cbW;
        $top = $cbY;
        $bottom = $cbY + $cbH;
        $sides = [abs($sx - $left), abs($right - $sx), abs($sy - $top), abs($bottom - $sy)];
        $corners = [];
        foreach ([$left, $right] as $cx) {
            foreach ([$top, $bottom] as $cy) {
                $corners[] = hypot($sx - $cx, $sy - $cy);
            }
        }

        $length = match ($ray->size) {
            \Phpdftk\Css\Value\RaySize::ClosestSide => min($sides),
            \Phpdftk\Css\Value\RaySize::FarthestSide => max($sides),
            \Phpdftk\Css\Value\RaySize::ClosestCorner => min($corners),
            \Phpdftk\Css\Value\RaySize::FarthestCorner => max($corners),
            \Phpdftk\Css\Value\RaySize::Sides => $this->raySidesLength(
                $degrees,
                $sx,
                $sy,
                $left,
                $top,
                $right,
                $bottom,
            ),
        };

        // CSS Motion Path 1 §2.2 — `contain` shortens the ray so the
        // element still fits inside its containing block. The reduction is
        // half the box's LARGER side and does not depend on the ray's
        // direction, so a wide box is held back as far when travelling
        // vertically as horizontally.
        if ($ray->contain) {
            [$width, $height] = $this->borderBoxExtent($box);
            $length = max(0.0, $length - max($width, $height) / 2.0);
        }
        return $length;
    }

    /**
     * The box's border-box width and height.
     *
     * @return array{float, float}
     */
    private function borderBoxExtent(Box $box): array
    {
        $g = $box->geometry;
        return [
            $g->width + $g->paddingLeft + $g->paddingRight + $g->borderLeft + $g->borderRight,
            $g->height + $g->paddingTop + $g->paddingBottom + $g->borderTop + $g->borderBottom,
        ];
    }

    /**
     * `sides`: the distance from the origin to where the ray crosses the
     * containing block's boundary. An origin outside the box gives zero —
     * the ray must not reach back in.
     */
    private function raySidesLength(
        float $degrees,
        float $sx,
        float $sy,
        float $left,
        float $top,
        float $right,
        float $bottom,
    ): float {
        if ($sx < $left || $sx > $right || $sy < $top || $sy > $bottom) {
            return 0.0;
        }
        $theta = deg2rad($degrees);
        $ux = sin($theta);
        $uy = -cos($theta);
        $best = null;
        // Distance to each of the four edge lines, keeping the nearest
        // one the ray actually travels toward.
        foreach ([[$ux, $left - $sx], [$ux, $right - $sx]] as [$component, $delta]) {
            if (abs($component) > 1e-9) {
                $t = $delta / $component;
                if ($t >= 0.0) {
                    $best = $best === null ? $t : min($best, $t);
                }
            }
        }
        foreach ([[$uy, $top - $sy], [$uy, $bottom - $sy]] as [$component, $delta]) {
            if (abs($component) > 1e-9) {
                $t = $delta / $component;
                if ($t >= 0.0) {
                    $best = $best === null ? $t : min($best, $t);
                }
            }
        }
        return $best ?? 0.0;
    }

    /**
     * `offset-distance`. A percentage is of the path's length; absolute
     * lengths are NOT clamped to the path (only a `contain` ray shortens).
     */
    private function resolveMotionDistance(Box $box, float $length): float
    {
        $value = $box->style->get('offset-distance');
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $length * $value->value / 100.0;
        }
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        // `calc(12.5% + 200px)` mixes both, so percentages resolve against
        // the path length rather than being dropped.
        if ($value instanceof \Phpdftk\Css\Value\Calc) {
            $resolved = \Phpdftk\Css\Cascade\CalcEvaluator::evaluate(
                $value,
                new \Phpdftk\Css\Cascade\LengthContext(percentageBasis: $length),
            );
            return is_finite($resolved) ? $resolved : 0.0;
        }
        return 0.0;
    }

    /**
     * `offset-rotate` — initial value `auto`, which follows the path
     * tangent. `reverse` faces the other way; a bare angle ignores the
     * tangent; `auto <angle>` / `reverse <angle>` add to it.
     */
    private function resolveMotionRotation(Box $box, float $tangent): float
    {
        $value = $box->style->get('offset-rotate');
        $components = $value instanceof \Phpdftk\Css\Value\ValueList
            ? $value->values
            : ($value !== null ? [$value] : []);

        $base = $tangent;
        $extra = 0.0;
        $sawKeyword = false;
        foreach ($components as $component) {
            if ($component instanceof Keyword) {
                $name = strtolower($component->name);
                if ($name === 'auto') {
                    $base = $tangent;
                    $sawKeyword = true;
                } elseif ($name === 'reverse') {
                    $base = $tangent + 180.0;
                    $sawKeyword = true;
                }
                continue;
            }
            if ($component instanceof \Phpdftk\Css\Value\Angle) {
                $extra = $component->toDegrees();
            }
        }
        // A bare `<angle>` replaces the tangent rather than adding to it.
        if (!$sawKeyword && $components !== []) {
            $hasAngle = false;
            foreach ($components as $component) {
                if ($component instanceof \Phpdftk\Css\Value\Angle) {
                    $hasAngle = true;
                    break;
                }
            }
            if ($hasAngle) {
                return $extra;
            }
        }
        return $base + $extra;
    }

    /**
     * `transform-origin` in CSS page coordinates (Y down), as the motion
     * math needs it. {@see resolveTransformOrigin} returns the same point
     * already flipped into PDF space.
     *
     * @return array{float, float}
     */
    private function transformOriginCss(Box $box): array
    {
        $g = $box->geometry;
        $width = $g->width + $g->paddingLeft + $g->paddingRight + $g->borderLeft + $g->borderRight;
        $height = $g->height + $g->paddingTop + $g->paddingBottom + $g->borderTop + $g->borderBottom;
        $boxX = $g->x - $g->paddingLeft - $g->borderLeft;
        $boxY = $g->y - $g->paddingTop - $g->borderTop;
        $value = $box->style->get('transform-origin');
        $values = $value instanceof \Phpdftk\Css\Value\ValueList
            ? $value->values
            : ($value !== null ? [$value] : []);
        [$offX, $offY] = $this->resolveTransformOriginOffsets($values, $width, $height);
        return [$boxX + $offX, $boxY + $offY];
    }

    /**
     * `offset-anchor` in CSS page coordinates. `auto` — the initial value
     * — means the computed `transform-origin`, NOT `offset-position`.
     *
     * @return array{float, float}
     */
    private function motionAnchorCss(Box $box, float $originX, float $originY): array
    {
        $value = $box->style->get('offset-anchor');
        if (!($value instanceof \Phpdftk\Css\Value\ValueList) || count($value->values) < 2) {
            return [$originX, $originY];
        }
        $g = $box->geometry;
        $width = $g->width + $g->paddingLeft + $g->paddingRight + $g->borderLeft + $g->borderRight;
        $height = $g->height + $g->paddingTop + $g->paddingBottom + $g->borderTop + $g->borderBottom;
        $boxX = $g->x - $g->paddingLeft - $g->borderLeft;
        $boxY = $g->y - $g->paddingTop - $g->borderTop;
        return [
            $boxX + $this->resolvePositionComponent($value->values[0], $width, true),
            $boxY + $this->resolvePositionComponent($value->values[1], $height, false),
        ];
    }

    /**
     * One `<position>` component against an extent. `$horizontal` selects
     * which edge keywords apply.
     */
    private function resolvePositionComponent(
        \Phpdftk\Css\Value\Value $value,
        float $extent,
        bool $horizontal,
    ): float {
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $extent * $value->value / 100.0;
        }
        if ($value instanceof Keyword) {
            return match (strtolower($value->name)) {
                'left', 'top' => 0.0,
                'right', 'bottom' => $extent,
                default => $extent / 2.0,
            };
        }
        // The three/four-value `<position>` form folds an offset from a far
        // edge into `calc(100% - <length>)`.
        if ($value instanceof \Phpdftk\Css\Value\Calc) {
            $resolved = \Phpdftk\Css\Cascade\CalcEvaluator::evaluate(
                $value,
                new \Phpdftk\Css\Cascade\LengthContext(percentageBasis: $extent),
            );
            return is_finite($resolved) ? $resolved : 0.0;
        }
        return 0.0;
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
     * CSS Transforms 2 §5 — the transform functions contributed by the
     * individual `translate` / `rotate` / `scale` properties, in that
     * order (they compose before the `transform` list). Each reads its
     * computed value directly; `none` / absent / malformed → skipped.
     *
     * @return list<\Phpdftk\Css\Value\TransformFunction>
     */
    private function individualTransformFunctions(Box $box): array
    {
        $out = [];
        $t = $this->individualTranslate($box->style->get('translate'));
        if ($t !== null) {
            $out[] = $t;
        }
        $r = $this->individualRotate($box->style->get('rotate'));
        if ($r !== null) {
            $out[] = $r;
        }
        $s = $this->individualScale($box->style->get('scale'));
        if ($s !== null) {
            $out[] = $s;
        }
        return $out;
    }

    /**
     * Unwrap a transform property's computed value into its component
     * list. `none` / null → null (skip); a space-separated ValueList →
     * its values; a single value → a one-element list.
     *
     * @return list<\Phpdftk\Css\Value\Value>|null
     */
    private function transformPropertyComponents(?\Phpdftk\Css\Value\Value $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Keyword && strtolower($value->name) === 'none') {
            return null;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            return $value->values;
        }
        return [$value];
    }

    private function individualTranslate(?\Phpdftk\Css\Value\Value $value): ?\Phpdftk\Css\Value\TranslateTransform
    {
        $c = $this->transformPropertyComponents($value);
        if ($c === null || $c === []) {
            return null;
        }
        $x = $c[0];
        if (!($x instanceof \Phpdftk\Css\Value\Length || $x instanceof \Phpdftk\Css\Value\Percentage)) {
            return null;
        }
        $y = $c[1] ?? new \Phpdftk\Css\Value\Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px);
        if (!($y instanceof \Phpdftk\Css\Value\Length || $y instanceof \Phpdftk\Css\Value\Percentage)) {
            return null;
        }
        $z = ($c[2] ?? null) instanceof \Phpdftk\Css\Value\Length ? $c[2] : null;
        return new \Phpdftk\Css\Value\TranslateTransform($x, $y, $z);
    }

    private function individualScale(?\Phpdftk\Css\Value\Value $value): ?\Phpdftk\Css\Value\ScaleTransform
    {
        $c = $this->transformPropertyComponents($value);
        if ($c === null || $c === []) {
            return null;
        }
        $sx = $this->transformNumber($c[0]);
        if ($sx === null) {
            return null;
        }
        $sy = isset($c[1]) ? $this->transformNumber($c[1]) : $sx;
        if ($sy === null) {
            return null;
        }
        $sz = isset($c[2]) ? $this->transformNumber($c[2]) : null;
        return new \Phpdftk\Css\Value\ScaleTransform($sx, $sy, $sz);
    }

    private function individualRotate(?\Phpdftk\Css\Value\Value $value): ?\Phpdftk\Css\Value\RotateTransform
    {
        $c = $this->transformPropertyComponents($value);
        if ($c === null || $c === []) {
            return null;
        }
        $ax = 0.0;
        $ay = 0.0;
        $az = 1.0;
        $angle = null;
        if (count($c) === 1) {
            // `rotate: <angle>` → 2D rotation about Z.
            $angle = $c[0];
        } elseif (count($c) === 2 && $c[0] instanceof Keyword) {
            // `rotate: [x|y|z] <angle>`.
            switch (strtolower($c[0]->name)) {
                case 'x': $ax = 1.0;
                    $ay = 0.0;
                    $az = 0.0;
                    break;
                case 'y': $ax = 0.0;
                    $ay = 1.0;
                    $az = 0.0;
                    break;
                case 'z': $ax = 0.0;
                    $ay = 0.0;
                    $az = 1.0;
                    break;
                default: return null;
            }
            $angle = $c[1];
        } elseif (count($c) === 4) {
            // `rotate: <number>{3} <angle>` (axis vector + angle).
            $ax = $this->transformNumber($c[0]);
            $ay = $this->transformNumber($c[1]);
            $az = $this->transformNumber($c[2]);
            if ($ax === null || $ay === null || $az === null) {
                return null;
            }
            $angle = $c[3];
        } else {
            return null;
        }
        if (!$angle instanceof \Phpdftk\Css\Value\Angle) {
            return null;
        }
        return new \Phpdftk\Css\Value\RotateTransform($angle->toDegrees(), $ax, $ay, $az);
    }

    private function transformNumber(\Phpdftk\Css\Value\Value $value): ?float
    {
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer) {
            return (float) $value->value;
        }
        return null;
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
            // CSS Transforms 2 §13 — rotateX / rotateY collapse onto
            // a 2D plane in print. Approximation: rotateX(θ) scales
            // the box vertically by |cos(θ)| (mirroring past 90°),
            // rotateY(θ) scales horizontally. Visually correct at
            // canonical angles (0° = unchanged, 90° = edge-on,
            // 180° = mirrored). Z rotation keeps the 2D rotation
            // matrix.
            $rad = deg2rad($fn->angleDeg);
            $cos = cos($rad);
            $sin = sin($rad);
            $axisLen = sqrt($fn->ax * $fn->ax + $fn->ay * $fn->ay + $fn->az * $fn->az);
            if ($axisLen <= 0.0) {
                return null;
            }
            $nx = $fn->ax / $axisLen;
            $ny = $fn->ay / $axisLen;
            $nz = $fn->az / $axisLen;
            // Decompose rotate3d into axis projections. For an axis
            // that's purely Z (nx=ny=0, nz=±1), keep the planar
            // rotation; for X/Y components, flatten via cos-scaling.
            if (abs($nz) > 0.9999) {
                // Z rotation (or close to it).
                $sign = $nz > 0 ? 1.0 : -1.0;
                return [$cos, -$sign * $sin, $sign * $sin, $cos, 0.0, 0.0];
            }
            // X / Y rotation (or a tilt): use cos-flatten. For mixed
            // axes, weight by the axis components. Pure rotateX
            // (nx=1) → sy = cos, sx = 1. Pure rotateY (ny=1) → sx
            // = cos, sy = 1.
            $sy = 1.0 - abs($nx) + abs($nx) * $cos;
            $sx = 1.0 - abs($ny) + abs($ny) * $cos;
            return [$sx, 0.0, 0.0, $sy, 0.0, 0.0];
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
        $values = $value instanceof \Phpdftk\Css\Value\ValueList
            ? $value->values
            : ($value !== null ? [$value] : []);
        [$offX, $offY] = $this->resolveTransformOriginOffsets($values, $width, $height);
        $cssY = $boxY + $offY;
        return [$boxX + $offX, $this->pageHeight - $cssY];
    }

    /**
     * Resolve `transform-origin`'s 1-3 components to (x, y) offsets. Position
     * keywords bind by AXIS IDENTITY, not order (CSS Transforms 1 §6 / the
     * <position> grammar): `left`/`right` set X, `top`/`bottom` set Y in any
     * order; `center`, `<length>` and `<percentage>` fill the remaining axes
     * positionally (X first). A single keyword leaves the other axis at center.
     *
     * @param list<\Phpdftk\Css\Value\Value> $values
     * @return array{float, float}
     */
    private function resolveTransformOriginOffsets(array $values, float $width, float $height): array
    {
        $offX = null;
        $offY = null;
        $positional = [];
        foreach ($values as $v) {
            if ($v instanceof \Phpdftk\Css\Value\Keyword) {
                switch (strtolower($v->name)) {
                    case 'left':   $offX = 0.0;
                        continue 2;
                    case 'right':  $offX = $width;
                        continue 2;
                    case 'top':    $offY = 0.0;
                        continue 2;
                    case 'bottom': $offY = $height;
                        continue 2;
                        // 'center' falls through to positional assignment.
                }
            }
            $positional[] = $v;
        }
        foreach ($positional as $v) {
            if ($offX === null) {
                $offX = $this->resolveOriginComponent($v, $width, $width / 2.0);
            } elseif ($offY === null) {
                $offY = $this->resolveOriginComponent($v, $height, $height / 2.0);
            }
        }
        return [$offX ?? $width / 2.0, $offY ?? $height / 2.0];
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
        // CSS Values 4 §5.2 — a unitless `0` is equivalent to `0px` in
        // any length context. The generic stylesheet parser stores it as
        // `Integer` / `Number`, which the cascade keeps unchanged for
        // properties (like `transform-origin`) that don't have a
        // dedicated typed parser. Treat both shapes as a px length so
        // `transform-origin: 0 0` doesn't silently fall back to the 50%
        // default.
        if ($value instanceof \Phpdftk\Css\Value\Integer
            || $value instanceof \Phpdftk\Css\Value\Number
        ) {
            return (float) $value->value;
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
    private function paintImage(Box $box, ContentStream $stream, ?Box $parent = null): void
    {
        // Replaced elements render whether they are atomic-inline
        // (`display: inline-block`) OR blockified into a BlockBox. An
        // `<img>` / `<embed>` / `<object>` that is FLOATED, `display:
        // block`, OR out-of-flow (`position: absolute` / `fixed`, which
        // CSS Display §2.7 blockifies) becomes a BlockBox, but its
        // geometry is still laid out by the normal block-flow path —
        // including the CSS Sizing §4.2 aspect-ratio transfer that sizes a
        // `width: 50%; height: auto` abspos image — so it paints at the
        // right rect. Accept every such BlockBox replaced element.
        //
        // Two geometry paths are still wrong, so gate them out:
        //  - a GRID ITEM replaced BlockBox reaches paint with grid-track
        //    geometry not wired for direct raster placement (painting it
        //    regressed css-grid −6); detect via the parent box type. A FLEX
        //    ITEM, by contrast, is blockified (CSS Display §2.7) and its
        //    main/cross size + §4.5 auto-minimum are resolved by the normal
        //    block-flow measure, so its geometry IS correct — paint it;
        //  - a VERTICAL-writing-mode replaced BlockBox (float/block WM
        //    positioning is still wrong — see css-writing-modes follow-up).
        $parentIsGrid = $parent instanceof \Phpdftk\HtmlToPdf\Box\GridBox;
        $isBlockReplaced = $box instanceof \Phpdftk\HtmlToPdf\Box\BlockBox
            && !$parentIsGrid
            && !WritingMode::fromStyle($box->style)->isVertical();
        if (!($box instanceof \Phpdftk\HtmlToPdf\Box\AtomicInlineBox)
            && !$isBlockReplaced
        ) {
            return;
        }
        if ($this->writer === null || $this->page === null) {
            return;
        }
        $element = $box->element;
        if ($element === null) {
            return;
        }
        // Inline foreign content (`<svg>` / `<math>`): the parser
        // tagged the subtree with its namespace, but our
        // AtomicInlineBox path historically only knew about `<img>`.
        // Detect each foreign namespace and route to the dedicated
        // painter before the img-src lookup.
        $foreignKind = \Phpdftk\HtmlToPdf\Box\BoxGenerator::foreignContentKind($element);
        if ($foreignKind === 'svg') {
            $this->paintInlineSvg($element, $box, $stream);
            return;
        }
        if ($foreignKind === 'math') {
            $this->paintInlineMath($element, $box, $stream);
            return;
        }
        // `<img src>`, `<embed src>`, `<object data>` and a `<video>`'s
        // `poster` frame all render an external image resource through the
        // same SVG / raster paint path (object-fit / object-position aware).
        $tag = strtolower($element->localName);
        if ($tag !== 'img' && $tag !== 'embed' && $tag !== 'object' && $tag !== 'video') {
            return;
        }
        $src = $element->getAttribute(match ($tag) {
            'object' => 'data',
            'video' => 'poster',
            default => 'src',
        });
        if ($src === null) {
            return;
        }
        // `<img src="*.svg">` and `<img src="data:image/svg+xml,...">`
        // route through the SVG painter rather than the raster image
        // XObject path — SVG isn't a pixel container, so PdfWriter's
        // addImage rejects it. The painter has the loader / sizer /
        // renderer already used by background-image:url(svg); reuse
        // it here so an inline replaced `<img>` honours its CSS
        // geometry plus `object-fit` / `object-position`.
        if ($this->isSvgSrc($src)) {
            $this->paintImgSvg($element, $box, $stream, $src);
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
     *   - `http(s)://...` → fetched via `phpdftk/resource-loader`
     *     (4F.5) when a loader was supplied; spilled to a tempfile.
     *     Without a loader, http(s) silently drops the image.
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
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $this->fetchHttpSrc($src);
        }
        return (new \Phpdftk\Filesystem\ResourceLoader($this->baseDir, $this->sandboxRoot))
            ->resolveLocalPath($src);
    }

    /**
     * 4F.5 — fetch an `http(s)://` `<img src>` through the injected
     * ResourceLoader and materialise the bytes to a temp file so
     * the existing `ImageParser` + `PdfWriter::addImage` flow can
     * register them as a PDF XObject. Returns the temp path on
     * success or null on any failure (no loader configured, SSRF
     * policy violation, network error, non-2xx, body cap exceeded,
     * write failure) — all of which surface as the no-image
     * outcome.
     */
    private function fetchHttpSrc(string $src): ?string
    {
        if ($this->resourceLoader === null) {
            return null;
        }
        try {
            $result = $this->resourceLoader->fetch($src);
        } catch (SsrfBlockedException | FetchFailedException) {
            return null;
        }
        $tmpPath = tempnam(sys_get_temp_dir(), 'phpdftk-http-img-');
        if ($tmpPath === false) {
            return null;
        }
        try {
            \Phpdftk\Filesystem\LocalFilesystem::writeFile($tmpPath, $result->bytes);
        } catch (\Throwable) {
            @unlink($tmpPath);
            return null;
        }
        $this->tempImagePaths[] = $tmpPath;
        return $tmpPath;
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
        // CSS Backgrounds 4 §3.5 — `border-area` paints only inside
        // the border ring (border-box ∖ padding-box). The bg-clip
        // resolution still hands back the OUTER bounding rect
        // (border-box dimensions) so the caller computes positions
        // against that, but the actual paint is intersected with
        // the ring path; the special-case emission lives in
        // `paintBackground`.
        if (in_array($name, ['border-box', 'padding-box', 'content-box', 'border-area'], true)) {
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
        // CSS Overflow 3 §3.3 — when the body's overflow has propagated
        // to the root, the body itself paints as if overflow:visible.
        if ($box === $this->propagatedOverflowBox) {
            return false;
        }
        return $this->axisClips($box, 'x') || $this->axisClips($box, 'y');
    }

    /**
     * Return true when the given axis (`'x'` or `'y'`) should clip
     * for this box. Checks the axis-specific longhand first, then
     * the `overflow` shorthand.
     */
    private function axisClips(Box $box, string $axis): bool
    {
        // CSS Overflow 3 §3.3 — when overflow propagated from body to
        // root, the root's effective overflow is the body's. Per §2.1 a
        // viewport can't mix a clipped axis with a `visible` one: if the
        // body constrained EITHER axis, the other (`visible`) axis
        // computes to `auto` on the viewport and therefore also clips.
        // (WPT overflow-body-propagation-007 pairs body `overflow-x: clip`
        // against a ref `html { overflow: hidden auto }` — both axes clip.)
        if ($box === $this->propagatedOverflowRoot && $this->propagatedOverflowBox !== null) {
            return $this->originalAxisClips($this->propagatedOverflowBox, 'x')
                || $this->originalAxisClips($this->propagatedOverflowBox, 'y');
        }
        return $this->originalAxisClips($box, $axis);
    }

    /** True when the box's `overflow` (or per-axis) uses the `clip` keyword. */
    private function hasOverflowClip(Box $box): bool
    {
        foreach (['overflow', 'overflow-x', 'overflow-y'] as $prop) {
            $v = $box->style->get($prop);
            if ($v instanceof Keyword && strtolower($v->name) === 'clip') {
                return true;
            }
        }
        return false;
    }

    /**
     * CSS Overflow 3 §4.2 — resolve `overflow-clip-margin` to a per-edge
     * OUTWARD expansion (left, top, right, bottom) from the padding box.
     * The value is `[ <visual-box> || <length [0,∞]> ]`: the reference box
     * (content-box / padding-box[default] / border-box) sets the base
     * edge, the length pushes further out. Only applies to `overflow:
     * clip`; returns zeros otherwise.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function overflowClipMargin(Box $box): array
    {
        // CSS Overflow 3 §4.2 — `overflow-clip-margin` applies to a box
        // that clips via `overflow: clip` OR paint containment
        // (`contain: paint`), the latter clipping even when `overflow`
        // stays `visible` (WPT paint-containment-svg).
        if (!$this->hasOverflowClip($box) && !$this->containImpliesPaintClip($box)) {
            return [0.0, 0.0, 0.0, 0.0];
        }
        $value = $box->style->get('overflow-clip-margin');
        $items = $value instanceof \Phpdftk\Css\Value\ValueList
            ? $value->values
            : ($value !== null ? [$value] : []);
        $refBox = 'padding-box';
        $margin = 0.0;
        foreach ($items as $item) {
            if ($item instanceof Keyword) {
                $n = strtolower($item->name);
                if (in_array($n, ['content-box', 'padding-box', 'border-box'], true)) {
                    $refBox = $n;
                }
            } elseif ($item instanceof \Phpdftk\Css\Value\Length) {
                $margin = max(0.0, $item->value);
            }
        }
        $g = $box->geometry;
        $baseL = $baseT = $baseR = $baseB = 0.0;
        if ($refBox === 'border-box') {
            $baseL = $g->borderLeft;
            $baseT = $g->borderTop;
            $baseR = $g->borderRight;
            $baseB = $g->borderBottom;
        } elseif ($refBox === 'content-box') {
            $baseL = -$g->paddingLeft;
            $baseT = -$g->paddingTop;
            $baseR = -$g->paddingRight;
            $baseB = -$g->paddingBottom;
        }
        return [$baseL + $margin, $baseT + $margin, $baseR + $margin, $baseB + $margin];
    }

    private function originalAxisClips(Box $box, string $axis): bool
    {
        // CSS Contain §2.3 — paint containment (`contain: paint |
        // content | strict`) clips descendants to the padding box on
        // BOTH axes, independent of and overriding `overflow: visible`.
        if ($this->containImpliesPaintClip($box)) {
            return true;
        }
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
    /**
     * CSS 2.1 §17.5.1 background layers 2 and 3 — column groups and
     * columns. `<col>` / `<colgroup>` generate no boxes of their own,
     * so their background is painted into the CELLS of the columns they
     * cover: the background POSITIONING AREA is the whole column strip,
     * while the paint is CLIPPED to each cell's border box.
     *
     * That split matters twice over. It is what the spec asks for, so a
     * `background-image` on a column tiles across the strip rather than
     * restarting in every cell; and because the paint only ever happens
     * per-cell, a column with no cells renders nothing at all — which
     * the spec also requires, and which a naive "fill the strip" would
     * get wrong.
     */
    private function paintTableColumnBackgrounds(
        \Phpdftk\HtmlToPdf\Box\TableBox $table,
        ContentStream $stream,
    ): void {
        if ($table->columnLayers === []) {
            return;
        }
        /** @var array<int, list<\Phpdftk\HtmlToPdf\Box\TableCellBox>> $byColumn */
        $byColumn = [];
        $this->collectTableCellsByColumn($table, $byColumn);
        if ($byColumn === []) {
            return;
        }
        // The strip's block extent is the table's whole row area.
        $stripTop = null;
        $stripBottom = null;
        foreach ($byColumn as $cells) {
            foreach ($cells as $cell) {
                $rect = $this->cellBorderBoxRect($cell);
                $stripTop = $stripTop === null ? $rect['y'] : min($stripTop, $rect['y']);
                $bottom = $rect['y'] + $rect['h'];
                $stripBottom = $stripBottom === null ? $bottom : max($stripBottom, $bottom);
            }
        }
        if ($stripTop === null || $stripBottom === null || $stripBottom <= $stripTop) {
            return;
        }
        foreach ($table->columnLayers as $layer) {
            $colBox = $layer['box'];
            if (!$this->boxHasPaintableBackground($colBox)) {
                continue;
            }
            $left = null;
            $right = null;
            /** @var list<\Phpdftk\HtmlToPdf\Box\TableCellBox> $covered */
            $covered = [];
            for ($c = $layer['start']; $c < $layer['start'] + $layer['span']; $c++) {
                foreach ($byColumn[$c] ?? [] as $cell) {
                    $rect = $this->cellBorderBoxRect($cell);
                    $left = $left === null ? $rect['x'] : min($left, $rect['x']);
                    $edge = $rect['x'] + $rect['w'];
                    $right = $right === null ? $edge : max($right, $edge);
                    $covered[] = $cell;
                }
            }
            if ($covered === [] || $left === null || $right === null || $right <= $left) {
                continue;
            }
            // Stand the column box up at the strip's geometry so the
            // existing background machinery (colour, images, repeat,
            // size, position) resolves against the strip.
            $strip = new \Phpdftk\HtmlToPdf\Layout\BoxGeometry();
            $strip->x = $left;
            $strip->y = $stripTop;
            $strip->width = $right - $left;
            $strip->height = $stripBottom - $stripTop;
            $original = $colBox->geometry;
            $colBox->geometry = $strip;
            foreach ($covered as $cell) {
                $rect = $this->cellBorderBoxRect($cell);
                if ($rect['w'] <= 0.0 || $rect['h'] <= 0.0) {
                    continue;
                }
                $stream->saveGraphicsState();
                $stream->rectangle(
                    $rect['x'],
                    $this->pageHeight - $rect['y'] - $rect['h'],
                    $rect['w'],
                    $rect['h'],
                );
                $stream->clip();
                $this->paintBackground($colBox, $stream);
                $stream->restoreGraphicsState();
            }
            $colBox->geometry = $original;
        }
    }

    /**
     * Border-box rect of a table cell in layout (top-down) coordinates.
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    private function cellBorderBoxRect(\Phpdftk\HtmlToPdf\Box\TableCellBox $cell): array
    {
        $g = $cell->geometry;
        return [
            'x' => $g->x - $g->paddingLeft - $g->borderLeft,
            'y' => $g->y - $g->paddingTop - $g->borderTop,
            'w' => $g->paddingLeft + $g->width + $g->paddingRight
                + $g->borderLeft + $g->borderRight,
            'h' => $g->paddingTop + $g->height + $g->paddingBottom
                + $g->borderTop + $g->borderBottom,
        ];
    }

    /**
     * Collect every cell in the table, keyed by the column it starts in.
     * A cell spanning N columns is registered under each of them so a
     * column background reaches the part of the cell it covers (the clip
     * still confines the paint to the cell itself).
     *
     * @param array<int, list<\Phpdftk\HtmlToPdf\Box\TableCellBox>> $byColumn
     */
    private function collectTableCellsByColumn(Box $box, array &$byColumn): void
    {
        foreach ($box->children as $child) {
            if ($child instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
                // A nested table owns its own column layers.
                continue;
            }
            if ($child instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox
                && $child->tableColumn !== null
            ) {
                for ($i = 0; $i < $child->tableColumnSpan; $i++) {
                    $byColumn[$child->tableColumn + $i][] = $child;
                }
                continue;
            }
            $this->collectTableCellsByColumn($child, $byColumn);
        }
    }

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
        // CSS Overflow 3 §4.2 — `overflow-clip-margin` expands the clip
        // region outward from a visual reference box (only for the `clip`
        // keyword; hidden/scroll/auto always clip at the padding box).
        [$expandL, $expandT, $expandR, $expandB] = $this->overflowClipMargin($box);
        // CSS Backgrounds 3 §5.3 — a rounded box clips its overflow to the
        // padding box's ROUNDED corners (border radii reduced by the border
        // widths). Only the standard both-axes padding-box clip is rounded;
        // one-axis clips and `overflow-clip-margin` expansions keep the rect.
        if ($clipsX && $clipsY
            && $expandL === 0.0 && $expandT === 0.0 && $expandR === 0.0 && $expandB === 0.0
        ) {
            $borderBoxW = $g->borderLeft + $padWidth + $g->borderRight;
            $borderBoxH = $g->borderTop + $padHeight + $g->borderBottom;
            $radii = $this->borderRadiiXY($box, $borderBoxW, $borderBoxH);
            if ($this->radiiAnyPositive($radii)) {
                $padRadii = $this->reduceRadiiToBox($radii, 'padding-box', $g);
                $this->buildRoundedRectPath($stream, $padX, $padTop, $padWidth, $padHeight, $padRadii, $this->cornerShapes($box));
                $stream->clip();
                $stream->endPath();
                return;
            }
        }
        $rectX = $clipsX ? $padX - $expandL : 0.0;
        $rectWidth = $clipsX ? $padWidth + $expandL + $expandR : $this->pageWidth;
        $rectTop = $clipsY ? $padTop - $expandT : 0.0;
        $rectHeight = $clipsY ? $padHeight + $expandT + $expandB : $this->pageHeight;
        $pdfY = $this->pageHeight - $rectTop - $rectHeight;
        $stream->rectangle($rectX, $pdfY, $rectWidth, $rectHeight);
        $stream->clip();
        $stream->endPath();
    }

    /**
     * CSS 2.1 §11.1.2 `clip: rect(top, right, bottom, left)` — resolve the
     * clipping rectangle (physical layout coords, y-down) for an
     * absolutely-positioned box, or `null` when `clip` is `auto`, the box
     * is not absolutely positioned, or the value isn't a `rect()`.
     * `top`/`left` offset from the border box's top/left edge; `right`/
     * `bottom` are measured from those same edges; `auto` means the
     * corresponding border edge.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private function resolveClipRect(Box $box): ?array
    {
        $clip = $box->style->get('clip');
        $edges = null;
        if ($clip instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($clip->name) === 'rect'
            && count($clip->arguments) === 4
        ) {
            // Comma-separated legacy form: `clip: rect(t, r, b, l)`.
            $edges = $clip->arguments;
        } elseif ($clip instanceof \Phpdftk\Css\Value\RectShape
            && count($clip->edges) === 4
        ) {
            // Whitespace-separated legacy form: `clip: rect(t r b l)` parses
            // to a RectShape (the CSS Shapes 2 `rect()` grammar) whose edges
            // carry the same top/right/bottom/left border-box-origin semantics
            // as the comma form (CSS 2.1 §11.1.2). `round <radius>` never
            // appears on the legacy `clip` property, so it is ignored.
            $edges = $clip->edges;
        }
        if ($edges === null) {
            return null;
        }
        $position = $box->style->get('position');
        if (!($position instanceof Keyword)) {
            return null;
        }
        $pos = strtolower($position->name);
        if ($pos !== 'absolute' && $pos !== 'fixed') {
            return null;
        }
        $g = $box->geometry;
        $borderBoxX = $g->x - $g->paddingLeft - $g->borderLeft;
        $borderBoxY = $g->y - $g->paddingTop - $g->borderTop;
        $borderBoxW = $g->borderLeft + $g->paddingLeft + $g->width
            + $g->paddingRight + $g->borderRight;
        $borderBoxH = $g->borderTop + $g->paddingTop + $g->height
            + $g->paddingBottom + $g->borderBottom;
        // rect(top, right, bottom, left); `auto` → border edge.
        [$topV, $rightV, $bottomV, $leftV] = $edges;
        $top = $this->clipEdgePx($topV, 0.0);
        $right = $this->clipEdgePx($rightV, $borderBoxW);
        $bottom = $this->clipEdgePx($bottomV, $borderBoxH);
        $left = $this->clipEdgePx($leftV, 0.0);
        $x = $borderBoxX + $left;
        $y = $borderBoxY + $top;
        return [
            'x' => $x,
            'y' => $y,
            'w' => max(0.0, ($borderBoxX + $right) - $x),
            'h' => max(0.0, ($borderBoxY + $bottom) - $y),
        ];
    }

    /**
     * CSS Masking 1 §6 — apply a `clip-path: <basic-shape>` to the box by
     * pushing a PDF clip path for the shape, resolved against the BORDER
     * box. Returns true when a clip was pushed (caller restores after the
     * children loop). Supports inset / circle / ellipse / polygon; the
     * `<geometry-box>` form and `url()` references are not handled.
     */
    /**
     * CSS Masking 1 §4 — true when the box's `mask-image` is a single
     * `url(...)` layer we cannot resolve to a mask image. Such a failed
     * layer is transparent black, so it masks the element (and subtree)
     * fully out. Restricted to a lone `Url` value: gradients (which we
     * render unmasked for now) and multi-layer / keyword values are left
     * alone so a merely-unsupported mask never hides content.
     */
    private function maskHidesElement(Box $box): bool
    {
        return $box->style->get('mask-image') instanceof \Phpdftk\Css\Value\Url;
    }

    /**
     * CSS Masking 1 §4 — resolve a `mask-image: <linear-gradient>` to the
     * Luminosity soft-mask ExtGState name that masks the box (and its
     * subtree) by the gradient, or null when the mask isn't a supported
     * single translucent linear gradient in the default `match-source`
     * (alpha) mode.
     *
     * `match-source` on a CSS `<image>` (a gradient, not an SVG `<mask>`)
     * resolves to `alpha`, so the mask value is the gradient's alpha
     * channel — exactly the DeviceGray alpha shading the background path
     * builds ({@see addLinearAlphaShading()}). The gradient is laid out
     * over the box's border box (the default mask-origin / mask-clip),
     * at its default size (auto → fills the box), so the geometry mirrors
     * a background gradient painted over that box.
     *
     * Bails (returns null → box paints unmasked) for anything outside this
     * narrow case — multi-layer masks, non-linear gradients, explicit
     * `luminance` mode, or any non-default mask geometry longhand — so an
     * unsupported mask never silently hides content.
     */
    /**
     * CSS Masking 1 §4 — resolve a `mask-image: url(<image>)` to a soft-mask
     * ExtGState name that masks the box by the image's alpha (`match-source`
     * / `alpha`) or luminance (`mask-mode: luminance`). Increment 1 handles a
     * single url() layer under the DEFAULT mask box model (border-box
     * origin/clip, position 0 0, size auto, repeat) drawn to fill the border
     * box; non-default geometry / multi-layer / url(#id) return null (the
     * caller falls back, so the box paints unmasked or blank as before).
     * Returns null when not applicable so the gradient builder can try next.
     */
    private function buildBoxImageMaskGsName(Box $box, ContentStream $stream): ?string
    {
        if ($this->writer === null || $this->page === null) {
            return null;
        }
        $maskImage = $box->style->get('mask-image');
        if (!$maskImage instanceof \Phpdftk\Css\Value\Url) {
            return null;
        }
        // In-document SVG references (`url(#mask)`) need element resolution —
        // a later increment; only external image URLs here.
        $src = $maskImage->url;
        if (str_starts_with(ltrim($src), '#')) {
            return null;
        }
        // Multi-layer mask compositing beyond a single `add` layer isn't
        // modeled yet.
        $composite = $box->style->get('mask-composite');
        if ($composite !== null && $composite->toCss() !== 'add') {
            return null;
        }
        // CSS Masking 1 §3.3 — `match-source` on an image (and explicit
        // `alpha`) uses the image's ALPHA; explicit `luminance` uses its
        // LUMINOSITY. (SVG `<mask>` elements default to luminance, but those
        // are url(#id) references handled later.)
        $subtype = 'Alpha';
        $maskMode = $box->style->get('mask-mode');
        if ($maskMode instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($maskMode->name) === 'luminance'
        ) {
            $subtype = 'Luminosity';
        }
        // CSS Masking 1 §4 — resolve the mask box model, reusing the
        // background origin/clip/size/position/repeat machinery. mask-origin
        // is the positioning area; mask-clip is the painted (group) region.
        $origin = $this->backgroundOriginRect($box, $this->resolveMaskOrigin($box));
        $clipName = $this->resolveMaskClip($box);
        if ($clipName === 'no-clip') {
            $clipX = 0.0;
            $clipTop = 0.0;
            $clipW = $this->pageWidth;
            $clipH = $this->pageHeight;
        } else {
            [$clipX, $clipTop, $clipW, $clipH] = $this->clipReferenceBox($box->geometry, $clipName);
        }
        if ($clipW <= 0.0 || $clipH <= 0.0) {
            return null;
        }
        $svgDoc = null;
        $imgName = null;
        $imgRef = null;
        if ($this->isSvgSrc($src)) {
            $svgDoc = $this->loadSvgDocument($src);
            if ($svgDoc === null) {
                return null;
            }
        } else {
            $resolved = $this->resolveImageSrc($src);
            if ($resolved === null) {
                return null;
            }
            try {
                $imgName = $this->writer->addImage($resolved, $this->page);
            } catch (\Throwable) {
                return null;
            }
            $imgRef = $this->page->corePage()->resources->xObject[$imgName] ?? null;
            if ($imgRef === null) {
                return null;
            }
        }
        // Final paint size (mask-size) then position (mask-position) within
        // the positioning area.
        $paint = $this->resolveBackgroundSize(
            $box->style->get('mask-size'),
            $src,
            $origin['width'],
            $origin['height'],
        );
        $maskPos = $box->style->get('mask-position');
        if ($maskPos !== null) {
            $pos = $this->resolveBackgroundPosition(
                $maskPos,
                $paint['w'],
                $paint['h'],
                $origin['width'],
                $origin['height'],
            );
            $paint['offsetX'] = $pos['offsetX'];
            $paint['offsetY'] = $pos['offsetY'];
        }
        $maskRepeat = $box->style->get('mask-repeat');
        $clipPdfY = $this->pageHeight - $clipTop - $clipH;
        try {
            $doc = \Phpdftk\Pdf\Writer\PdfDoc::wrap($this->writer);
            $group = $doc->createTransparencyGroup(
                new \Phpdftk\Geometry\Rectangle($clipX, $clipPdfY, $clipW, $clipH),
                function (
                    ContentStream $cs,
                    \Phpdftk\Pdf\Core\Content\Resources $res,
                ) use ($svgDoc, $imgName, $imgRef, $clipX, $clipTop, $clipW, $clipH, $paint, $maskRepeat, $origin): void {
                    $register = ($imgName !== null && $imgRef !== null)
                        ? function (string $n) use ($res, $imgRef): void {
                            $res->addXObject($n, $imgRef);
                        }
                    : null;
                    $this->drawImageTilesInto(
                        $cs,
                        $svgDoc,
                        $imgName,
                        $clipX,
                        $clipTop,
                        $clipW,
                        $clipH,
                        $paint,
                        $maskRepeat,
                        $origin['x'],
                        $origin['top'],
                        $origin['width'],
                        $origin['height'],
                        $register,
                    );
                },
            );
            return $this->page->ensureSoftMaskState($group, $subtype);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * CSS Masking 1 §4.5 — `mask-origin` positioning box. Initial `border-box`
     * (unlike `background-origin`'s `padding-box`).
     */
    private function resolveMaskOrigin(Box $box): string
    {
        $v = $box->style->get('mask-origin');
        if ($v instanceof \Phpdftk\Css\Value\Keyword) {
            $n = strtolower($v->name);
            if (in_array($n, ['content-box', 'padding-box', 'border-box'], true)) {
                return $n;
            }
        }
        return 'border-box';
    }

    /**
     * CSS Masking 1 §4.6 — `mask-clip` painting box (initial `border-box`).
     * `no-clip` leaves the mask unclipped; SVG visual boxes (fill/stroke/
     * view-box) map to the nearest supported CSS box.
     */
    private function resolveMaskClip(Box $box): string
    {
        $v = $box->style->get('mask-clip');
        if ($v instanceof \Phpdftk\Css\Value\Keyword) {
            $n = strtolower($v->name);
            if ($n === 'no-clip') {
                return 'no-clip';
            }
            if (in_array($n, ['content-box', 'padding-box', 'border-box'], true)) {
                return $n;
            }
        }
        return 'border-box';
    }

    private function buildBoxGradientMaskGsName(Box $box, ContentStream $stream): ?string
    {
        if ($this->writer === null || $this->page === null) {
            return null;
        }
        $maskImage = $box->style->get('mask-image');
        if (!$maskImage instanceof \Phpdftk\Css\Value\LinearGradient) {
            return null;
        }
        // Only the default `match-source` (→ alpha for a CSS image) or an
        // explicit `alpha` mode maps cleanly to the alpha shading. Any
        // other mode (`luminance`) would need a different mask value.
        $maskMode = $box->style->get('mask-mode');
        if ($maskMode instanceof \Phpdftk\Css\Value\Keyword) {
            $mode = strtolower($maskMode->name);
            if ($mode !== 'match-source' && $mode !== 'alpha') {
                return null;
            }
        }
        // Bail on any non-default mask geometry — those change where / how
        // the gradient tiles and we only model the full-border-box case.
        // The longhands are registered with initial values, so compare the
        // computed value's serialisation against the CSS initial rather
        // than testing for absence.
        $defaults = [
            'mask-repeat' => 'repeat',
            'mask-size' => 'auto',
            'mask-position' => '0% 0%',
            'mask-clip' => 'border-box',
            'mask-origin' => 'border-box',
            'mask-composite' => 'add',
        ];
        foreach ($defaults as $prop => $initial) {
            $value = $box->style->get($prop);
            if ($value !== null && $value->toCss() !== $initial) {
                return null;
            }
        }
        // A gradient with no translucent stop masks nothing out (mask is
        // fully opaque) — skip the soft mask entirely (fast path, no-op).
        if (!$this->gradientHasAlpha($maskImage->stops)) {
            return null;
        }
        [$bx, $by, $bw, $bh] = $this->clipReferenceBox($box->geometry, 'border-box');
        if ($bw <= 0.0 || $bh <= 0.0) {
            return null;
        }
        $tilePdfY = $this->pageHeight - $by - $bh;
        [$startX, $startY, $endX, $endY, $lineLength] =
            $this->linearGradientLine($maskImage->angleDeg, $bx, $tilePdfY, $bw, $bh);
        $stopList = $this->resolveGradientStops($maskImage->stops, $lineLength);
        if (count($stopList) < 2) {
            return null;
        }
        $alphaStops = $this->alphaStopsFrom($stopList);
        return $this->buildGradientAlphaMaskState(
            \Phpdftk\Pdf\Writer\PdfDoc::wrap($this->writer),
            fn(\Phpdftk\Pdf\Writer\PdfDoc $d): int => $d->addLinearAlphaShading(
                new \Phpdftk\Geometry\Point($startX, $startY),
                new \Phpdftk\Geometry\Point($endX, $endY),
                $alphaStops,
            )->objectNumber,
            $bx,
            $tilePdfY,
            $bw,
            $bh,
        );
    }

    /**
     * Compute a CSS linear-gradient line over a tile: its start / end
     * points (PDF coordinates) and full length. `angleDeg` follows the
     * CSS convention (0° points up, increases clockwise); the endpoints
     * land on the tile boundary corners per CSS Images 3 §3.1. Shared by
     * the background gradient painter and the gradient mask builder.
     *
     * @return array{float, float, float, float, float}
     *   [startX, startY, endX, endY, lineLength]
     */
    private function linearGradientLine(
        float $angleDeg,
        float $tileX,
        float $tilePdfY,
        float $tileWidth,
        float $tileHeight,
    ): array {
        $angle = fmod($angleDeg, 360.0);
        if ($angle < 0.0) {
            $angle += 360.0;
        }
        $rad = deg2rad($angle);
        $cx = $tileX + $tileWidth / 2;
        $cy = $tilePdfY + $tileHeight / 2;
        $sin = sin($rad);
        $cos = cos($rad);
        $halfLen = (abs($tileWidth * $sin) + abs($tileHeight * $cos)) / 2;
        return [
            $cx - $sin * $halfLen,
            $cy - $cos * $halfLen,
            $cx + $sin * $halfLen,
            $cy + $cos * $halfLen,
            $halfLen * 2,
        ];
    }

    private function applyClipPath(Box $box, ContentStream $stream): bool
    {
        $shape = $box->style->get('clip-path');
        // CSS Masking 1 §6 — `clip-path: <basic-shape> || <geometry-box>`.
        // When a reference box is present the value parses as a ValueList
        // of [shape, geometry-box keyword] (in either order); unwrap it so
        // the `instanceof` shape dispatch below still fires, and resolve
        // the shape against the named box instead of the default border box.
        $geometryBoxes = [
            'content-box', 'padding-box', 'border-box', 'margin-box',
            'fill-box', 'stroke-box', 'view-box',
        ];
        $refBox = 'border-box';
        $bareBox = false;
        if ($shape instanceof \Phpdftk\Css\Value\ValueList) {
            $extractedShape = null;
            foreach ($shape->values as $part) {
                if ($part instanceof \Phpdftk\Css\Value\BasicShape) {
                    $extractedShape = $part;
                } elseif ($part instanceof \Phpdftk\Css\Value\Keyword) {
                    $refBox = strtolower($part->name);
                }
            }
            $shape = $extractedShape;
            // A bare `<geometry-box>` with no shape clips to that box's edge.
            $bareBox = $extractedShape === null;
        } elseif ($shape instanceof \Phpdftk\Css\Value\Keyword
            && in_array(strtolower($shape->name), $geometryBoxes, true)
        ) {
            // `clip-path: <geometry-box>` with no shape — clip to that box.
            $refBox = strtolower($shape->name);
            $bareBox = true;
        }
        $g = $box->geometry;
        [$bx, $by, $bw, $bh] = $this->clipReferenceBox($g, $refBox);
        if ($bw <= 0.0 || $bh <= 0.0) {
            return false;
        }
        $ph = $this->pageHeight;
        if ($bareBox) {
            // CSS Masking 1 §6 — `clip-path: <geometry-box>` clips to that
            // box's edge. When the element has border-radius the clip follows
            // the (reference-box-reduced) rounded corners (CSS Backgrounds
            // 3 §5.3); otherwise a plain rectangle.
            $stream->saveGraphicsState();
            $g = $box->geometry;
            $borderBoxW = $g->borderLeft + $g->paddingLeft + $g->width + $g->paddingRight + $g->borderRight;
            $borderBoxH = $g->borderTop + $g->paddingTop + $g->height + $g->paddingBottom + $g->borderBottom;
            $radii = $this->reduceRadiiToBox(
                $this->borderRadiiXY($box, $borderBoxW, $borderBoxH),
                $refBox,
                $g,
            );
            if ($this->radiiAnyPositive($radii)) {
                $this->buildRoundedRectPath($stream, $bx, $by, $bw, $bh, $radii, $this->cornerShapes($box));
            } else {
                $stream->rectangle($bx, $ph - $by - $bh, $bw, $bh);
            }
            $stream->clip();
            $stream->endPath();
            return true;
        }
        if ($shape instanceof \Phpdftk\Css\Value\InsetShape) {
            $ins = $shape->insets;
            // 1–4 values: top, right, bottom, left (CSS shorthand expansion).
            $n = count($ins);
            $top = $this->shapeLengthPercent($ins[0], $bh);
            $right = $this->shapeLengthPercent($ins[$n >= 2 ? 1 : 0], $bw);
            $bottom = $this->shapeLengthPercent($ins[$n >= 3 ? 2 : 0], $bh);
            $left = $this->shapeLengthPercent($ins[$n >= 4 ? 3 : ($n >= 2 ? 1 : 0)], $bw);
            $x = $bx + $left;
            $w = max(0.0, $bw - $left - $right);
            $h = max(0.0, $bh - $top - $bottom);
            $stream->saveGraphicsState();
            $stream->rectangle($x, $ph - ($by + $top) - $h, $w, $h);
            $stream->clip();
            $stream->endPath();
            return true;
        }
        if ($shape instanceof \Phpdftk\Css\Value\CircleShape) {
            $cx = $bx + $this->positionComponent($shape->centerX, $bw);
            $cy = $by + $this->positionComponent($shape->centerY, $bh);
            $r = $this->circleRadius($shape->radius, $cx - $bx, $cy - $by, $bw, $bh);
            $stream->saveGraphicsState();
            $this->emitEllipsePath($stream, $cx, $ph - $cy, $r, $r);
            $stream->clip();
            $stream->endPath();
            return true;
        }
        if ($shape instanceof \Phpdftk\Css\Value\EllipseShape) {
            $cx = $bx + $this->positionComponent($shape->centerX, $bw);
            $cy = $by + $this->positionComponent($shape->centerY, $bh);
            $rx = $this->ellipseRadius($shape->radiusX, $cx - $bx, $bw, $bw);
            $ry = $this->ellipseRadius($shape->radiusY, $cy - $by, $bh, $bh);
            $stream->saveGraphicsState();
            $this->emitEllipsePath($stream, $cx, $ph - $cy, $rx, $ry);
            $stream->clip();
            $stream->endPath();
            return true;
        }
        if ($shape instanceof \Phpdftk\Css\Value\PolygonShape) {
            if (count($shape->vertices) < 3) {
                return false;
            }
            $stream->saveGraphicsState();
            foreach ($shape->vertices as $i => [$vx, $vy]) {
                $px = $bx + $this->shapeLengthPercent($vx, $bw);
                $py = $ph - ($by + $this->shapeLengthPercent($vy, $bh));
                if ($i === 0) {
                    $stream->moveTo($px, $py);
                } else {
                    $stream->lineTo($px, $py);
                }
            }
            $stream->closePath();
            if (strtolower($shape->fillRule) === 'evenodd') {
                $stream->clipEvenOdd();
            } else {
                $stream->clip();
            }
            $stream->endPath();
            return true;
        }
        if ($shape instanceof \Phpdftk\Css\Value\RectShape) {
            // CSS Shapes 2 §4.5 — rect(top right bottom left): each is an edge
            // POSITION from the box origin (top/bottom against height, left/right
            // against width). `auto` resolves to the matching box edge: top/left
            // → 0, right/bottom → the box size.
            $e = $shape->edges;
            $top = $this->rectEdge($e[0] ?? null, $bh, 0.0);
            $right = $this->rectEdge($e[1] ?? null, $bw, $bw);
            $bottom = $this->rectEdge($e[2] ?? null, $bh, $bh);
            $left = $this->rectEdge($e[3] ?? null, $bw, 0.0);
            $w = max(0.0, $right - $left);
            $h = max(0.0, $bottom - $top);
            $stream->saveGraphicsState();
            $stream->rectangle($bx + $left, $ph - ($by + $top) - $h, $w, $h);
            $stream->clip();
            $stream->endPath();
            return true;
        }
        if ($shape instanceof \Phpdftk\Css\Value\XywhShape) {
            // CSS Shapes 2 §4.6 — xywh(x y width height): clip rectangle by
            // origin + size (x/width against box width, y/height against height).
            $x = $this->shapeLengthPercent($shape->x, $bw);
            $y = $this->shapeLengthPercent($shape->y, $bh);
            $w = $this->shapeLengthPercent($shape->width, $bw);
            $h = $this->shapeLengthPercent($shape->height, $bh);
            $stream->saveGraphicsState();
            $stream->rectangle($bx + $x, $ph - ($by + $y) - $h, $w, $h);
            $stream->clip();
            $stream->endPath();
            return true;
        }
        return false;
    }

    /**
     * CSS Masking 1 §6 / CSS Box 3 — resolve a `<geometry-box>` keyword to
     * the box rectangle `[left, top, width, height]` (top-left origin, y
     * down, CSS px) a clip shape is measured against. `$g->x` / `$g->y` are
     * the CONTENT box origin; padding / border / margin extend outward.
     * The SVG-only boxes map to their closest CSS box for HTML elements
     * (fill-box → content, stroke-box / view-box → border), per the spec's
     * fallback for elements without an SVG geometry.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function clipReferenceBox(\Phpdftk\HtmlToPdf\Layout\BoxGeometry $g, string $box): array
    {
        return match ($box) {
            'content-box', 'fill-box' => [
                $g->x,
                $g->y,
                $g->width,
                $g->height,
            ],
            'padding-box' => [
                $g->x - $g->paddingLeft,
                $g->y - $g->paddingTop,
                $g->paddingLeft + $g->width + $g->paddingRight,
                $g->paddingTop + $g->height + $g->paddingBottom,
            ],
            'margin-box' => [
                $g->x - $g->paddingLeft - $g->borderLeft - $g->marginLeft,
                $g->y - $g->paddingTop - $g->borderTop - $g->marginTop,
                $g->marginLeft + $g->borderLeft + $g->paddingLeft + $g->width
                    + $g->paddingRight + $g->borderRight + $g->marginRight,
                $g->marginTop + $g->borderTop + $g->paddingTop + $g->height
                    + $g->paddingBottom + $g->borderBottom + $g->marginBottom,
            ],
            // border-box (the clip-path default), stroke-box, view-box.
            default => [
                $g->x - $g->paddingLeft - $g->borderLeft,
                $g->y - $g->paddingTop - $g->borderTop,
                $g->borderLeft + $g->paddingLeft + $g->width + $g->paddingRight + $g->borderRight,
                $g->borderTop + $g->paddingTop + $g->height + $g->paddingBottom + $g->borderBottom,
            ],
        };
    }

    /**
     * Resolve one `rect()` edge to an absolute position (px) against `$basis`.
     * A non-length value (the `auto` keyword, or a missing edge) resolves to
     * `$auto` — the matching box edge per CSS Shapes 2 §4.5.
     */
    private function rectEdge(?\Phpdftk\Css\Value\Value $value, float $basis, float $auto): float
    {
        if ($value instanceof \Phpdftk\Css\Value\Length
            || $value instanceof \Phpdftk\Css\Value\Percentage
        ) {
            return $this->lengthOrPercentageToFloat($value, $basis);
        }
        return $auto;
    }

    /**
     * `<length-percentage>` from a basic-shape's generic `Value` slot →
     * px against `$basis`; non-length/percentage values resolve to 0.
     */
    private function shapeLengthPercent(\Phpdftk\Css\Value\Value $value, float $basis): float
    {
        if ($value instanceof \Phpdftk\Css\Value\Length
            || $value instanceof \Phpdftk\Css\Value\Percentage
        ) {
            return $this->lengthOrPercentageToFloat($value, $basis);
        }
        return 0.0;
    }

    /**
     * Approximate an ellipse (radii rx, ry) centred at (cx, cy) with four
     * cubic Béziers and append it as the current path.
     */
    private function emitEllipsePath(ContentStream $stream, float $cx, float $cy, float $rx, float $ry): void
    {
        $kx = $rx * 0.5522847498307933;
        $ky = $ry * 0.5522847498307933;
        $stream->moveTo($cx + $rx, $cy);
        $stream->curveTo($cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry);
        $stream->curveTo($cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy);
        $stream->curveTo($cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry);
        $stream->curveTo($cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy);
    }

    /**
     * Resolve a `<position>` component (clip-path center). `null` →
     * centre (50%); keywords map to 0 / 50% / 100% of `$basis`.
     */
    private function positionComponent(?\Phpdftk\Css\Value\Value $value, float $basis): float
    {
        if ($value === null) {
            return $basis * 0.5;
        }
        if ($value instanceof Keyword) {
            return match (strtolower($value->name)) {
                'left', 'top' => 0.0,
                'right', 'bottom' => $basis,
                default => $basis * 0.5,
            };
        }
        if ($value instanceof \Phpdftk\Css\Value\Length
            || $value instanceof \Phpdftk\Css\Value\Percentage
        ) {
            return $this->lengthOrPercentageToFloat($value, $basis);
        }
        return $basis * 0.5;
    }

    /**
     * CSS Shapes 1 — resolve a `circle()` radius. `null` / keyword
     * default `closest-side`; percentage against `sqrt(W²+H²)/√2`.
     */
    private function circleRadius(?\Phpdftk\Css\Value\Value $value, float $cx, float $cy, float $w, float $h): float
    {
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $value->value / 100.0 * (sqrt($w * $w + $h * $h) / M_SQRT2);
        }
        $kw = $value instanceof Keyword ? strtolower($value->name) : 'closest-side';
        $sides = [$cx, $w - $cx, $cy, $h - $cy];
        $corners = [
            hypot($cx, $cy), hypot($w - $cx, $cy),
            hypot($cx, $h - $cy), hypot($w - $cx, $h - $cy),
        ];
        return match ($kw) {
            'farthest-side' => max($sides),
            'closest-corner' => min($corners),
            'farthest-corner' => max($corners),
            default => min($sides), // closest-side
        };
    }

    /**
     * Resolve one `ellipse()` radius (`rx` or `ry`). `null` / keyword
     * default `closest-side`; percentage against `$pctBasis`; `$center`
     * is the centre offset along this axis, `$extent` the box extent.
     */
    private function ellipseRadius(?\Phpdftk\Css\Value\Value $value, float $center, float $extent, float $pctBasis): float
    {
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $value->value / 100.0 * $pctBasis;
        }
        $kw = $value instanceof Keyword ? strtolower($value->name) : 'closest-side';
        return $kw === 'farthest-side'
            ? max($center, $extent - $center)
            : min($center, $extent - $center);
    }

    /**
     * One `clip` rect edge → px. `auto` returns `$autoValue` (the border
     * edge); a `<length>` / `0` returns its value.
     */
    private function clipEdgePx(\Phpdftk\Css\Value\Value $value, float $autoValue): float
    {
        if ($value instanceof Keyword && strtolower($value->name) === 'auto') {
            return $autoValue;
        }
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer
            || $value instanceof \Phpdftk\Css\Value\Number
        ) {
            return (float) $value->value;
        }
        return $autoValue;
    }

    /**
     * Return true when the box's cumulative `transform` rotates the
     * backface forward AND `backface-visibility: hidden` is set.
     * We walk the box's rotate functions, summing the (signed) X /
     * Y rotations; the backface is forward-facing when either total
     * exceeds 90° (mod 360°) on the canonical face.
     */
    private function isBackfaceHidden(Box $box): bool
    {
        $visibility = $box->style->get('backface-visibility');
        if (!$visibility instanceof Keyword
            || strtolower($visibility->name) !== 'hidden'
        ) {
            return false;
        }
        $transform = $box->style->get('transform');
        if (!$transform instanceof \Phpdftk\Css\Value\Transform) {
            return false;
        }
        $cumX = 0.0;
        $cumY = 0.0;
        foreach ($transform->functions as $fn) {
            if (!$fn instanceof \Phpdftk\Css\Value\RotateTransform) {
                continue;
            }
            $axisLen = sqrt($fn->ax * $fn->ax + $fn->ay * $fn->ay + $fn->az * $fn->az);
            if ($axisLen <= 0.0) {
                continue;
            }
            // Distribute the rotation across X / Y by axis component.
            $cumX += $fn->angleDeg * ($fn->ax / $axisLen);
            $cumY += $fn->angleDeg * ($fn->ay / $axisLen);
        }
        $isFlippedX = cos(deg2rad($cumX)) < 0;
        $isFlippedY = cos(deg2rad($cumY)) < 0;
        return $isFlippedX || $isFlippedY;
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
            // CSS Backgrounds 3 §6 — a fully-transparent shadow colour
            // contributes nothing visible; skip the paint so the
            // alpha=0 colour doesn't resolve through the DeviceRGB
            // `rg` operator (which has no alpha) and render as black.
            if ($color->a <= 0.0) {
                continue;
            }
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
     * Filter Effects 1 §16.1 — paint `filter: drop-shadow(...)` as an
     * offset rect behind the box. Syntax matches `box-shadow`'s
     * `<offset-x> <offset-y> <blur>? <color>?` minus `inset` and
     * `spread`. Multiple drop-shadow filters in the value list paint
     * back-to-front (first listed sits on top), matching CSS stacking.
     * Other filter primitives (`blur`, `brightness`, `grayscale`, …)
     * silently fall through — they require raster pre-painting.
     */
    private function paintFilterDropShadow(Box $box, ContentStream $stream): void
    {
        $value = $box->style->get('filter');
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'none')
        ) {
            return;
        }
        $shadows = $this->collectDropShadowFilters($value);
        if ($shadows === []) {
            return;
        }
        $defaultColor = $box->style->get('color');
        $textColor = $defaultColor instanceof Color ? $defaultColor : new Color(0, 0, 0, 1);
        $geo = $box->geometry;
        foreach (array_reverse($shadows) as $shadow) {
            $color = $shadow['color'] ?? $textColor;
            $x = $geo->x - $geo->paddingLeft - $geo->borderLeft + $shadow['offsetX'];
            $top = $geo->y - $geo->paddingTop - $geo->borderTop + $shadow['offsetY'];
            $width = $geo->paddingLeft + $geo->width + $geo->paddingRight
                + $geo->borderLeft + $geo->borderRight;
            $height = $geo->paddingTop + $geo->height + $geo->paddingBottom
                + $geo->borderTop + $geo->borderBottom;
            if ($width <= 0.0 || $height <= 0.0) {
                continue;
            }
            $this->emitRect($stream, $x, $top, $width, $height, fill: $color);
        }
    }

    /**
     * Walk a `filter` value collecting every `drop-shadow(...)` call.
     * Returns `[]` when no drop-shadow appears (other filter primitives
     * are skipped without warning).
     *
     * @return list<array{offsetX: float, offsetY: float, blur: float, color: ?Color}>
     */
    private function collectDropShadowFilters(\Phpdftk\Css\Value\Value $value): array
    {
        $out = [];
        // Filter post-processing typed form: Filter<list<FilterFunction>>.
        if ($value instanceof \Phpdftk\Css\Value\Filter) {
            foreach ($value->functions as $fn) {
                if ($fn->kind === \Phpdftk\Css\Value\FilterKind::DropShadow) {
                    $parsed = $this->parseDropShadowArgs($fn->args);
                    if ($parsed !== null) {
                        $out[] = $parsed;
                    }
                }
            }
            return $out;
        }
        // Legacy generic form (CssFunction / ValueList<CssFunction>) for
        // value-paths that bypass Parser::makeDeclaration.
        $items = $value instanceof \Phpdftk\Css\Value\ValueList
            ? $value->values
            : [$value];
        foreach ($items as $item) {
            if (!$item instanceof \Phpdftk\Css\Value\CssFunction) {
                continue;
            }
            if (strtolower($item->name) !== 'drop-shadow') {
                continue;
            }
            $parsed = $this->parseDropShadowArgs($item->arguments);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }
        return $out;
    }

    /**
     * Parse `drop-shadow(<offset-x> <offset-y> [<blur>] [<color>])`
     * arguments into a layer struct. The CSS parser wraps a
     * space-separated function-arg list inside a single ValueList,
     * so flatten one level of ValueList before scanning.
     *
     * @param list<\Phpdftk\Css\Value\Value> $args
     * @return array{offsetX: float, offsetY: float, blur: float, color: ?Color}|null
     */
    private function parseDropShadowArgs(array $args): ?array
    {
        $flat = [];
        foreach ($args as $a) {
            if ($a instanceof \Phpdftk\Css\Value\ValueList) {
                foreach ($a->values as $inner) {
                    $flat[] = $inner;
                }
            } else {
                $flat[] = $a;
            }
        }
        $color = null;
        $lengths = [];
        foreach ($flat as $a) {
            if ($a instanceof Color) {
                $color = $a;
                continue;
            }
            if ($a instanceof \Phpdftk\Css\Value\Length) {
                $lengths[] = $a->value;
                continue;
            }
            if ($a instanceof \Phpdftk\Css\Value\Integer
                || $a instanceof \Phpdftk\Css\Value\Number
            ) {
                $lengths[] = (float) $a->value;
            }
        }
        if (count($lengths) < 2) {
            return null;
        }
        return [
            'offsetX' => $lengths[0],
            'offsetY' => $lengths[1],
            'blur' => $lengths[2] ?? 0.0,
            'color' => $color,
        ];
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
        // Text emission needs a registered font to look up. Skip
        // entirely when neither the registered map nor the explicit
        // default supplies a candidate Tf resource for any fragment —
        // the result is a no-op text pass so background/border
        // content still renders.
        if ($box->lineBoxes === []
            || ($this->defaultFont === null && $this->registeredFonts === [])
        ) {
            return;
        }
        $color = $box->style->get('color');
        $textColor = $color instanceof Color ? $color : new Color(0, 0, 0, 1);

        $shadows = $this->collectTextShadowLayers($box, $textColor);
        // CSS 2.1 Appendix E — inline content paints in TREE order, so each
        // line's background goes down immediately before its own glyphs
        // rather than every background preceding every glyph. The difference
        // shows once a background can extend past its line: an inline whose
        // vertical padding bleeds upward has to cover the previous line's
        // text, which it cannot do if that text is painted afterwards.
        foreach ($box->lineBoxes as $line) {
            // Inline backgrounds (`<mark>` and friends) paint as a strip
            // behind each fragment carrying a `backgroundColor`, before this
            // line's text + shadow passes so its own glyphs sit on top.
            $this->paintInlineBackgrounds($box, $line, $stream);
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
        /** @var list<array{x: float, width: float, color: Color, above: float, below: float}> $runs */
        $runs = [];
        foreach ($line->fragments as $fragment) {
            $bg = $fragment->backgroundColor;
            // A fully transparent background paints nothing — the CSS
            // `background-color` initial is `transparent` (rgba(0,0,0,0)),
            // which every inline box computes and propagates to its
            // fragments, so without this guard a plain nested inline (or
            // generated counter/marker content) fills an opaque BLACK rect
            // over its own text. Mirrors the alpha guards already at
            // paintBoxShadow and boxHasPaintableBackground.
            if ($bg === null || $bg->a <= 0.0) {
                continue;
            }
            $last = $runs === [] ? null : $runs[array_key_last($runs)];
            $sameAsLast = $last !== null
                && abs($last['x'] + $last['width'] - $fragment->x) < 0.001
                && $last['color']->r === $bg->r
                && $last['color']->g === $bg->g
                && $last['color']->b === $bg->b
                && $last['above'] === $fragment->bgExtendAbove
                && $last['below'] === $fragment->bgExtendBelow;
            if ($sameAsLast) {
                $runs[array_key_last($runs)]['width'] = ($fragment->x + $fragment->width) - $last['x'];
            } else {
                $runs[] = [
                    'x' => $fragment->x,
                    'width' => $fragment->width,
                    'color' => $bg,
                    'above' => $fragment->bgExtendAbove,
                    'below' => $fragment->bgExtendBelow,
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
            // CSS 2.1 §10.6.1 — an inline box's vertical padding and border
            // do not grow the line box, but they ARE painted, so the
            // background bleeds over the lines above and below rather than
            // being clipped to this one.
            $stream->rectangle(
                $box->geometry->x + $run['x'],
                $pdfY - $run['below'],
                $run['width'],
                $line->height + $run['above'] + $run['below'],
            );
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
    /**
     * Resolved `text-decoration-skip-spaces` mode: `none` (decorate
     * through all white space), `all` (skip every white-space run), or
     * `start end` — the initial value, and the fallback for any value
     * we do not model.
     *
     * @return 'none'|'all'|'start end'
     */
    private function decorationSkipSpaces(Box $box): string
    {
        $value = $box->style->get('text-decoration-skip-spaces');
        if (!($value instanceof Keyword)) {
            return 'start end';
        }
        return match (strtolower($value->name)) {
            'none' => 'none',
            'all' => 'all',
            default => 'start end',
        };
    }

    private function paintTextDecorations(Box $box, LineBox $line, ContentStream $stream, Color $color): void
    {
        $blockLines = $this->textDecorationLines($box);
        $decoColor = $this->textDecorationColor($box, $color);
        // CSS Text Decoration 4 §5 — `text-decoration-skip-spaces`.
        // The initial `start end` keeps decorations off the white space
        // at each END of the line while still drawing through interior
        // spaces, so locate the first and last fragments with ink.
        $skipSpaces = $this->decorationSkipSpaces($box);
        $firstInk = null;
        $lastInk = null;
        if ($skipSpaces !== 'none') {
            foreach ($line->fragments as $inkIndex => $inkFragment) {
                if (!$inkFragment->isWhitespace) {
                    $firstInk ??= $inkIndex;
                    $lastInk = $inkIndex;
                }
            }
        }
        foreach ($line->fragments as $fragmentIndex => $fragment) {
            if ($fragment->isWhitespace && $skipSpaces !== 'none') {
                if ($skipSpaces === 'all'
                    || $firstInk === null
                    || $lastInk === null
                    || $fragmentIndex < $firstInk
                    || $fragmentIndex > $lastInk
                ) {
                    continue;
                }
            }
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
            // CSS Text Decoration 4 §4 — these belong to the box that
            // INTRODUCED the decoration. When the block itself declares
            // no lines, the decoration came from an inline ancestor via
            // `$fragment->decorationLines`, and applying the block's
            // own thickness / style to it is simply reading the wrong
            // box — the same reasoning `$fragment->decorationColor`
            // already follows below.
            $explicitThickness = $blockLines === []
                ? null
                : $this->resolveDecorationThickness($box, $fontSize);
            if ($explicitThickness !== null) {
                $thickness = max(0.5, $explicitThickness);
            }
            // `text-underline-offset` shifts the underline ONLY (not
            // overline or line-through). Positive values push the line
            // further below the baseline.
            $explicitUnderlineOffset = $this->resolveUnderlineOffset($box, $fontSize);
            $x = $box->geometry->x + $fragment->x;
            $width = $fragment->width;
            // Anchor decorations to the fragment's painted baseline: the
            // line's shared baseline plus the fragment's own vertical-align
            // shift (matching paintFragment). The underline/overline offsets
            // below stay relative to the fragment's own ascent.
            $baselineY = $box->geometry->y + $line->y + $line->baseline + $fragment->baselineShift;
            $style = $blockLines === [] ? 'solid' : $this->textDecorationStyle($box);
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
                    // The offset is the band's TOP, so centring a
                    // line-through on the x-height middle means lifting
                    // it by half its own thickness — otherwise a thick
                    // line drifts downward off the glyphs.
                    'line-through' => -0.3 * $fontSize - $thickness / 2.0,
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
        // CSS2 §10.8 — every baseline-aligned fragment on the line shares the
        // line's single baseline (`line.baseline`, distance from line top),
        // so mixed-size runs line up instead of each sitting at its own
        // ascent. `baselineShift` layers `vertical-align: sub`/`super` (and,
        // later, the keyword offsets) on top; negative raises, positive
        // lowers. Add in layout-Y space, then flip to PDF-Y.
        $baselineY = $box->geometry->y + $line->y + $line->baseline + $offsetY + $fragment->baselineShift;
        $pdfY = $this->pageHeight - $baselineY;

        // Pick the RegisteredFont matching this fragment's shaped font.
        // The map is keyed by OpenType postScriptName; the shaping context
        // chose the font, so this lookup just hands the painter the right
        // PDF Tf resource. Falls back to defaultFont when the fragment's
        // font wasn't registered (e.g., the resolver returned defaultFont
        // and there's no alt map).
        $registered = $this->registeredFonts[$font->postScriptName] ?? $this->defaultFont;
        if ($registered === null) {
            // No registered Tf resource matches this fragment's font and
            // no default font is set either. Emitting a `Tf` op with a
            // bare postScriptName yields garbage in the viewer (some
            // fall back to an arbitrary font, others render nothing) —
            // both cases regress reftests that previously produced an
            // empty page. Skip the fragment instead.
            return;
        }
        $stream->setFont($registered, $shapedRun->fontSizePt);
        // Fake-italic via a 12° skew in the Tm `c` slot (≈ tan(12°) = 0.213).
        // CSS Fonts 4 §6.4.1 lets browsers synthesise oblique from regular
        // when no real italic face is registered; this is the same trick.
        $skew = $fragment->isItalic ? 0.213 : 0.0;
        // CSS Writing Modes 4 §5 — for vertical writing modes,
        // rotate the text matrix 90° clockwise so the line's
        // horizontal advance (text-space x) becomes a vertical
        // descent in PDF (page-space -y). Anchor at the line's
        // TOP-LEFT corner — `applyVerticalLineShift` placed every
        // line at y = 0 and shifted fragments along x to stack
        // lines as parallel columns right-to-left for vrl; the
        // anchor here matches that placement.
        $wm = WritingMode::fromStyle($box->style);
        if ($wm->isVertical()) {
            // The 90°-clockwise rotation maps the glyph's ascent to
            // +deviceX (physical right / line-over) and its descent to
            // -deviceX (physical left / line-under) for BOTH vertical-lr
            // and vertical-rl. The alphabetic drawing baseline therefore
            // sits a `descent` in from the column's line-under (left)
            // edge, centred inside the column's cross-size (its line box
            // extent) with symmetric half-leading — so the em box lands
            // centred in the column rather than flush to the left edge.
            $descent = (abs($font->descent) / max(1, $font->unitsPerEm)) * $shapedRun->fontSizePt;
            $halfLeading = max(0.0, ($line->height - ($ascent + $descent)) / 2.0);
            $columnX = $box->geometry->x + $fragment->x + $offsetX + $halfLeading + $descent;
            // Increment 2: `blockOffset` places each fragment DOWN the column
            // by its original inline advance, so multiple fragments in one
            // source line stack vertically instead of sharing the column top.
            $columnTopLayoutY = $box->geometry->y + $line->y + $offsetY + $fragment->blockOffset;
            $columnTopPdfY = $this->pageHeight - $columnTopLayoutY;
            $stream->setTextMatrix(0.0, -1.0, 1.0, 0.0, $columnX, $columnTopPdfY);
        } else {
            // Tm reseats the text matrix at each fragment's left baseline,
            // which is simpler than tracking incremental Td offsets between
            // fragments.
            $stream->setTextMatrix(1, 0, $skew, 1, $x, $pdfY);
        }
        // Fake-bold via text rendering mode 2 (fill + stroke). Stroke
        // contributes ≈ fontSize × 0.04 of extra thickness — visually close
        // to the design-weight increment for bold. Match the stroke color
        // to the cascaded fill color so the bold outline doesn't bleed in a
        // different hue. Always re-emit the Tr so a non-bold fragment that
        // follows a bold one resets to fill-only.
        if ($color->a <= 0.0) {
            // CSS Color 4 — fully-transparent text (`color: transparent`,
            // alpha 0) paints no marks. `setFillColorRGB` drops alpha, so a
            // transparent colour reaches here as opaque black (rgb 0,0,0)
            // and would fill the glyphs solid. Switch to PDF text rendering
            // mode 3 (invisible): the glyphs still emit — so the text stays
            // extractable and contributes to the tagged-PDF structure — but
            // they leave no visible ink, matching how browsers print
            // transparent text. This is an extremely common WPT idiom
            // (Ahem "filler" text under `color: transparent`).
            $stream->setTextRenderingMode(3);
        } elseif ($fragment->isBold) {
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
            // CSS Fonts 4 §3.5: `font-size: 0` is legal — the glyphs
            // still emit, but at zero advance. Skip the kern fixup so
            // we don't divide by zero; nothing to nudge in PDF text
            // space when each glyph already advances 0.
            $kern = $fontSize > 0.0 ? $delta * 1000.0 / $fontSize : 0.0;
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
        // CSS Backgrounds 3 §3.11.2 — the background-source box (root,
        // or the body when the root is transparent) was already painted
        // across the entire canvas before the tree walk; skip its own
        // per-box paint to avoid double-fill.
        if ($box === $this->propagatedBgBox) {
            return;
        }
        $color = $this->resolveColorWithCurrentColor(
            $box->style->get('background-color'),
            $box,
        );
        // CSS Backgrounds 3 §2.1 — `background-image` may resolve to a
        // comma-separated list of images (layers). The first-listed image
        // paints on top; later images paint below. Build the layer list
        // and reject any non-paintable entries (e.g. `none`).
        $bgImage = $box->style->get('background-image');
        $layers = $this->extractBackgroundLayers($bgImage);
        $hasColor = $color instanceof Color && $color->a > 0.0;
        $hasAnyLayer = $layers !== [];
        if (!$hasColor && !$hasAnyLayer) {
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
            default: // 'border-box' or 'border-area' — both use border-box dims as the bounding rect
                $x = $geo->x - $geo->paddingLeft - $geo->borderLeft;
                $top = $geo->y - $geo->paddingTop - $geo->borderTop;
                $width = $geo->paddingLeft + $geo->width + $geo->paddingRight
                    + $geo->borderLeft + $geo->borderRight;
                $height = $geo->paddingTop + $geo->height + $geo->paddingBottom
                    + $geo->borderTop + $geo->borderBottom;
        }
        if ($hasColor) {
            // Border radii resolve against the border box, then reduce inward
            // to the background-clip box (CSS Backgrounds 3 §5.3), so a
            // `padding-box` / `content-box` background rounds its (smaller)
            // corners rather than reusing the full border-box radius.
            $borderBoxW = $geo->paddingLeft + $geo->width + $geo->paddingRight
                + $geo->borderLeft + $geo->borderRight;
            $borderBoxH = $geo->paddingTop + $geo->height + $geo->paddingBottom
                + $geo->borderTop + $geo->borderBottom;
            $radii = $this->reduceRadiiToBox(
                $this->borderRadiiXY($box, $borderBoxW, $borderBoxH),
                $clip,
                $geo,
            );
            // CSS Backgrounds 4 — `background-clip: border-area`
            // paints only on the border ring. Emit border-box rect
            // ∪ padding-box rect with the even-odd fill rule so the
            // inner padding-box is left unfilled (a ring of bg).
            // Radii are ignored on this branch — rounded-corner
            // ring support is a follow-up.
            if ($clip === 'border-area') {
                $padX = $geo->x - $geo->paddingLeft;
                $padTop = $geo->y - $geo->paddingTop;
                $padWidth = $geo->paddingLeft + $geo->width + $geo->paddingRight;
                $padHeight = $geo->paddingTop + $geo->height + $geo->paddingBottom;
                $outerPdfY = $this->pageHeight - $top - $height;
                $padPdfY = $this->pageHeight - $padTop - $padHeight;
                $stream->saveGraphicsState();
                $stream->setFillColorRGB($color->r, $color->g, $color->b);
                $stream->rectangle($x, $outerPdfY, $width, $height);
                $stream->rectangle($padX, $padPdfY, $padWidth, $padHeight);
                $stream->fillEvenOdd();
                $stream->restoreGraphicsState();
            } elseif ($this->radiiAnyPositive($radii)) {
                $this->emitRoundedFill($stream, $x, $top, $width, $height, $radii, $color, $this->cornerShapes($box));
            } else {
                $this->emitRect($stream, $x, $top, $width, $height, fill: $color);
            }
        }
        // All three image-class properties resolve against the same
        // bg-origin / bg-size / bg-position trio. Hoist once so the
        // gradient branches can reuse the rects computed for the
        // raster path.
        $needBgImageProps = $hasAnyLayer && $width > 0.0 && $height > 0.0;
        // CSS Backgrounds 4 §3.5 — `border-area` paints bg-image
        // ONLY inside the border ring. Wrap the image-paint cluster
        // in a graphics state + even-odd clip path so paint inside
        // the padding-box is masked off. Rectangle radii are still
        // a follow-up (the rounded-ring case needs Bezier path
        // intersection beyond the rect-only clip below).
        $needBorderAreaClip = $needBgImageProps && $clip === 'border-area';
        if ($needBorderAreaClip) {
            $padX = $geo->x - $geo->paddingLeft;
            $padTop = $geo->y - $geo->paddingTop;
            $padWidth = $geo->paddingLeft + $geo->width + $geo->paddingRight;
            $padHeight = $geo->paddingTop + $geo->height + $geo->paddingBottom;
            $outerPdfYClip = $this->pageHeight - $top - $height;
            $padPdfYClip = $this->pageHeight - $padTop - $padHeight;
            $stream->saveGraphicsState();
            $stream->rectangle($x, $outerPdfYClip, $width, $height);
            $stream->rectangle($padX, $padPdfYClip, $padWidth, $padHeight);
            $stream->clipEvenOdd();
            $stream->endPath();
        }
        if ($needBgImageProps) {
            // Per CSS 2.1 §14.2.1, when fewer values are supplied than
            // images the values cycle. Split each property into a comma
            // list and index modulo its length.
            $sizeList = $this->extractCommaList($box->style->get('background-size'));
            $positionList = $this->extractCommaList($box->style->get('background-position'));
            $repeatList = $this->extractCommaList($box->style->get('background-repeat'));
            $originRect = $this->backgroundOriginRect(
                $box,
                $this->resolveBackgroundOrigin($box),
            );
            // CSS Backgrounds 3 §3.10 — first-listed image is topmost;
            // walk the layer list in reverse so the topmost ends up
            // painted last.
            $count = count($layers);
            for ($i = $count - 1; $i >= 0; $i--) {
                $layer = $layers[$i];
                $sizeValue = $sizeList === [] ? null : $sizeList[$i % count($sizeList)];
                $positionValue = $positionList === [] ? null : $positionList[$i % count($positionList)];
                $repeatValue = $repeatList === [] ? null : $repeatList[$i % count($repeatList)];
                if ($layer instanceof \Phpdftk\Css\Value\Url) {
                    $this->paintBackgroundImage(
                        $layer,
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
                } elseif ($layer instanceof \Phpdftk\Css\Value\LinearGradient) {
                    if ($this->isDefaultGradientSize($sizeValue)) {
                        $this->paintLinearGradient($layer, $stream, $x, $top, $width, $height);
                    } else {
                        // Non-default `background-size` tiles the gradient
                        // across the box per `background-repeat` (no-repeat
                        // reduces to one positioned tile).
                        $this->paintTiledLinearGradient(
                            $layer,
                            $stream,
                            $x,
                            $top,
                            $width,
                            $height,
                            $originRect,
                            $sizeValue,
                            $positionValue,
                            $repeatValue,
                        );
                    }
                } elseif ($layer instanceof \Phpdftk\Css\Value\RadialGradient) {
                    // A non-default `background-size` tiles the gradient into
                    // cells; we tile linear gradients (above) but not radial
                    // yet — a radial with translucent stops (the common tiled
                    // case) still floods rather than fades, so painting the
                    // tiled grid would unmask worse than skipping. Paint the
                    // single box-filling gradient only at the default size.
                    if ($this->isDefaultGradientSize($sizeValue)) {
                        $this->paintRadialGradient($layer, $stream, $x, $top, $width, $height);
                    }
                } elseif ($layer instanceof \Phpdftk\Css\Value\ConicGradient) {
                    $this->paintConicGradient($layer, $stream, $x, $top, $width, $height);
                } elseif (($imgArg = $this->imageFunctionColorArg($layer)) !== null) {
                    $imgColor = $this->resolveColorWithCurrentColor($imgArg, $box);
                    if ($imgColor instanceof Color) {
                        $this->paintColorImage($stream, $imgColor, $sizeValue, $positionValue, $repeatValue, $x, $top, $width, $height, $originRect);
                    }
                }
            }
        }
        if ($needBorderAreaClip) {
            $stream->restoreGraphicsState();
        }
    }

    /**
     * Flatten a `background-image` value into the list of paintable
     * layers (Url / LinearGradient / RadialGradient). A bare value
     * becomes a single-element list; a comma-separated `ValueList`
     * is expanded. `none` keywords and other unsupported entries
     * are skipped.
     *
     * @return list<\Phpdftk\Css\Value\Url|\Phpdftk\Css\Value\LinearGradient|\Phpdftk\Css\Value\RadialGradient|\Phpdftk\Css\Value\ConicGradient|\Phpdftk\Css\Value\CssFunction>
     */
    private function extractBackgroundLayers(mixed $value): array
    {
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Comma
        ) {
            $layers = [];
            foreach ($value->values as $v) {
                $layer = $this->resolveBackgroundLayer($v);
                if ($layer !== null) {
                    $layers[] = $layer;
                }
            }
            return $layers;
        }
        $layer = $this->resolveBackgroundLayer($value);
        return $layer !== null ? [$layer] : [];
    }

    /**
     * Classify a single `background-image` list entry into the paintable
     * layer value the painter understands, or null when it's not one we
     * render. `image-set()` is unwrapped to its selected option's image
     * (CSS Images 4 §6) so a gradient / url wrapped in `image-set()`
     * paints identically to the bare image.
     *
     * @return \Phpdftk\Css\Value\Url|\Phpdftk\Css\Value\LinearGradient|\Phpdftk\Css\Value\RadialGradient|\Phpdftk\Css\Value\ConicGradient|\Phpdftk\Css\Value\CssFunction|null
     */
    private function resolveBackgroundLayer(mixed $value): ?\Phpdftk\Css\Value\Value
    {
        if ($value instanceof \Phpdftk\Css\Value\ImageSet) {
            $value = $this->selectImageSetOption($value);
        }
        // A bare-string `image-set()` option — `image-set("/img.png" 1x)` —
        // selects a StringValue whose <string> is equivalent to url() per
        // CSS Images 4 §6. Map it to a Url so it paints like the wrapped form.
        // (A StringValue can only reach here via the image-set unwrap above;
        // a top-level `background-image: "string"` is invalid and never yields
        // a bare StringValue layer.)
        if ($value instanceof \Phpdftk\Css\Value\StringValue) {
            $value = new \Phpdftk\Css\Value\Url($value->value);
        }
        if ($value instanceof \Phpdftk\Css\Value\Url
            || $value instanceof \Phpdftk\Css\Value\LinearGradient
            || $value instanceof \Phpdftk\Css\Value\RadialGradient
            || $value instanceof \Phpdftk\Css\Value\ConicGradient
            || ($value instanceof \Phpdftk\Css\Value\CssFunction
                && $this->imageFunctionColorArg($value) !== null)
        ) {
            return $value;
        }
        return null;
    }

    /**
     * Pick the {@see \Phpdftk\Css\Value\ImageSetOption} that best matches
     * the print target (1 device-pixel-per-CSS-pixel). Prefers the entry
     * whose resolution is closest to 1x; a missing resolution counts as
     * 1x. Returns the option's image, or null for an empty set.
     */
    private function selectImageSetOption(\Phpdftk\Css\Value\ImageSet $set): ?\Phpdftk\Css\Value\Value
    {
        // CSS Images 4 §6: before choosing, remove every option that names an
        // unknown/unsupported MIME type in its `type()` hint or carries a
        // non-positive resolution. If none survive, the image-set() resolves
        // to no image (paints nothing) — the function stays valid, so it still
        // wins the cascade over an earlier `background-image`.
        $best = null;
        $bestDelta = null;
        foreach ($set->options as $option) {
            if ($option->mimeType !== null
                && !self::isSupportedImageSetMimeType($option->mimeType)
            ) {
                continue;
            }
            if ($option->resolutionDppx !== null && $option->resolutionDppx <= 0.0) {
                continue;
            }
            $delta = abs(($option->resolutionDppx ?? 1.0) - 1.0);
            if ($bestDelta === null || $delta < $bestDelta) {
                $best = $option;
                $bestDelta = $delta;
            }
        }
        return $best?->image;
    }

    /**
     * Whether an `image-set()` `type()` MIME hint names an image format the
     * renderer can plausibly decode (CSS Images 4 §6 — options with an
     * unknown/unsupported type are removed from the set). The allowlist mirrors
     * the formats {@see \Phpdftk\ImageMetadata} recognises plus common raster
     * aliases; any non-image or invented subtype (e.g. `image/unsupported`) is
     * treated as unsupported.
     */
    private static function isSupportedImageSetMimeType(string $mime): bool
    {
        return in_array(strtolower(trim($mime)), [
            'image/png', 'image/apng', 'image/jpeg', 'image/jpg', 'image/pjpeg',
            'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp', 'image/tiff',
            'image/x-icon', 'image/vnd.microsoft.icon', 'image/avif', 'image/jp2',
        ], true);
    }

    private function imageFunctionColorArg(mixed $value): ?\Phpdftk\Css\Value\Value
    {
        if (!($value instanceof \Phpdftk\Css\Value\CssFunction)
            || strtolower($value->name) !== 'image'
        ) {
            return null;
        }
        foreach ($value->arguments as $arg) {
            if ($arg instanceof \Phpdftk\Css\Value\Url) {
                return null; // url-primary image() — not a solid colour.
            }
            if ($arg instanceof Color) {
                return $arg;
            }
            // `image(currentcolor)` resolves against the element's `color`
            // at paint time (via resolveColorWithCurrentColor).
            if ($arg instanceof Keyword && strtolower($arg->name) === 'currentcolor') {
                return $arg;
            }
        }
        return null;
    }

    /**
     * @param array{x: float, top: float, width: float, height: float} $originRect
     */
    private function paintColorImage(
        ContentStream $stream,
        Color $color,
        ?\Phpdftk\Css\Value\Value $sizeValue,
        ?\Phpdftk\Css\Value\Value $positionValue,
        ?\Phpdftk\Css\Value\Value $repeatValue,
        float $x,
        float $top,
        float $width,
        float $height,
        array $originRect,
    ): void {
        if ($color->a <= 0.0) {
            return;
        }
        $ow = $originRect['width'];
        $oh = $originRect['height'];
        $size = $this->resolveBackgroundSize($sizeValue, '', $ow, $oh);
        $tw = $size['w'];
        $th = $size['h'];
        if ($tw <= 0.0 || $th <= 0.0) {
            return;
        }
        if ($positionValue !== null) {
            $pos = $this->resolveBackgroundPosition($positionValue, $tw, $th, $ow, $oh);
            $offX = $pos['offsetX'];
            $offY = $pos['offsetY'];
        } else {
            $offX = $size['offsetX'];
            $offY = $size['offsetY'];
        }
        $repeat = $this->repeatAxes($repeatValue);
        $rx = $repeat['x'] ? $x : $originRect['x'] + $offX;
        $rw = $repeat['x'] ? $width : $tw;
        $ry = $repeat['y'] ? $top : $originRect['top'] + $offY;
        $rh = $repeat['y'] ? $height : $th;
        $stream->saveGraphicsState();
        $stream->rectangle($x, $this->pageHeight - $top - $height, $width, $height);
        $stream->clip();
        $stream->endPath();
        $this->emitRect($stream, $rx, $ry, $rw, $rh, fill: $color);
        $stream->restoreGraphicsState();
    }

    /**
     * Split a CSS property value into a list of per-layer values. A
     * comma `ValueList` is exploded into its components; any other
     * value is wrapped in a single-element list. Null inputs return
     * an empty list so the caller can detect "no value supplied".
     *
     * @return list<\Phpdftk\Css\Value\Value>
     */
    private function extractCommaList(mixed $value): array
    {
        if (!$value instanceof \Phpdftk\Css\Value\Value) {
            return [];
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Comma
        ) {
            return array_values($value->values);
        }
        return [$value];
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
    /**
     * CSS Images 4 §3.5 — paint a `conic-gradient()` over the box rect.
     * PDF has no angular shading, so this routes through a function-based
     * ShadingType-1 whose PostScript calculator maps each point to its
     * sweep angle and interpolates the stops
     * ({@see \Phpdftk\Pdf\Writer\PdfDoc::addConicShadingStops()}).
     */
    private function paintConicGradient(
        \Phpdftk\Css\Value\ConicGradient $gradient,
        ContentStream $stream,
        float $x,
        float $top,
        float $width,
        float $height,
    ): void {
        if ($gradient->stops === []) {
            return;
        }
        if (count($gradient->stops) === 1) {
            $this->fillGradientSolidStop($gradient->stops[0], $stream, $x, $top, $width, $height);
            return;
        }
        if ($this->writer === null || $this->page === null) {
            return;
        }
        if ($width <= 0.0 || $height <= 0.0) {
            return;
        }
        $pdfY = $this->pageHeight - $top - $height;
        // Centre: `at <position>` fractions (default 50% 50%). centerY is
        // measured from the CSS top, so flip into the PDF y-up box.
        $cx = $x + ($gradient->centerX ?? 0.5) * $width;
        $cy = $pdfY + ($height - ($gradient->centerY ?? 0.5) * $height);
        // Conic stop positions are angular percentages of the full sweep,
        // so they resolve to [0,1] offsets directly (length line = 1).
        $stopList = $this->resolveGradientStops($gradient->stops, 1.0);
        if (count($stopList) < 2) {
            return;
        }
        try {
            $doc = \Phpdftk\Pdf\Writer\PdfDoc::wrap($this->writer);
            $pattern = $doc->addConicShadingStops(
                new \Phpdftk\Geometry\Point($cx, $cy),
                $gradient->fromAngleDeg,
                $stopList,
                [$x, $x + $width, $pdfY, $pdfY + $height],
                $gradient->repeating,
            );
        } catch (\Throwable) {
            return;
        }
        $patternName = $this->page->useGradient($pattern);
        $stream->saveGraphicsState();
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->clip();
        $stream->endPath();
        $stream->setFillColorSpace('Pattern');
        $stream->setFillColor($patternName);
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->fill();
        $stream->restoreGraphicsState();
    }

    private function paintRadialGradient(
        \Phpdftk\Css\Value\RadialGradient $gradient,
        ContentStream $stream,
        float $x,
        float $top,
        float $width,
        float $height,
    ): void {
        if ($gradient->stops === []) {
            return;
        }
        // CSS Images 3 §3.5.1 — single-stop gradient is a solid fill
        // (no PDF writer needed, so runs before the shading writer guard).
        if (count($gradient->stops) === 1) {
            $this->fillGradientSolidStop($gradient->stops[0], $stream, $x, $top, $width, $height);
            return;
        }
        if ($this->writer === null || $this->page === null) {
            return;
        }
        if ($width <= 0.0 || $height <= 0.0) {
            return;
        }
        $pdfY = $this->pageHeight - $top - $height;
        // Centre: default to the box centre when no `at <position>` is
        // supplied. Author-supplied length values resolve relative to
        // the box's content rect. centerY is measured from the CSS top,
        // so flip into the PDF y-up box.
        $cx = $x + ($gradient->centerX !== null ? $gradient->centerX->value : $width / 2);
        $cy = $pdfY + ($height - ($gradient->centerY !== null ? $gradient->centerY->value : $height / 2));
        // Radii: prefer author lengths, otherwise default to the box
        // half-extent per axis. PDF's ShadingType-3 primitive is circular,
        // so we build a circle of radius r = max(rx, ry) and, for ellipses
        // (rx != ry), scale pattern space via the pattern /Matrix.
        //
        // A single authored `<length>` (`radial-gradient(25px …)`) is a
        // *circle* of that radius per CSS Images 3 §3.5.1 — an ellipse
        // needs two radii — so mirror sizeX onto the missing sizeY rather
        // than falling back to the box half-height (which would stretch
        // the circle into a tall ellipse).
        $rx = $gradient->sizeX !== null ? $gradient->sizeX->value : $width / 2;
        if ($gradient->sizeY !== null) {
            $ry = $gradient->sizeY->value;
        } elseif ($gradient->sizeX !== null) {
            $ry = $rx;
        } else {
            $ry = $height / 2;
        }
        if ($rx <= 0.0 || $ry <= 0.0) {
            return;
        }
        $r = max($rx, $ry);
        // For radial, the gradient line is from centre to the ending
        // shape — its length is the outer radius (the larger axis).
        $stopList = $this->resolveGradientStops($gradient->stops, $r, $gradient->interpolationSpace, $gradient->hueInterpolation);
        // Beyond the ending shape, CSS Images 3 §3.5.1 paints the last
        // stop's colour outward to the box edge. Extend the shading only
        // when that last stop is opaque: a translucent final stop (e.g.
        // `…, transparent`) must fade OUT rather than flood the box with
        // the stop's RGB — and PDF colour shadings carry no alpha, so
        // extending it would paint opaque black. Leaving extend off there
        // stops painting past the outer circle, which reads as the
        // intended transparency. (Full per-stop alpha is a later soft-mask
        // pass — see the note below.)
        $lastStop = $gradient->stops[count($gradient->stops) - 1];
        $extend = $lastStop->color->a >= 1.0;
        // Build the concentric-circle shading in ABSOLUTE page coordinates
        // (inner radius 0, outer r at the box centre) with an identity
        // pattern matrix. A shading pattern anchors via its own /Matrix
        // relative to the page default coordinate system and IGNORES the
        // current CTM, so — like the linear path — we must place the
        // shading at real page coordinates rather than relying on
        // `concatMatrix`. (The old CTM-based placement left the shading at
        // the page origin, painting nothing.)
        try {
            $doc = \Phpdftk\Pdf\Writer\PdfDoc::wrap($this->writer);
            $pattern = $doc->addRadialGradientStops(
                new \Phpdftk\Geometry\Point($cx, $cy),
                0.0,
                new \Phpdftk\Geometry\Point($cx, $cy),
                $r,
                $stopList,
                $extend,
            );
        } catch (\Throwable) {
            return;
        }
        // Elliptical ending shape: scale pattern space about the centre so
        // the radius-r circle becomes an rx × ry ellipse. Scaling P about
        // C by s is P' = s·P + C(1−s); the pattern /Matrix carries that.
        if ($rx !== $ry) {
            $sx = $rx / $r;
            $sy = $ry / $r;
            $pattern->matrix = new \Phpdftk\Pdf\Core\PdfArray([
                new \Phpdftk\Pdf\Core\PdfNumber($sx),
                new \Phpdftk\Pdf\Core\PdfNumber(0.0),
                new \Phpdftk\Pdf\Core\PdfNumber(0.0),
                new \Phpdftk\Pdf\Core\PdfNumber($sy),
                new \Phpdftk\Pdf\Core\PdfNumber($cx - $sx * $cx),
                new \Phpdftk\Pdf\Core\PdfNumber($cy - $sy * $cy),
            ]);
        }
        // NOTE: per-stop alpha is not yet honoured for radial gradients.
        // A Luminosity soft mask analogous to the linear path (see
        // {@see paintLinearGradient()}) would reuse the same absolute
        // anchoring; the writer primitive
        // ({@see \Phpdftk\Pdf\Writer\PdfDoc::addRadialAlphaShading()})
        // exists for when that is wired up.
        $patternName = $this->page->useGradient($pattern);
        $stream->saveGraphicsState();
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->clip();
        $stream->endPath();
        $stream->setFillColorSpace('Pattern');
        $stream->setFillColor($patternName);
        $stream->rectangle($x, $pdfY, $width, $height);
        $stream->fill();
        $stream->restoreGraphicsState();
    }

    /**
     * CSS Images 3 §3.5.1 — paint a single-colour-stop gradient as a
     * solid rectangle fill of that stop's colour. `$top` / `$height` are
     * CSS coordinates (y down); flip to PDF space for the rectangle.
     */
    private function fillGradientSolidStop(
        \Phpdftk\Css\Value\GradientStop $stop,
        ContentStream $stream,
        float $x,
        float $top,
        float $width,
        float $height,
    ): void {
        if ($width <= 0.0 || $height <= 0.0) {
            return;
        }
        $color = $stop->color;
        $stream->saveGraphicsState();
        $stream->setFillColorRGB($color->r, $color->g, $color->b);
        $stream->rectangle($x, $this->pageHeight - $top - $height, $width, $height);
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
     * @param float $gradientLineLength Length of the gradient line in
     *   user units. Used to convert `<length>` stop positions to
     *   fractional [0, 1] offsets. Pass 0 to skip length-position
     *   resolution (degrades length-positioned stops to "no authored
     *   position" — the spec's interpolation algorithm fills them in).
     * @return list<array{offset: float, rgb: array{float, float, float}, alpha: float}>
     */
    private function resolveGradientStops(
        array $stops,
        float $gradientLineLength = 0.0,
        ?ColorSpace $interpSpace = null,
        ?HueInterpolation $hueInterp = null,
    ): array {
        $count = count($stops);
        if ($count === 0) {
            return [];
        }
        // Step 1: pull positions where authored. `<percentage>` →
        // [0,1] fraction directly. `<length>` divides by the gradient
        // line length to get a fraction; when the line length is
        // unknown (0), length-positioned stops fall through to the
        // interpolation step like unset positions.
        $offsets = array_fill(0, $count, null);
        foreach ($stops as $i => $s) {
            if ($s->position instanceof \Phpdftk\Css\Value\Percentage) {
                $offsets[$i] = max(0.0, min(1.0, $s->position->value / 100.0));
            } elseif ($s->position instanceof \Phpdftk\Css\Value\Length && $gradientLineLength > 0.0) {
                $offsets[$i] = max(0.0, min(1.0, $s->position->value / $gradientLineLength));
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
        // Step 5: build entries carrying the authored Colour + resolved
        // offset (the colour is converted to sRGB — or resampled in the
        // interpolation space — in step 7).
        $entries = [];
        foreach ($stops as $i => $s) {
            $entries[] = ['offset' => (float) $offsets[$i], 'color' => $s->color];
        }
        // Step 6: pad the ends so the stop list always spans [0, 1] with the
        // terminal colours held flat. CSS Images 3 §3.5.1 — before the first
        // stop the gradient is the first colour, after the last stop it is
        // the last colour. The PDF stitching function assumes its input runs
        // first-stop → last-stop, so a last stop authored at e.g. 70% (`…,
        // green 0` clamped up, or `…, green 70%`) would otherwise stretch
        // the final segment across the remaining 30% instead of holding a
        // solid band. Anchoring synthetic end stops keeps the colour flat.
        if ($entries[0]['offset'] > 0.0) {
            array_unshift($entries, ['offset' => 0.0, 'color' => $entries[0]['color']]);
        }
        $lastEntry = $entries[count($entries) - 1];
        if ($lastEntry['offset'] < 1.0) {
            $entries[] = ['offset' => 1.0, 'color' => $lastEntry['color']];
        }
        // Step 7: flatten to {offset, rgb, alpha}, resampling in the
        // interpolation space when the gradient names one.
        return $this->flattenGradientStops($entries, $interpSpace, $hueInterp);
    }

    /**
     * Flatten resolved stop entries to the `{offset, rgb, alpha}` tuples the
     * PDF writer consumes. Each colour is converted to sRGB. When `$space`
     * is an explicit interpolation space PDF cannot express natively (i.e.
     * anything but sRGB), each segment is densely resampled via
     * {@see ColorConverter::interpolate()} so the shading's linear DeviceRGB
     * interpolation between samples tracks the true in-space curve
     * (CSS Color 4 §12 / CSS Images 4 §3.1). Otherwise stops are emitted
     * verbatim and the shading interpolates them linearly — the default.
     *
     * @param  list<array{offset: float, color: Color}> $entries
     * @return list<array{offset: float, rgb: array{float, float, float}, alpha: float}>
     */
    private function flattenGradientStops(array $entries, ?ColorSpace $space, ?HueInterpolation $hue): array
    {
        if ($space === null || !$this->interpolationSpaceNeedsResample($space)) {
            $out = [];
            foreach ($entries as $e) {
                $c = ColorConverter::toSrgb($e['color']);
                $out[] = ['offset' => $e['offset'], 'rgb' => [$c->r, $c->g, $c->b], 'alpha' => $c->a];
            }
            return $out;
        }
        $out = [];
        $n = count($entries);
        for ($k = 0; $k < $n - 1; $k++) {
            $a = $entries[$k];
            $b = $entries[$k + 1];
            if ($k === 0) {
                $ca = ColorConverter::toSrgb($a['color']);
                $out[] = ['offset' => $a['offset'], 'rgb' => [$ca->r, $ca->g, $ca->b], 'alpha' => $ca->a];
            }
            $span = $b['offset'] - $a['offset'];
            if ($span > 1e-9) {
                $steps = $this->gradientSampleSteps($space, $hue);
                for ($m = 1; $m < $steps; $m++) {
                    $t = $m / $steps;
                    $ci = ColorConverter::interpolate($a['color'], $b['color'], $t, $space, $hue);
                    $out[] = ['offset' => $a['offset'] + $span * $t, 'rgb' => [$ci->r, $ci->g, $ci->b], 'alpha' => $ci->a];
                }
            }
            $cb = ColorConverter::toSrgb($b['color']);
            $out[] = ['offset' => $b['offset'], 'rgb' => [$cb->r, $cb->g, $cb->b], 'alpha' => $cb->a];
        }
        return $out;
    }

    /**
     * Whether interpolating in `$space` requires pre-sampling. sRGB (and any
     * space without a forward conversion) matches PDF's native DeviceRGB
     * interpolation, so no resampling is needed.
     */
    private function interpolationSpaceNeedsResample(ColorSpace $space): bool
    {
        return match ($space) {
            ColorSpace::OKLab, ColorSpace::OKLCH, ColorSpace::Lab, ColorSpace::Lch,
            ColorSpace::HSL, ColorSpace::HWB, ColorSpace::sRGBLinear,
            ColorSpace::XYZ, ColorSpace::XYZD65, ColorSpace::DisplayP3 => true,
            default => false,
        };
    }

    /**
     * Samples per gradient segment when resampling in `$space`. Polar spaces
     * (hue channel) get more samples — and `longer`/`increasing`/`decreasing`
     * hue can traverse up to a full turn — so the piecewise-linear sRGB
     * approximation stays smooth.
     */
    private function gradientSampleSteps(ColorSpace $space, ?HueInterpolation $hue): int
    {
        $isPolar = match ($space) {
            ColorSpace::OKLCH, ColorSpace::Lch, ColorSpace::HSL, ColorSpace::HWB => true,
            default => false,
        };
        if (!$isPolar) {
            return 16;
        }
        return $hue === HueInterpolation::Longer
            || $hue === HueInterpolation::Increasing
            || $hue === HueInterpolation::Decreasing ? 64 : 32;
    }

    /**
     * True when any stop of a gradient carries alpha < 1 — the case a
     * plain PDF colour shading cannot express (shadings have no alpha
     * channel). Such gradients need a Luminosity soft mask built from
     * the per-stop alpha (see {@see applyGradientAlphaMask()}).
     *
     * @param list<\Phpdftk\Css\Value\GradientStop> $stops
     */
    private function gradientHasAlpha(array $stops): bool
    {
        foreach ($stops as $s) {
            if ($s->color->a < 1.0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Map a resolved stop list (offset + rgb + alpha) to the gray-stop
     * list {@see \Phpdftk\Pdf\Writer\PdfDoc::addLinearAlphaShading()}
     * consumes: gray = alpha, so the Luminosity mask is opaque where
     * the gradient is opaque and transparent where it is transparent.
     *
     * @param list<array{offset: float, rgb: array{float, float, float}, alpha: float}> $stopList
     * @return list<array{offset: float, gray: float}>
     */
    private function alphaStopsFrom(array $stopList): array
    {
        $out = [];
        foreach ($stopList as $s) {
            $out[] = ['offset' => $s['offset'], 'gray' => $s['alpha']];
        }
        return $out;
    }

    /**
     * Build a Luminosity soft-mask ExtGState carrying a gradient's
     * per-stop alpha and return its resource name (or null when the
     * mask can't be built). `$registerShading` registers the DeviceGray
     * alpha shading (linear or radial) on the PdfDoc and returns its
     * object number; the mask group paints it with `sh`, clipped to the
     * clip rect, in absolute page coordinates — a soft-mask group
     * inherits the CTM in effect when its `gs` is set, and the painter
     * paints gradients under an identity CTM. Emit `/<name> gs` inside
     * the gradient's save/restore scope before the pattern fill so the
     * alpha modulates the colour shading (ISO 32000-2 §11.6.5.2).
     *
     * @param \Closure(\Phpdftk\Pdf\Writer\PdfDoc): int $registerShading
     */
    private function buildGradientAlphaMaskState(
        \Phpdftk\Pdf\Writer\PdfDoc $doc,
        \Closure $registerShading,
        float $clipX,
        float $clipPdfY,
        float $clipWidth,
        float $clipHeight,
    ): ?string {
        if ($this->page === null || $clipWidth <= 0.0 || $clipHeight <= 0.0) {
            return null;
        }
        try {
            $shadingObjNum = $registerShading($doc);
            $bbox = new \Phpdftk\Geometry\Rectangle($clipX, $clipPdfY, $clipWidth, $clipHeight);
            $group = $doc->createTransparencyGroup(
                $bbox,
                function (
                    ContentStream $cs,
                    \Phpdftk\Pdf\Core\Content\Resources $res,
                ) use ($shadingObjNum, $clipX, $clipPdfY, $clipWidth, $clipHeight): void {
                    $res->shading['Sh0'] = new \Phpdftk\Pdf\Core\PdfReference($shadingObjNum);
                    $cs->rectangle($clipX, $clipPdfY, $clipWidth, $clipHeight);
                    $cs->clip();
                    $cs->endPath();
                    $cs->paintShading('Sh0');
                },
            );
            return $this->page->ensureSoftMaskState($group, 'Luminosity');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the stop list of a `repeating-linear-gradient` to one
     * cycle of in-cycle offsets ∈ [0, 1], the cycle length in absolute
     * pixels along the gradient line, and the first stop's absolute
     * pixel position. Returns `null` when the cycle is degenerate
     * (zero-length or fewer than two stops) — callers fall through to
     * the non-repeating path.
     *
     * Mirrors the position-resolution algorithm in
     * {@see resolveGradientStops()} but keeps positions in absolute
     * px (length stops can exceed the gradient line length when an
     * author writes `… 100px` on a 50-px ray) rather than clamping to
     * [0, 1].
     *
     * @param  list<\Phpdftk\Css\Value\GradientStop> $stops
     * @return array{list<array{offset: float, rgb: array{float, float, float}}>, float, float}|null
     */
    private function resolveRepeatingLinearCycle(array $stops, float $gradientLineLength): ?array
    {
        $count = count($stops);
        if ($count < 2) {
            return null;
        }
        $positions = array_fill(0, $count, null);
        foreach ($stops as $i => $s) {
            if ($s->position instanceof \Phpdftk\Css\Value\Length) {
                $positions[$i] = $s->position->value;
            } elseif ($s->position instanceof \Phpdftk\Css\Value\Percentage) {
                $positions[$i] = $s->position->value / 100.0 * $gradientLineLength;
            }
        }
        if ($positions[0] === null) {
            $positions[0] = 0.0;
        }
        if ($positions[$count - 1] === null) {
            $positions[$count - 1] = $gradientLineLength;
        }
        // Monotonic clamp — each explicit position must be ≥ prior.
        $prev = -INF;
        foreach ($positions as $i => $p) {
            if ($p !== null) {
                $positions[$i] = max($p, $prev);
                $prev = $positions[$i];
            }
        }
        // Interpolate runs of unset positions between anchored stops.
        $i = 0;
        while ($i < $count) {
            if ($positions[$i] !== null) {
                $i++;
                continue;
            }
            $start = $i - 1;
            $end = $i;
            while ($end < $count && $positions[$end] === null) {
                $end++;
            }
            $span = $positions[$end] - $positions[$start];
            $gap = $end - $start;
            for ($j = $start + 1; $j < $end; $j++) {
                $positions[$j] = $positions[$start] + $span * ($j - $start) / $gap;
            }
            $i = $end;
        }
        $firstPos = (float) $positions[0];
        $lastPos = (float) $positions[$count - 1];
        $cycleLength = $lastPos - $firstPos;
        if ($cycleLength <= 0.0) {
            return null;
        }
        $inCycle = [];
        foreach ($stops as $i => $s) {
            $inCycle[] = [
                'offset' => ((float) $positions[$i] - $firstPos) / $cycleLength,
                'rgb' => [$s->color->r, $s->color->g, $s->color->b],
            ];
        }
        return [$inCycle, $cycleLength, $firstPos];
    }

    /**
     * Extend the gradient axis to cover the clip rect's projection onto
     * the gradient line in whole cycles, and emit a stop list that
     * replicates the in-cycle stops once per cycle across the extended
     * [0, 1] domain.
     *
     * @param  array{list<array{offset: float, rgb: array{float, float, float}}>, float, float} $cycle
     * @return array{float, float, float, float, list<array{offset: float, rgb: array{float, float, float}}>}
     */
    private function buildRepeatingLinearAxis(
        array $cycle,
        float $startPdfX,
        float $startPdfY,
        float $dx,
        float $dy,
        float $clipX,
        float $clipPdfY,
        float $clipWidth,
        float $clipHeight,
    ): array {
        [$inCycleStops, $cycleLength, $firstPos] = $cycle;
        $tMin = INF;
        $tMax = -INF;
        foreach (
            [
                [$clipX,             $clipPdfY],
                [$clipX + $clipWidth, $clipPdfY],
                [$clipX,             $clipPdfY + $clipHeight],
                [$clipX + $clipWidth, $clipPdfY + $clipHeight],
            ] as [$px, $py]
        ) {
            $t = ($px - $startPdfX) * $dx + ($py - $startPdfY) * $dy;
            if ($t < $tMin) {
                $tMin = $t;
            }
            if ($t > $tMax) {
                $tMax = $t;
            }
        }
        $kMin = (int) floor(($tMin - $firstPos) / $cycleLength);
        $kMax = (int) ceil(($tMax - $firstPos) / $cycleLength) - 1;
        if ($kMax < $kMin) {
            $kMax = $kMin;
        }
        // Defensive cap. A clip-extent / cycle ratio over 500 means
        // either a wildly short cycle or a huge clip rect; either way
        // the result is visually indistinguishable past that count and
        // not worth the pattern bytes.
        $maxCycles = 500;
        $cycles = $kMax - $kMin + 1;
        if ($cycles > $maxCycles) {
            $kMax = $kMin + $maxCycles - 1;
            $cycles = $maxCycles;
        }
        $extStartPos = $firstPos + $kMin * $cycleLength;
        $extEndPos = $firstPos + ($kMax + 1) * $cycleLength;
        $extStartX = $startPdfX + $extStartPos * $dx;
        $extStartY = $startPdfY + $extStartPos * $dy;
        $extEndX = $startPdfX + $extEndPos * $dx;
        $extEndY = $startPdfY + $extEndPos * $dy;
        $stopList = [];
        for ($k = 0; $k < $cycles; $k++) {
            foreach ($inCycleStops as $s) {
                $stopList[] = [
                    'offset' => ($k + $s['offset']) / $cycles,
                    'rgb' => $s['rgb'],
                ];
            }
        }
        return [$extStartX, $extStartY, $extEndX, $extEndY, $stopList];
    }

    /**
     * Paint a CSS `linear-gradient(<angle>|to <side>, <stops>)` as the
     * box's background. Phase-1 simplification: only the first and last
     * stop colours are honoured (PDF's basic shading dictionary is
     * two-stop). The gradient line orientation comes from the CSS angle
     * (CSS direction: 0deg = upward, 90deg = rightward, 180deg = down,
     * 270deg = leftward; angles increase clockwise).
     */
    /**
     * Paint a CSS `linear-gradient(...)` as a box background.
     *
     * The (tileX, tileTop, tileWidth, tileHeight) rect is the
     * **gradient's positioning + sizing area** — gradient line and
     * stop offsets resolve against it (CSS Images 3 §3.1, §3.5.1).
     * `clipRect`, when supplied, scopes the actual paint to a
     * different area (e.g. the background-clip rect when bg-size
     * specifies an explicit tile smaller than the box). Defaults to
     * the tile rect so callers that pass only one rect get the
     * previous behaviour.
     *
     * @param array{0: float, 1: float, 2: float, 3: float}|null $clipRect
     *   Layout-space rect [x, top, width, height]. Null = same as tile.
     */
    private function paintLinearGradient(
        \Phpdftk\Css\Value\LinearGradient $gradient,
        ContentStream $stream,
        float $tileX,
        float $tileTop,
        float $tileWidth,
        float $tileHeight,
        ?array $clipRect = null,
    ): void {
        if ($gradient->stops === []) {
            return;
        }
        if ($tileWidth <= 0.0 || $tileHeight <= 0.0) {
            return;
        }
        [$clipX, $clipTop, $clipWidth, $clipHeight] = $clipRect
            ?? [$tileX, $tileTop, $tileWidth, $tileHeight];
        // CSS Images 3 §3.5.1 — a gradient with a single colour-stop
        // renders as a solid fill of that colour. PDF's ShadingType-2/3
        // stitching function needs ≥2 stops, so short-circuit to a plain
        // rectangle fill (the shading path would otherwise throw and paint
        // nothing, exposing the element's fallback background). This needs
        // no PDF writer, so it runs before the shading-only writer guard.
        if (count($gradient->stops) === 1) {
            $this->fillGradientSolidStop(
                $gradient->stops[0],
                $stream,
                $clipX,
                $clipTop,
                $clipWidth,
                $clipHeight,
            );
            return;
        }
        if ($this->writer === null) {
            return;
        }
        $tilePdfY = $this->pageHeight - $tileTop - $tileHeight;
        $clipPdfY = $this->pageHeight - $clipTop - $clipHeight;
        // CSS angle convention: 0deg points up, increases clockwise. The
        // gradient line passes through the centre of the *tile*. Compute
        // its start and end points on the tile's edge per CSS Images 3 §3.1.
        // A `to <corner>` direction's angle depends on the tile's aspect
        // ratio (the gradient line is perpendicular to the diagonal joining
        // the other two corners), so resolve it here where the tile size
        // is known rather than using the parser's square-box fallback.
        $rawAngle = $gradient->corner !== null
            ? $gradient->corner->angleFor($tileWidth, $tileHeight)
            : $gradient->angleDeg;
        $angle = fmod($rawAngle, 360.0);
        if ($angle < 0.0) {
            $angle += 360.0;
        }
        $rad = deg2rad($angle);
        $cx = $tileX + $tileWidth / 2;
        $cy = $tilePdfY + $tileHeight / 2;
        // Gradient line half-length so the endpoints sit on the tile
        // boundary corners (CSS spec): l/2 = |W sin θ| + |H cos θ| / 2
        $sin = sin($rad);
        $cos = cos($rad);
        $halfLen = (abs($tileWidth * $sin) + abs($tileHeight * $cos)) / 2;
        // Full line length is twice the half — what `<length>` stops
        // resolve against (CSS Images 3 §3.5.1).
        $lineLength = $halfLen * 2;
        // The CSS convention rotates the gradient line such that 0deg
        // points UP (towards the box top). In PDF space the y-axis
        // grows upward already (after our flip), so "up" is +y.
        $dx = $sin;
        $dy = $cos;
        $startPdfX = $cx - $dx * $halfLen;
        $startPdfY = $cy - $dy * $halfLen;
        $endPdfX = $cx + $dx * $halfLen;
        $endPdfY = $cy + $dy * $halfLen;
        // CSS Images 4 §6.4 — `repeating-linear-gradient` replays the
        // stop list at `(lastStopPos - firstStopPos)` intervals along
        // the gradient ray, infinitely. We approximate that by
        // extending the axis to cover the clip projection in whole
        // cycles and feeding the shading a stop list that replicates
        // the in-cycle stops once per cycle.
        $stopList = null;
        if ($gradient->repeating) {
            $cycle = $this->resolveRepeatingLinearCycle($gradient->stops, $lineLength);
            if ($cycle !== null) {
                $extended = $this->buildRepeatingLinearAxis(
                    $cycle,
                    $startPdfX,
                    $startPdfY,
                    $dx,
                    $dy,
                    $clipX,
                    $clipPdfY,
                    $clipWidth,
                    $clipHeight,
                );
                [$startPdfX, $startPdfY, $endPdfX, $endPdfY, $stopList] = $extended;
            }
        }
        $stopList ??= $this->resolveGradientStops($gradient->stops, $lineLength, $gradient->interpolationSpace, $gradient->hueInterpolation);
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
        // CSS gradient stops can carry alpha (`rgba(c, 0)` → `rgba(c, 1)`),
        // but a PDF colour shading has no alpha channel. When any stop is
        // translucent, build a Luminosity soft mask from the per-stop
        // alpha so the fill fades with opacity instead of rendering solid.
        $maskGsName = null;
        if ($this->gradientHasAlpha($gradient->stops)) {
            $alphaStops = $this->alphaStopsFrom($stopList);
            $maskGsName = $this->buildGradientAlphaMaskState(
                $doc,
                fn(\Phpdftk\Pdf\Writer\PdfDoc $d): int => $d->addLinearAlphaShading(
                    new \Phpdftk\Geometry\Point($startPdfX, $startPdfY),
                    new \Phpdftk\Geometry\Point($endPdfX, $endPdfY),
                    $alphaStops,
                )->objectNumber,
                $clipX,
                $clipPdfY,
                $clipWidth,
                $clipHeight,
            );
        }
        $stream->saveGraphicsState();
        // Clip first to the bg-clip rect, then fill at the (typically
        // smaller) tile rect. When tile == clip the two are identical
        // and the behaviour is byte-for-byte the same as before.
        $stream->rectangle($clipX, $clipPdfY, $clipWidth, $clipHeight);
        $stream->clip();
        $stream->endPath();
        if ($maskGsName !== null) {
            $stream->setGraphicsState($maskGsName);
        }
        $patternName = $this->page?->useGradient($pattern);
        if ($patternName !== null) {
            $stream->setFillColorSpace('Pattern');
            $stream->setFillColor($patternName);
            $stream->rectangle($tileX, $tilePdfY, $tileWidth, $tileHeight);
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
        $svgDoc = null;
        $name = null;
        if ($this->isSvgSrc($src)) {
            $svgDoc = $this->loadSvgDocument($src);
            if ($svgDoc === null) {
                return;
            }
        } elseif (isset($this->imageNameCache[$src])) {
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
        // CSS Backgrounds 3 §3.6 — `background-position` resolves
        // against the positioning area regardless of whether the tile
        // is smaller or larger than the area; for an oversized tile,
        // the position still anchors its origin (e.g. `0% 0%`
        // keeps the tile's top-left at the area's top-left, even if
        // the tile overflows). Reapply the author position whenever
        // it is supplied so it overrides the size resolver's default
        // centred offset.
        if ($positionValue !== null) {
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
        $this->drawImageTilesInto(
            $stream,
            $svgDoc,
            $name,
            $x,
            $top,
            $width,
            $height,
            $paint,
            $repeatValue,
            $originX,
            $originTop,
            $originWidth,
            $originHeight,
            null,
        );
    }

    /**
     * Tile an image (an SVG doc OR a raster XObject `$name`) across a
     * positioning area, clipped to a rect, per CSS Backgrounds 3 §3.7-3.9
     * (background-size / -position / -repeat already folded into `$paint`
     * and `$repeatValue`). Extracted from {@see paintBackgroundImage} so the
     * mask painter can reuse it to draw a `mask-image: url()` into a soft-mask
     * transparency group. `$registerXObject`, when given, is called with the
     * XObject name before each `Do` so the caller can register the image in
     * the target stream's resources (needed for a transparency group, which
     * has its own resources); the background path passes null (the image is
     * already in the page resources).
     *
     * @param array{w: float, h: float, offsetX: float, offsetY: float} $paint
     * @param \Closure(string): void|null $registerXObject
     */
    private function drawImageTilesInto(
        ContentStream $stream,
        ?\Phpdftk\Svg\SvgDocument $svgDoc,
        ?string $name,
        float $x,
        float $top,
        float $width,
        float $height,
        array $paint,
        ?\Phpdftk\Css\Value\Value $repeatValue,
        float $originX,
        float $originTop,
        float $originWidth,
        float $originHeight,
        ?\Closure $registerXObject = null,
    ): void {
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
        $repeatModes = $this->repeatModes($repeatValue);
        $tileW = $paint['w'];
        $tileH = $paint['h'];
        if ($tileW <= 0.0 || $tileH <= 0.0) {
            $stream->restoreGraphicsState();
            return;
        }
        // CSS Backgrounds 3 §3.7 — `round` per axis scales the tile
        // so a whole number of tiles fits the positioning area. Apply
        // before computing tile offsets so subsequent positioning + the
        // repeat loop see the rescaled tile dims.
        $tileW = $this->roundTileDim($repeatModes['x'], $tileW, $originWidth);
        $tileH = $this->roundTileDim($repeatModes['y'], $tileH, $originHeight);
        $paint['w'] = $tileW;
        $paint['h'] = $tileH;
        // Start positions: shift the anchor backwards by whole tile
        // widths until the leftmost / topmost tile sits at or before
        // the origin box (NOT the clip box — tiles anchor against
        // `background-origin`). With `no-repeat`, no shift happens.
        // Tile iteration bounds. A repeating axis tiles across the whole
        // PAINT/clip rect (which for a propagated root background is the
        // entire canvas, larger than the positioning area), anchored to
        // the origin rect. A non-repeating axis paints a single tile
        // within the origin rect. For a normal box clip == origin, so
        // these reduce to the origin bounds (no behaviour change).
        $startX = $paint['offsetX'];
        $farX = $originWidth;
        if ($repeat['x']) {
            $farX = $x + $width - $originX;
            while ($originX + $startX > $x) {
                $startX -= $tileW;
            }
        }
        $startY = $paint['offsetY'];
        $farY = $originHeight;
        if ($repeat['y']) {
            $farY = $top + $height - $originTop;
            while ($originTop + $startY > $top) {
                $startY -= $tileH;
            }
        }
        $originBottomLayoutY = $originTop + $originHeight;
        $originPdfBottom = $this->pageHeight - $originBottomLayoutY;
        // Precompute the per-axis tile offsets (relative to the origin
        // edge). This unifies repeat / round / no-repeat / space: `space`
        // distributes even gaps rather than butting tiles, the others step
        // continuously.
        $xOffsets = $this->tileOffsets($repeatModes['x'], $repeat['x'], $startX, $farX, $tileW, $paint['offsetX'], $originWidth);
        $yOffsets = $this->tileOffsets($repeatModes['y'], $repeat['y'], $startY, $farY, $tileH, $paint['offsetY'], $originHeight);
        $maxTiles = 4096;
        $tileCount = 0;
        foreach ($yOffsets as $offsetY) {
            foreach ($xOffsets as $offsetX) {
                if ($tileCount >= $maxTiles) {
                    break 2;
                }
                $tileBottomY = $originPdfBottom + ($originHeight - $tileH - $offsetY);
                if ($svgDoc !== null) {
                    // Route the SVG draw through the caller's stream so
                    // it lands INSIDE the bg-clip `q ... clip ... Q`
                    // scope this method opened above. Without this the
                    // page would attach a fresh content stream and the
                    // SVG paint (e.g. a `cover`-overflowed 768×3072
                    // tile) escapes the box clip.
                    $this->svgRenderer()->draw(
                        $svgDoc,
                        $originX + $offsetX,
                        $tileBottomY,
                        $tileW,
                        $tileH,
                        stream: $stream,
                    );
                } else {
                    assert($name !== null);
                    if ($registerXObject !== null) {
                        $registerXObject($name);
                    }
                    $stream->saveGraphicsState();
                    $stream->concatMatrix(
                        $tileW,
                        0.0,
                        0.0,
                        $tileH,
                        $originX + $offsetX,
                        $tileBottomY,
                    );
                    $stream->doXObject($name);
                    $stream->restoreGraphicsState();
                }
                $tileCount++;
            }
        }
        $stream->restoreGraphicsState();
    }

    /**
     * Resolve a CSS `background-repeat` value to a `{x: bool, y: bool}`
     * pair indicating whether each axis should tile (true for all
     * non-`no-repeat` modes; the per-axis `round` / `space` math
     * folds back into the caller via {@see repeatModes}).
     *
     * Phase-1 handles the simple keyword set:
     *   - `repeat` (default) → both axes
     *   - `repeat-x` → x only
     *   - `repeat-y` → y only
     *   - `no-repeat` → neither
     *   - `round` / `space` → both axes (loop-active; the actual
     *     scale-to-fit / distribute-spacing math is layered on top
     *     of the loop)
     *   - two-value form (e.g. `repeat no-repeat`): per-axis keywords
     *
     * @return array{x: bool, y: bool}
     */
    private function repeatAxes(?\Phpdftk\Css\Value\Value $value): array
    {
        $modes = $this->repeatModes($value);
        return [
            'x' => $modes['x'] !== 'no-repeat',
            'y' => $modes['y'] !== 'no-repeat',
        ];
    }

    /**
     * Per-axis `background-repeat` mode (the richer view repeatAxes
     * collapses to bools). Returns one of `repeat` / `repeat-x` (collapsed
     * to a single bool above) / `no-repeat` / `round` / `space` per axis.
     *
     * @return array{x: string, y: string}
     */
    private function repeatModes(?\Phpdftk\Css\Value\Value $value): array
    {
        if ($value === null) {
            return ['x' => 'repeat', 'y' => 'repeat'];
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
                'x' => strtolower($items[0]->name),
                'y' => strtolower($items[1]->name),
            ];
        }
        if ($items[0] instanceof \Phpdftk\Css\Value\Keyword) {
            return match (strtolower($items[0]->name)) {
                'repeat-x' => ['x' => 'repeat', 'y' => 'no-repeat'],
                'repeat-y' => ['x' => 'no-repeat', 'y' => 'repeat'],
                'no-repeat' => ['x' => 'no-repeat', 'y' => 'no-repeat'],
                'round' => ['x' => 'round', 'y' => 'round'],
                'space' => ['x' => 'space', 'y' => 'space'],
                default => ['x' => 'repeat', 'y' => 'repeat'],
            };
        }
        return ['x' => 'repeat', 'y' => 'repeat'];
    }

    /**
     * CSS Backgrounds 3 §3.7 `round` per-axis rescale: scale the
     * tile so a whole number of tiles fits the positioning area,
     * preserving aspect when possible. Returns the rescaled tile
     * size; passthrough when the mode isn't `round` or the natural
     * tile dim is non-positive.
     */
    private function roundTileDim(string $mode, float $tileDim, float $originDim): float
    {
        if ($mode !== 'round' || $tileDim <= 0.0 || $originDim <= 0.0) {
            return $tileDim;
        }
        // CSS spec: number of tiles = round(originDim / tileDim).
        // At least 1 — a tile larger than the box still emits once.
        $n = max(1, (int) round($originDim / $tileDim));
        return $originDim / $n;
    }

    /**
     * Compute the per-axis list of tile offsets (relative to the
     * background-origin edge) for one axis of a repeated background image.
     *
     *   - `no-repeat` → a single tile at the background-position offset.
     *   - `repeat` / `round` → tiles butt together, stepping by `$tileDim`
     *     from the back-shifted `$start` across the paint area to `$far`
     *     (`round` has already rescaled `$tileDim` so a whole number fit).
     *   - `space` (CSS Backgrounds 3 §3.7) → as many whole tiles as fit
     *     without clipping, the remaining space distributed evenly so the
     *     first and last tiles touch the two edges; background-position is
     *     ignored on this axis. When 0 or 1 tile fits, a single tile is
     *     placed at the background-position offset.
     *
     * @return list<float>
     */
    private function tileOffsets(
        string $mode,
        bool $repeats,
        float $start,
        float $far,
        float $tileDim,
        float $positionedOffset,
        float $originDim,
    ): array {
        if (!$repeats) {
            return [$positionedOffset];
        }
        if ($mode === 'space') {
            $n = $tileDim > 0.0 ? (int) floor($originDim / $tileDim + 1e-6) : 0;
            if ($n >= 2) {
                $step = $tileDim + ($originDim - $n * $tileDim) / ($n - 1);
                $offsets = [];
                for ($i = 0; $i < $n; $i++) {
                    $offsets[] = $i * $step;
                }
                return $offsets;
            }
            return [$positionedOffset];
        }
        // repeat / round — continuous butting tiles from the shifted start.
        $offsets = [];
        for ($o = $start; $o < $far; $o += $tileDim) {
            $offsets[] = $o;
            if (count($offsets) >= 4096) {
                break;
            }
        }
        return $offsets === [] ? [$start] : $offsets;
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
        // 50% default applies only when entering the single-keyword
        // branch below: per CSS Backgrounds 3 §3.6, a single keyword
        // (e.g. `top`, `left`) pins one axis and centres the other.
        // The two-value / empty path enters `else` and lets
        // `axisOffsetFromValue` resolve each side from its own value
        // (or its null-default of 0%, the spec's initial value).
        $xPercent = 0.5;
        $yPercent = 0.5;
        $xLength = null;
        $yLength = null;
        // CSS Backgrounds 3 §3.6 — the 3-4 value edge-offset form: each
        // <length-percentage> is an OFFSET FROM a preceding edge keyword
        // (`bottom 10px right 20px` = 10px up from bottom, 20px left from
        // right). Only triggers at 3+ items so the 1-2 value paths stay
        // byte-identical.
        if (count($items) >= 3) {
            [$xLength, $xPercent, $yLength, $yPercent] = $this->resolveEdgeOffsetPosition(
                $items,
                $imageWidth,
                $imageHeight,
                $boxWidth,
                $boxHeight,
            );
            $offsetX = $xLength ?? ($boxWidth - $imageWidth) * $xPercent;
            $offsetY = $yLength ?? ($boxHeight - $imageHeight) * $yPercent;
            return ['offsetX' => $offsetX, 'offsetY' => $offsetY];
        }
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
            // Two-value form. Per CSS Backgrounds 3 §3.6 the components may
            // appear in either axis order: `left`/`right` always bind X and
            // `top`/`bottom` always bind Y, so `top center` and `center left`
            // must be read axis-first, not positionally. Swap when the first
            // component is a vertical keyword or the second is a horizontal
            // keyword; the common `<x> <y>` order stays byte-identical.
            $xItem = $items[0] ?? null;
            $yItem = $items[1] ?? null;
            if ($this->isVerticalPositionKeyword($xItem)
                || $this->isHorizontalPositionKeyword($yItem)
            ) {
                [$xItem, $yItem] = [$yItem, $xItem];
            }
            // CSS Backgrounds 3 §3.6 — when the author specifies exactly
            // ONE value (e.g. `background-position: 25%` or `-0px`), the
            // second value is `center` (50%), not the unspecified initial
            // `0%`. An EMPTY list (count 0) is the unspecified initial and
            // keeps `0% 0%`; a single keyword is handled above.
            $singleValue = count($items) === 1;
            $xAxis = $this->axisOffsetFromValue($xItem, isHorizontal: true);
            if ($xAxis['percent'] !== null) {
                $xPercent = $xAxis['percent'];
            }
            if ($xAxis['length'] !== null) {
                $xLength = $xAxis['length'];
            }
            if ($singleValue) {
                $yPercent = 0.5;
            } else {
                $yAxis = $this->axisOffsetFromValue($yItem, isHorizontal: false);
                if ($yAxis['percent'] !== null) {
                    $yPercent = $yAxis['percent'];
                }
                if ($yAxis['length'] !== null) {
                    $yLength = $yAxis['length'];
                }
            }
        }
        $offsetX = $xLength ?? ($boxWidth - $imageWidth) * $xPercent;
        $offsetY = $yLength ?? ($boxHeight - $imageHeight) * $yPercent;
        return ['offsetX' => $offsetX, 'offsetY' => $offsetY];
    }

    /**
     * CSS Backgrounds 3 §3.6 — resolve the 3-4 value `background-position`
     * edge-offset form into `[xLength, xPercent, yLength, yPercent]` (the
     * same shape {@see resolveBackgroundPosition} folds into an offset).
     * Each edge keyword (`left`/`right`/`top`/`bottom`/`center`) may be
     * followed by a `<length-percentage>` offset measured FROM that edge:
     * a `right`/`bottom` offset counts inward from the far edge. `center`
     * takes no offset and fills whichever axis the other group left open.
     *
     * @param list<\Phpdftk\Css\Value\Value> $items
     * @return array{0: ?float, 1: float, 2: ?float, 3: float}
     */
    private function resolveEdgeOffsetPosition(
        array $items,
        float $imageWidth,
        float $imageHeight,
        float $boxWidth,
        float $boxHeight,
    ): array {
        $xLength = null;
        $yLength = null;
        $xPercent = null;
        $yPercent = null;
        $count = count($items);
        $i = 0;
        while ($i < $count) {
            $item = $items[$i];
            if (!($item instanceof \Phpdftk\Css\Value\Keyword)) {
                $i++;
                continue;
            }
            $kw = strtolower($item->name);
            // Pull an optional trailing offset (not for `center`).
            $offset = null;
            if ($kw !== 'center' && $i + 1 < $count) {
                $next = $items[$i + 1];
                if ($next instanceof \Phpdftk\Css\Value\Length
                    || $next instanceof \Phpdftk\Css\Value\Percentage
                ) {
                    $offset = $next;
                    $i++;
                }
            }
            $i++;
            $far = $kw === 'right' || $kw === 'bottom';
            $isX = $kw === 'left' || $kw === 'right';
            $isY = $kw === 'top' || $kw === 'bottom';
            if ($kw === 'center') {
                if ($xPercent === null && $xLength === null) {
                    $xPercent = 0.5;
                } else {
                    $yPercent = 0.5;
                }
                continue;
            }
            if ($offset instanceof \Phpdftk\Css\Value\Length) {
                if ($isX) {
                    $xLength = $far ? ($boxWidth - $imageWidth) - $offset->value : $offset->value;
                } elseif ($isY) {
                    $yLength = $far ? ($boxHeight - $imageHeight) - $offset->value : $offset->value;
                }
            } elseif ($offset instanceof \Phpdftk\Css\Value\Percentage) {
                $p = $offset->value / 100.0;
                $p = $far ? 1.0 - $p : $p;
                if ($isX) {
                    $xPercent = $p;
                } elseif ($isY) {
                    $yPercent = $p;
                }
            } else {
                // Bare edge keyword: anchor at that edge.
                if ($isX) {
                    $xPercent = $far ? 1.0 : 0.0;
                } elseif ($isY) {
                    $yPercent = $far ? 1.0 : 0.0;
                }
            }
        }
        return [$xLength, $xPercent ?? 0.5, $yLength, $yPercent ?? 0.5];
    }

    /**
     * Classify a single `background-position` axis value into a
     * `{percent?, length?}` pair. Keywords (`top`/`bottom`/`left`/
     * `right`/`center`) become percentages; explicit `<length>` /
     * `<percentage>` values come through as-is.
     *
     * @return array{percent: ?float, length: ?float}
     */
    /**
     * True for the horizontal-axis `background-position` keywords
     * (`left` / `right`), which bind the X axis regardless of order.
     */
    private function isHorizontalPositionKeyword(?\Phpdftk\Css\Value\Value $v): bool
    {
        return $v instanceof \Phpdftk\Css\Value\Keyword
            && in_array(strtolower($v->name), ['left', 'right'], true);
    }

    /**
     * True for the vertical-axis `background-position` keywords
     * (`top` / `bottom`), which bind the Y axis regardless of order.
     */
    private function isVerticalPositionKeyword(?\Phpdftk\Css\Value\Value $v): bool
    {
        return $v instanceof \Phpdftk\Css\Value\Keyword
            && in_array(strtolower($v->name), ['top', 'bottom'], true);
    }

    /**
     * @return array{percent: float|null, length: float|null}
     */
    private function axisOffsetFromValue(
        ?\Phpdftk\Css\Value\Value $value,
        bool $isHorizontal,
    ): array {
        // CSS Backgrounds 3 §3.6 — `background-position` initial value
        // is `0% 0%` (top-left). A missing axis falls back to that
        // initial; the previous default of 50% (centre) misrouted any
        // explicit-tile case (e.g. `background-size: 12px auto`
        // without an explicit position) to centre instead of top-left.
        if ($value === null) {
            return ['percent' => 0.0, 'length' => null];
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
        // Default / unset / `auto`: CSS Backgrounds 3 §3.9 — when both
        // axes are `auto` and the image has intrinsic dimensions, use
        // those dimensions; only fall back to box dims when the image
        // has no intrinsic info.
        $isAuto = $sizeValue === null
            || ($sizeValue instanceof \Phpdftk\Css\Value\Keyword
                && strtolower($sizeValue->name) === 'auto');
        if ($isAuto) {
            // Per CSS Backgrounds 3 §3.9 — both auto and the image
            // has full intrinsic w+h (raster, or SVG with both axes
            // either fixed or derived from viewBox) → use those.
            $intrinsic = $this->intrinsicSize($src);
            if ($intrinsic !== null && $intrinsic[0] > 0 && $intrinsic[1] > 0) {
                return [
                    'w' => (float) $intrinsic[0],
                    'h' => (float) $intrinsic[1],
                    'offsetX' => 0.0,
                    'offsetY' => 0.0,
                ];
            }
            // Partial intrinsic (SVG with one fixed dim + no
            // viewBox): missing axes fall back to the bg-positioning
            // area dimension (CSS Images 3 §5.2 default object size).
            $partial = $this->intrinsicSizePartial($src);
            $w = $partial['w'];
            $h = $partial['h'];
            if ($w !== null || $h !== null) {
                return [
                    'w' => $w ?? $boxWidth,
                    'h' => $h ?? $boxHeight,
                    'offsetX' => 0.0,
                    'offsetY' => 0.0,
                ];
            }
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
            // Extreme viewBox aspect ratios can collapse one axis to
            // zero (CSS Backgrounds 3 §3.9 considers the ratio
            // well-defined, but the derived dimension rounds to 0).
            // `contain` resolves cleanly via INF on the impossible
            // axis — min picks the finite scale and the zero side
            // stays zero — but `cover` would otherwise produce INF
            // dimensions. For cover with a degenerate axis, degrade
            // to stretch so the box is at least covered with finite
            // dimensions.
            if ($keyword === 'cover' && ($natW === 0 || $natH === 0)) {
                return ['w' => $boxWidth, 'h' => $boxHeight, 'offsetX' => 0.0, 'offsetY' => 0.0];
            }
            $scaleW = $natW > 0 ? $boxWidth / $natW : INF;
            $scaleH = $natH > 0 ? $boxHeight / $natH : INF;
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
        // space-separated ValueList. Per CSS Backgrounds 3 §3.9, when
        // one component is `auto`:
        //   • image has intrinsic ratio → derive from the other side
        //   • image has no intrinsic ratio → use 100% of the
        //     corresponding bg-positioning area dimension
        if ($sizeValue instanceof \Phpdftk\Css\Value\ValueList
            && $sizeValue->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $w = $sizeValue->values[0] ?? null;
            $h = $sizeValue->values[1] ?? null;
            $explicitW = $this->backgroundSizeAxisLength($w, $boxWidth);
            $explicitH = $this->backgroundSizeAxisLength($h, $boxHeight);
            [$finalW, $finalH] = $this->resolveAutoSizePair(
                $explicitW,
                $explicitH,
                $src,
                $boxWidth,
                $boxHeight,
            );
            return [
                'w' => $finalW,
                'h' => $finalH,
                'offsetX' => max(0.0, ($boxWidth - $finalW) / 2),
                'offsetY' => max(0.0, ($boxHeight - $finalH) / 2),
            ];
        }
        // Single-value `background-size`: a bare `<length>` or
        // `<percentage>` sets the width; the second axis defaults to
        // `auto` per CSS Backgrounds 3 §3.9 (derive from intrinsic
        // ratio, or fall back to the bg-positioning-area height).
        $singleW = $this->backgroundSizeAxisLength($sizeValue, $boxWidth);
        if ($singleW !== null) {
            [$finalW, $finalH] = $this->resolveAutoSizePair(
                $singleW,
                null,
                $src,
                $boxWidth,
                $boxHeight,
            );
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
     * Resolve one axis of `background-size` from a single value to a
     * concrete pixel length. Lengths come through as-is; percentages
     * resolve against the bg-positioning area axis (CSS Backgrounds 3
     * §3.9). Any other value (including `auto` keywords) returns null
     * so the caller can route to the intrinsic-ratio path.
     */
    private function backgroundSizeAxisLength(
        ?\Phpdftk\Css\Value\Value $value,
        float $axisExtent,
    ): ?float {
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $value->value / 100.0 * $axisExtent;
        }
        return null;
    }

    /**
     * Return true when the `background-size` value resolves to the
     * "fill the box" default. Treated as default:
     *   • null (property unset)
     *   • single keyword `auto` / unknown
     *   • two-value `auto auto`
     * Anything explicit (length, percentage, `cover`, `contain`,
     * `auto <length>`, `<length> auto`) is non-default and the
     * gradient renders at the resolved tile rect.
     */
    private function isDefaultGradientSize(?\Phpdftk\Css\Value\Value $sizeValue): bool
    {
        if ($sizeValue === null) {
            return true;
        }
        if ($sizeValue instanceof \Phpdftk\Css\Value\Keyword) {
            return strtolower($sizeValue->name) === 'auto';
        }
        if ($sizeValue instanceof \Phpdftk\Css\Value\ValueList
            && $sizeValue->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $allAuto = true;
            foreach ($sizeValue->values as $v) {
                $isAutoKw = $v instanceof \Phpdftk\Css\Value\Keyword
                    && strtolower($v->name) === 'auto';
                if (!$isAutoKw) {
                    $allAuto = false;
                    break;
                }
            }
            return $allAuto && $sizeValue->values !== [];
        }
        return false;
    }

    /**
     * Compute the rect a CSS gradient tile occupies given the
     * background-size + background-position values and the
     * background-positioning area (`background-origin` rect).
     *
     * Gradients have no intrinsic size and no intrinsic ratio, so
     * CSS Backgrounds 3 §3.9 reduces to:
     *   • both auto / unset / unknown keyword → 100% × 100%
     *   • cover / contain                     → 100% × 100%
     *   • &lt;length&gt; auto                       → length × 100% height
     *   • auto &lt;length&gt;                       → 100% width × length
     *   • &lt;length&gt; &lt;length&gt;                   → explicit pair
     * Position resolves the tile's offset within the positioning
     * area (CSS Backgrounds 3 §3.6 — keywords / percentages anchor;
     * lengths are direct offsets).
     *
     * Tile a linear gradient across the box when `background-size` makes
     * the tile smaller than the positioning area (CSS Backgrounds 3 §3.9).
     * Mirrors the image tiling loop: resolve the tile size + positioned
     * offset, compute per-axis cell offsets (with `background-repeat`'s
     * repeat / round / space handling via {@see tileOffsets}), and paint
     * the gradient once per cell, each clipped to the box. `no-repeat`
     * reduces to a single positioned tile.
     *
     * @param array{x: float, top: float, width: float, height: float} $originRect
     */
    private function paintTiledLinearGradient(
        \Phpdftk\Css\Value\LinearGradient $gradient,
        ContentStream $stream,
        float $boxX,
        float $boxTop,
        float $boxWidth,
        float $boxHeight,
        array $originRect,
        ?\Phpdftk\Css\Value\Value $sizeValue,
        ?\Phpdftk\Css\Value\Value $positionValue,
        ?\Phpdftk\Css\Value\Value $repeatValue,
    ): void {
        $grid = $this->gradientTileCells($originRect, $boxX, $boxTop, $boxWidth, $boxHeight, $sizeValue, $positionValue, $repeatValue);
        if ($grid === null) {
            // Degenerate or sub-pixel tile — a dense grid of tiny tiles
            // approximates one box-filling gradient (and avoids blowing the
            // tile cap), so paint it once (e.g. `background-size: 0.2px`).
            $this->paintLinearGradient($gradient, $stream, $boxX, $boxTop, $boxWidth, $boxHeight);
            return;
        }
        // A linear-gradient shading is confined to the tile rect it's built
        // for, so paint each cell clipped to the box (the shading carries
        // its own extent).
        $clip = [$boxX, $boxTop, $boxWidth, $boxHeight];
        foreach ($grid['cells'] as $cell) {
            $this->paintLinearGradient($gradient, $stream, $cell['x'], $cell['top'], $grid['tileW'], $grid['tileH'], $clip);
        }
    }

    /**
     * Compute the tile size and per-cell top-left positions for tiling a
     * gradient across the box under `background-size` / `-position` /
     * `-repeat`. Mirrors the image tiling geometry (back-shift on repeating
     * axes, {@see tileOffsets} for repeat / round / space). Returns null
     * when the tile is degenerate.
     *
     * @param  array{x: float, top: float, width: float, height: float} $originRect
     * @return array{tileW: float, tileH: float, cells: list<array{x: float, top: float}>}|null
     */
    private function gradientTileCells(
        array $originRect,
        float $boxX,
        float $boxTop,
        float $boxWidth,
        float $boxHeight,
        ?\Phpdftk\Css\Value\Value $sizeValue,
        ?\Phpdftk\Css\Value\Value $positionValue,
        ?\Phpdftk\Css\Value\Value $repeatValue,
    ): ?array {
        $originX = $originRect['x'];
        $originTop = $originRect['top'];
        $originWidth = $originRect['width'];
        $originHeight = $originRect['height'];
        $repeat = $this->repeatAxes($repeatValue);
        $modes = $this->repeatModes($repeatValue);
        [$tileW, $tileH] = $this->resolveGradientTileSize($sizeValue, $originWidth, $originHeight);
        // `round` rescales the tile so a whole number fits (same as the
        // image path); apply before computing offsets.
        $tileW = $this->roundTileDim($modes['x'], $tileW, $originWidth);
        $tileH = $this->roundTileDim($modes['y'], $tileH, $originHeight);
        // Degenerate or sub-pixel tiles can't be tiled meaningfully (they'd
        // demand thousands of tiles); the caller falls back to a single fill.
        if ($tileW < 1.0 || $tileH < 1.0) {
            return null;
        }
        $offsets = $positionValue === null
            ? ['offsetX' => max(0.0, ($originWidth - $tileW) / 2), 'offsetY' => max(0.0, ($originHeight - $tileH) / 2)]
            : $this->resolveBackgroundPosition($positionValue, $tileW, $tileH, $originWidth, $originHeight);
        // Back-shift the anchor and extend the far bound to the box edge on
        // repeating axes, exactly as paintBackgroundImage does.
        $startX = $offsets['offsetX'];
        $farX = $originWidth;
        if ($repeat['x']) {
            $farX = $boxX + $boxWidth - $originX;
            while ($originX + $startX > $boxX) {
                $startX -= $tileW;
            }
        }
        $startY = $offsets['offsetY'];
        $farY = $originHeight;
        if ($repeat['y']) {
            $farY = $boxTop + $boxHeight - $originTop;
            while ($originTop + $startY > $boxTop) {
                $startY -= $tileH;
            }
        }
        $xOffsets = $this->tileOffsets($modes['x'], $repeat['x'], $startX, $farX, $tileW, $offsets['offsetX'], $originWidth);
        $yOffsets = $this->tileOffsets($modes['y'], $repeat['y'], $startY, $farY, $tileH, $offsets['offsetY'], $originHeight);
        // Gradient tiles each register a shading pattern, heavier than an
        // XObject draw — cap lower than the image loop.
        $cells = [];
        foreach ($yOffsets as $oy) {
            foreach ($xOffsets as $ox) {
                if (count($cells) >= 512) {
                    break 2;
                }
                $cells[] = ['x' => $originX + $ox, 'top' => $originTop + $oy];
            }
        }
        return ['tileW' => $tileW, 'tileH' => $tileH, 'cells' => $cells];
    }

    /**
     * Resolve a CSS `background-size` value to a concrete (w, h)
     * for a gradient (no intrinsic dimensions, no intrinsic ratio).
     * See {@see gradientTileCells} for how the tile is positioned.
     *
     * @return array{0: float, 1: float}
     */
    private function resolveGradientTileSize(
        ?\Phpdftk\Css\Value\Value $sizeValue,
        float $originWidth,
        float $originHeight,
    ): array {
        if ($sizeValue === null) {
            return [$originWidth, $originHeight];
        }
        if ($sizeValue instanceof \Phpdftk\Css\Value\Keyword) {
            $kw = strtolower($sizeValue->name);
            // `cover` / `contain` need an intrinsic ratio to do
            // anything useful; without one they degrade to stretch.
            return [$originWidth, $originHeight];
        }
        if ($sizeValue instanceof \Phpdftk\Css\Value\Length) {
            // Single length sets width; height = auto = 100% of
            // positioning area (no intrinsic ratio for gradients).
            return [$sizeValue->value, $originHeight];
        }
        if ($sizeValue instanceof \Phpdftk\Css\Value\ValueList
            && $sizeValue->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $w = $sizeValue->values[0] ?? null;
            $h = $sizeValue->values[1] ?? null;
            $tileW = $w instanceof \Phpdftk\Css\Value\Length ? $w->value : $originWidth;
            $tileH = $h instanceof \Phpdftk\Css\Value\Length ? $h->value : $originHeight;
            // Percentage tile dims resolve against the positioning area.
            if ($w instanceof \Phpdftk\Css\Value\Percentage) {
                $tileW = $originWidth * ($w->value / 100.0);
            }
            if ($h instanceof \Phpdftk\Css\Value\Percentage) {
                $tileH = $originHeight * ($h->value / 100.0);
            }
            return [$tileW, $tileH];
        }
        return [$originWidth, $originHeight];
    }

    /**
     * Resolve a two-component `background-size` where one or both
     * sides may be `auto`. Implements CSS Backgrounds 3 §3.9 auto
     * resolution: intrinsic ratio derives the missing side; if no
     * ratio, auto resolves to 100% of the positioning area.
     *
     * @return array{0: float, 1: float}
     */
    private function resolveAutoSizePair(
        ?float $explicitW,
        ?float $explicitH,
        string $src,
        float $boxWidth,
        float $boxHeight,
    ): array {
        if ($explicitW !== null && $explicitH !== null) {
            return [$explicitW, $explicitH];
        }
        // Prefer the FULL intrinsic (raster, or SVG with both axes
        // either fixed or derivable from viewBox). Only fall to the
        // partial helper when no complete intrinsic is available —
        // e.g. an SVG with just one fixed axis and no viewBox.
        $intrinsic = $this->intrinsicSize($src);
        $hasFullIntrinsic = $intrinsic !== null && $intrinsic[0] > 0 && $intrinsic[1] > 0;
        if ($hasFullIntrinsic) {
            $natW = (float) $intrinsic[0];
            $natH = (float) $intrinsic[1];
            if ($explicitW === null && $explicitH === null) {
                return [$natW, $natH];
            }
            if ($explicitW !== null) {
                return [$explicitW, $explicitW * ($natH / $natW)];
            }
            assert($explicitH !== null);
            return [$explicitH * ($natW / $natH), $explicitH];
        }
        $partial = $this->intrinsicSizePartial($src);
        $intW = $partial['w'];
        $intH = $partial['h'];
        if ($explicitW === null && $explicitH === null) {
            return [$intW ?? $boxWidth, $intH ?? $boxHeight];
        }
        if ($explicitW !== null) {
            return [$explicitW, $intH ?? $boxHeight];
        }
        assert($explicitH !== null);
        return [$intW ?? $boxWidth, $explicitH];
    }

    /**
     * Partial intrinsic dimensions of an image. Unlike
     * {@see intrinsicSize}, this returns nullable per-axis values
     * plus a `hasRatio` flag so callers (notably the `bg-size: auto`
     * branches of {@see resolveBackgroundSize} / {@see resolveAutoSizePair})
     * can honour SVGs that declare only one of width / height /
     * viewBox per CSS Backgrounds 3 §3.9. For raster images the
     * shape is always full (both axes set, hasRatio derived).
     *
     * @return array{w: ?float, h: ?float, hasRatio: bool}
     */
    private function intrinsicSizePartial(string $src): array
    {
        if ($this->isSvgSrc($src)) {
            $svg = $this->loadSvgDocument($src);
            if ($svg === null) {
                return ['w' => null, 'h' => null, 'hasRatio' => false];
            }
            $w = self::parseSvgLengthAttribute($svg->widthAttribute());
            $h = self::parseSvgLengthAttribute($svg->heightAttribute());
            $viewBox = $svg->viewBox();
            // A ratio comes from any of: viewBox, fixed width+height,
            // or viewBox alone — anything that gives both axes
            // mathematically. The earlier `intrinsicSvgSize` already
            // backfills missing axes from ratio, so by the time
            // we're here for "partial intrinsic" we typically have
            // at most one fixed dim. Mirror the same ratio sources.
            $hasRatio = ($viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0)
                || ($w !== null && $h !== null && $h > 0.0);
            return ['w' => $w, 'h' => $h, 'hasRatio' => $hasRatio];
        }
        $intrinsic = $this->intrinsicSize($src);
        if ($intrinsic === null) {
            return ['w' => null, 'h' => null, 'hasRatio' => false];
        }
        return [
            'w' => (float) $intrinsic[0],
            'h' => (float) $intrinsic[1],
            'hasRatio' => $intrinsic[0] > 0 && $intrinsic[1] > 0,
        ];
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
        if ($this->isSvgSrc($src)) {
            $svg = $this->loadSvgDocument($src);
            if ($svg === null) {
                return null;
            }
            return $this->intrinsicSvgSize($svg);
        }
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

    /**
     * Detect SVG background sources: `.svg` URLs and `data:image/svg+xml`
     * URIs. Case-insensitive on the extension to match CSS / file-system
     * conventions.
     */
    private function isSvgSrc(string $src): bool
    {
        if (str_starts_with(strtolower($src), 'data:image/svg+xml')) {
            return true;
        }
        $path = parse_url($src, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $src;
        }
        return strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'svg';
    }

    /**
     * Parse an SVG background source into an `SvgDocument`, memoising by
     * `$src` so each unique URL is read + parsed once per Painter. Returns
     * `null` on read / parse failure (the caller paints nothing — same
     * fallback as raster sources that fail to parse).
     */
    private function loadSvgDocument(string $src): ?\Phpdftk\Svg\SvgDocument
    {
        if (array_key_exists($src, $this->svgDocumentCache)) {
            $cached = $this->svgDocumentCache[$src];
            return $cached === false ? null : $cached;
        }
        $bytes = null;
        try {
            if (str_starts_with($src, 'data:')) {
                $bytes = $this->decodeSvgDataUri($src);
            } else {
                $resolved = $this->resolveImageSrc($src);
                if ($resolved !== null) {
                    $bytes = \Phpdftk\Filesystem\LocalFilesystem::readFile($resolved, 'SVG background-image');
                }
            }
            if ($bytes === null || $bytes === '') {
                $this->svgDocumentCache[$src] = false;
                return null;
            }
            $doc = (new \Phpdftk\Svg\Parser())->parse($bytes);
        } catch (\Throwable) {
            $this->svgDocumentCache[$src] = false;
            return null;
        }
        $this->svgDocumentCache[$src] = $doc;
        return $doc;
    }

    private function decodeSvgDataUri(string $src): ?string
    {
        if (preg_match('~^data:image/svg\+xml(?:;[^,]*)?,(.*)$~is', $src, $m) !== 1) {
            return null;
        }
        $payload = $m[1];
        if (str_contains(strtolower($src), ';base64,')) {
            $decoded = base64_decode($payload, strict: true);
            return $decoded === false ? null : $decoded;
        }
        return rawurldecode($payload);
    }

    /**
     * Derive an intrinsic pixel size from an `<svg>` element per
     * CSS Images 3 §5.2:
     *   - both width + height set in absolute units    → use them
     *   - only width  + intrinsic aspect ratio         → (w, w/aspect)
     *   - only height + intrinsic aspect ratio         → (h*aspect, h)
     *   - viewBox only                                 → viewBox dims
     *   - nothing useful                               → null
     *
     * Returns null when the SVG has no intrinsic dimensions AND no
     * intrinsic ratio. The caller (`resolveBackgroundSize` / friends)
     * then falls back to the background-positioning area for `cover`
     * / `contain` (CSS Backgrounds 3 §3.9 — "no intrinsic ratio →
     * 100% × 100% of the positioning area"). Earlier this method
     * returned `[300, 150]` (the CSS Images 3 §5.3 default object
     * size), but that constant misroutes the cover/contain math for
     * SVGs without intrinsic information.
     *
     * @return array{int, int}|null
     */
    private function intrinsicSvgSize(\Phpdftk\Svg\SvgDocument $svg): ?array
    {
        $w = self::parseSvgLengthAttribute($svg->widthAttribute());
        $h = self::parseSvgLengthAttribute($svg->heightAttribute());
        $viewBox = $svg->viewBox();
        $aspect = null;
        if ($viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0) {
            $aspect = $viewBox[2] / $viewBox[3];
        } elseif ($w !== null && $h !== null && $h > 0.0) {
            $aspect = $w / $h;
        }
        if ($w !== null && $h !== null) {
            return [max(1, (int) round($w)), max(1, (int) round($h))];
        }
        // Derived-from-aspect: keep the clamp on the *explicit* side
        // (so a real `width="8"` survives rounding), but let the
        // derived side fall to zero when an extreme viewBox aspect
        // (e.g. `2147483647:1`) makes the other dimension
        // mathematically negligible. CSS Backgrounds 3 §3.9 then
        // resolves `contain` to a zero-extent tile — matching the
        // browser-visible "renders as empty" outcome that the
        // `tall--contain--height` / `wide--contain--height` reftests
        // expect for SVGs with extreme viewBox aspect ratios.
        if ($w !== null && $aspect !== null && $aspect > 0.0) {
            return [max(1, (int) round($w)), max(0, (int) round($w / $aspect))];
        }
        if ($h !== null && $aspect !== null && $aspect > 0.0) {
            return [max(0, (int) round($h * $aspect)), max(1, (int) round($h))];
        }
        if ($viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0) {
            return [max(1, (int) round($viewBox[2])), max(1, (int) round($viewBox[3]))];
        }
        return null;
    }

    /**
     * Parse an SVG length attribute like `"8"`, `"8px"`, `"50%"`. Returns
     * a float when the value is an absolute length (with no unit or `px`);
     * returns null for percentages, em / rem / vw etc. — those aren't
     * intrinsic for sizing purposes (CSS Images 3 §5.2).
     */
    private static function parseSvgLengthAttribute(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(-?\d+(?:\.\d+)?)(px)?$/', $raw, $m) !== 1) {
            return null;
        }
        return (float) $m[1];
    }

    /**
     * Paint a CSS `border-image` 9-slice grid in place of the per-side
     * border colours/styles (CSS Backgrounds 3 §6). Returns true when
     * a border-image was painted; the caller skips legacy border
     * painting in that case.
     *
     * Phase-1 scope:
     *   • `border-image-source`: `url(...)` raster (PNG/JPG) only
     *   • `border-image-slice`: a single number (px from each edge)
     *     or `<n> fill` — single-percentage and four-value variants
     *     are a follow-up
     *   • `border-image-width`: implicit (use the box's border-width)
     *   • `border-image-outset`: implicit (zero)
     *   • `border-image-repeat`: `stretch` (initial), `repeat`, `round`
     *
     * Anything outside that surface falls through to the legacy
     * paint-borders path.
     */
    private function paintBorderImage(Box $box, ContentStream $stream): bool
    {
        if ($this->writer === null || $this->page === null) {
            return false;
        }
        $source = $box->style->get('border-image-source');
        if (!$source instanceof \Phpdftk\Css\Value\Url) {
            return false;
        }
        // Raster only for the first cut — SVG border-image needs the
        // SvgRenderer slicing path, deferred.
        if ($this->isSvgSrc($source->url)) {
            return false;
        }
        if (isset($this->imageNameCache[$source->url])) {
            $name = $this->imageNameCache[$source->url];
        } else {
            $resolved = $this->resolveImageSrc($source->url);
            if ($resolved === null) {
                return false;
            }
            try {
                $name = $this->writer->addImage($resolved, $this->page);
            } catch (\Throwable) {
                return false;
            }
            $this->imageNameCache[$source->url] = $name;
        }
        $intrinsic = $this->intrinsicSize($source->url);
        if ($intrinsic === null) {
            return false;
        }
        [$srcW, $srcH] = $intrinsic;
        if ($srcW <= 0 || $srcH <= 0) {
            return false;
        }
        // Slice dimensions: 1–4 values in `<number>` / `<length>` /
        // `<percentage>`, applied to top/right/bottom/left in the
        // standard "TRBL fill-in" rule (CSS Backgrounds 3 §6.4.3).
        // Horizontal slices (top, bottom) resolve their percentages
        // against the image's height; vertical slices (left, right)
        // resolve against width.
        $sliceValue = $box->style->get('border-image-slice');
        $slices = $this->resolveBorderImageSliceSides($sliceValue, (float) $srcW, (float) $srcH);
        if ($slices === null) {
            return false;
        }
        [$st, $sr, $sb, $sl] = $slices;
        $st = min($st, (float) $srcH / 2.0);
        $sb = min($sb, (float) $srcH / 2.0);
        $sl = min($sl, (float) $srcW / 2.0);
        $sr = min($sr, (float) $srcW / 2.0);
        // `border-image-slice ... fill` keeps the middle image region and
        // paints it into the content box (CSS Backgrounds 3 §6.2). With
        // `slice: 0 fill` every edge slice is zero yet the middle must still
        // paint, so don't bail on all-zero slices when `fill` is present.
        $hasFill = $this->borderImageSliceHasFill($sliceValue);
        if (!$hasFill && $st <= 0.0 && $sr <= 0.0 && $sb <= 0.0 && $sl <= 0.0) {
            return false;
        }

        [$repeatH, $repeatV] = $this->parseBorderImageRepeatModes($box->style->get('border-image-repeat'));

        // Destination border area: the box's border-box rect, grown by
        // `border-image-outset` (CSS Backgrounds 3 §6.6 — extends the area
        // outward past the border edge, into the margin).
        $geo = $box->geometry;
        $bx = $geo->x - $geo->paddingLeft - $geo->borderLeft;
        $by = $geo->y - $geo->paddingTop - $geo->borderTop;
        $bw = $geo->paddingLeft + $geo->width + $geo->paddingRight
            + $geo->borderLeft + $geo->borderRight;
        $bh = $geo->paddingTop + $geo->height + $geo->paddingBottom
            + $geo->borderTop + $geo->borderBottom;
        [$ot, $or, $ob, $ol] = $this->resolveBorderImageOutset(
            $box->style->get('border-image-outset'),
            $geo->borderTop,
            $geo->borderRight,
            $geo->borderBottom,
            $geo->borderLeft,
        );
        $bx -= $ol;
        $by -= $ot;
        $bw += $ol + $or;
        $bh += $ot + $ob;
        // `border-image-width` sizes the destination border slices; it
        // defaults to the border-widths but may be a multiple / length /
        // percentage that extends inward past the border into the padding.
        [$bt, $br, $bb, $bl] = $this->resolveBorderImageWidth(
            $box->style->get('border-image-width'),
            $geo->borderTop,
            $geo->borderRight,
            $geo->borderBottom,
            $geo->borderLeft,
            $bw,
            $bh,
        );
        if ($bw <= 0.0 || $bh <= 0.0) {
            return false;
        }

        // 9 destination + source rects. Source coords use the SVG/PNG
        // convention: (0, 0) top-left, y grows down. Destination coords
        // here are layout-space (Y down too); we convert to PDF
        // (Y up) inside `emitImageSlice`.
        $midSrcW = max(0.0, (float) $srcW - $sl - $sr);
        $midSrcH = max(0.0, (float) $srcH - $st - $sb);
        $midDstW = max(0.0, $bw - $bl - $br);
        $midDstH = max(0.0, $bh - $bt - $bb);

        // Corners — always stretched (no tile / round per spec §6.3).
        $this->emitImageSlice($stream, $name, $srcW, $srcH, 0.0, 0.0, $sl, $st, $bx, $by, $bl, $bt);
        $this->emitImageSlice($stream, $name, $srcW, $srcH, (float) $srcW - $sr, 0.0, $sr, $st, $bx + $bw - $br, $by, $br, $bt);
        $this->emitImageSlice($stream, $name, $srcW, $srcH, 0.0, (float) $srcH - $sb, $sl, $sb, $bx, $by + $bh - $bb, $bl, $bb);
        $this->emitImageSlice($stream, $name, $srcW, $srcH, (float) $srcW - $sr, (float) $srcH - $sb, $sr, $sb, $bx + $bw - $br, $by + $bh - $bb, $br, $bb);

        // Edges — apply repeat mode to the tile axis only. Each edge also
        // needs a non-zero SOURCE slice thickness ($st/$sb for the
        // horizontal edges, $sl/$sr for the vertical ones); a zero-thickness
        // slice (e.g. `slice: 0 fill`) has no edge image to sample and would
        // divide by zero in emitImageEdge.
        if ($midSrcW > 0.0 && $midDstW > 0.0 && $bt > 0.0 && $st > 0.0) {
            $this->emitImageEdge(
                $stream,
                $name,
                $srcW,
                $srcH,
                $sl,
                0.0,
                $midSrcW,
                $st,
                $bx + $bl,
                $by,
                $midDstW,
                $bt,
                $repeatH,
                horizontal: true,
            );
        }
        if ($midSrcW > 0.0 && $midDstW > 0.0 && $bb > 0.0 && $sb > 0.0) {
            $this->emitImageEdge(
                $stream,
                $name,
                $srcW,
                $srcH,
                $sl,
                (float) $srcH - $sb,
                $midSrcW,
                $sb,
                $bx + $bl,
                $by + $bh - $bb,
                $midDstW,
                $bb,
                $repeatH,
                horizontal: true,
            );
        }
        if ($midSrcH > 0.0 && $midDstH > 0.0 && $bl > 0.0 && $sl > 0.0) {
            $this->emitImageEdge(
                $stream,
                $name,
                $srcW,
                $srcH,
                0.0,
                $st,
                $sl,
                $midSrcH,
                $bx,
                $by + $bt,
                $bl,
                $midDstH,
                $repeatV,
                horizontal: false,
            );
        }
        if ($midSrcH > 0.0 && $midDstH > 0.0 && $br > 0.0 && $sr > 0.0) {
            $this->emitImageEdge(
                $stream,
                $name,
                $srcW,
                $srcH,
                (float) $srcW - $sr,
                $st,
                $sr,
                $midSrcH,
                $bx + $bw - $br,
                $by + $bt,
                $br,
                $midDstH,
                $repeatV,
                horizontal: false,
            );
        }

        // Middle fill (region 5) — CSS Backgrounds 3 §6.2 `fill`. The middle
        // image is drawn into the box's content area governed by the same
        // repeat modes as the edges: `stretch` scales it to fill; `repeat` /
        // `round` / `space` tile it at its natural (intrinsic) size, centred,
        // and clip to the content area — so an over-large middle shows only
        // its centre.
        if ($hasFill
            && $midSrcW > 0.0 && $midSrcH > 0.0
            && $midDstW > 0.0 && $midDstH > 0.0
        ) {
            $this->emitImageMiddleFill(
                $stream,
                $name,
                $srcW,
                $srcH,
                $sl,
                $st,
                $midSrcW,
                $midSrcH,
                $bx + $bl,
                $by + $bt,
                $midDstW,
                $midDstH,
                $repeatH,
                $repeatV,
            );
        }
        return true;
    }

    /**
     * Paint the `border-image` middle region (the `fill` keyword) into the
     * content area, tiling per axis. `stretch` scales the middle to fill that
     * axis; `repeat`/`round`/`space` tile it at its intrinsic size (the layout
     * geometry is in CSS px, so 1 source px = 1 unit), centred and clipped to
     * the content area.
     */
    private function emitImageMiddleFill(
        ContentStream $stream,
        string $imageName,
        int $srcW,
        int $srcH,
        float $sx,
        float $sy,
        float $sw,
        float $sh,
        float $dx,
        float $dy,
        float $dw,
        float $dh,
        string $repeatH,
        string $repeatV,
    ): void {
        if ($sw <= 0.0 || $sh <= 0.0 || $dw <= 0.0 || $dh <= 0.0) {
            return;
        }
        [$tileW, $offX] = $this->borderImageFillAxis($repeatH, $dw, $sw);
        [$tileH, $offY] = $this->borderImageFillAxis($repeatV, $dh, $sh);
        if ($tileW <= 0.0 || $tileH <= 0.0 || $offX === [] || $offY === []) {
            return;
        }
        $pdfDy = $this->pageHeight - $dy - $dh;
        $stream->saveGraphicsState();
        $stream->rectangle($dx, $pdfDy, $dw, $dh);
        $stream->clip();
        $stream->endPath();
        foreach ($offY as $oy) {
            foreach ($offX as $ox) {
                $this->emitImageSlice(
                    $stream,
                    $imageName,
                    $srcW,
                    $srcH,
                    $sx,
                    $sy,
                    $sw,
                    $sh,
                    $dx + $ox,
                    $dy + $oy,
                    $tileW,
                    $tileH,
                );
            }
        }
        $stream->restoreGraphicsState();
    }

    /**
     * Tile-placement for one axis of the border-image middle fill. Returns
     * `[tileLength, [offsets…]]` in the destination axis. Mirrors the edge
     * repeat rules (CSS Backgrounds 3 §6.2): `stretch` → one dest-spanning
     * tile; `round` → rescaled to a whole count; `space` → whole tiles with
     * distributed gaps; `repeat` → natural size, centred, partial tiles clipped.
     *
     * @return array{0: float, 1: list<float>}
     */
    private function borderImageFillAxis(string $mode, float $axisDest, float $natLen): array
    {
        if ($mode === 'stretch' || $natLen <= 0.0) {
            return [$axisDest, [0.0]];
        }
        if ($mode === 'round') {
            $n = max(1, (int) round($axisDest / $natLen));
            $tile = $axisDest / $n;
            $offsets = [];
            for ($i = 0; $i < $n; $i++) {
                $offsets[] = $i * $tile;
            }
            return [$tile, $offsets];
        }
        if ($mode === 'space') {
            $n = (int) floor($axisDest / $natLen + 1e-6);
            if ($n < 1) {
                return [$natLen, []];
            }
            $gap = ($axisDest - $n * $natLen) / ($n + 1);
            $offsets = [];
            for ($i = 0; $i < $n; $i++) {
                $offsets[] = ($i + 1) * $gap + $i * $natLen;
            }
            return [$natLen, $offsets];
        }
        // repeat — natural tile, centred, partial end tiles clipped.
        $remainder = fmod($axisDest, $natLen);
        $start = $remainder > 1e-6 ? -($natLen - $remainder) / 2.0 : 0.0;
        $offsets = [];
        for ($pos = $start; $pos < $axisDest - 1e-6 && count($offsets) < 4096; $pos += $natLen) {
            $offsets[] = $pos;
        }
        return [$natLen, $offsets];
    }

    /**
     * Whether a `border-image-slice` value carries the `fill` keyword, which
     * preserves and paints the middle image region into the content box
     * (CSS Backgrounds 3 §6.2).
     */
    private function borderImageSliceHasFill(?\Phpdftk\Css\Value\Value $value): bool
    {
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            return strtolower($value->name) === 'fill';
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Keyword
                    && strtolower($v->name) === 'fill'
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Emit a single image slice — the source rect (sx, sy, sw, sh) in
     * source pixel coords lands at destination rect (dx, dy, dw, dh)
     * in layout coords. Stretched (no tiling) — used for corners.
     */
    private function emitImageSlice(
        ContentStream $stream,
        string $imageName,
        int $srcW,
        int $srcH,
        float $sx,
        float $sy,
        float $sw,
        float $sh,
        float $dx,
        float $dy,
        float $dw,
        float $dh,
    ): void {
        if ($sw <= 0.0 || $sh <= 0.0 || $dw <= 0.0 || $dh <= 0.0) {
            return;
        }
        // PDF y is up; image unit-y=0 is bottom (after the y-flip
        // implicit in addImage). Source rect's bottom in source-px:
        // `srcH - (sy + sh)`. Place the FULL image such that this
        // bottom-of-slice falls on PDF dy.
        $pdfDy = $this->pageHeight - $dy - $dh;
        $sliceBotSrc = (float) $srcH - $sy - $sh;
        $scaleX = $dw * (float) $srcW / $sw;
        $scaleY = $dh * (float) $srcH / $sh;
        $tx = $dx - $dw * $sx / $sw;
        $ty = $pdfDy - $dh * $sliceBotSrc / $sh;
        $stream->saveGraphicsState();
        $stream->rectangle($dx, $pdfDy, $dw, $dh);
        $stream->clip();
        $stream->endPath();
        $stream->concatMatrix($scaleX, 0.0, 0.0, $scaleY, $tx, $ty);
        $stream->doXObject($imageName);
        $stream->restoreGraphicsState();
    }

    /**
     * Emit an edge slice (top/right/bottom/left) of a border-image.
     * `horizontal: true` tiles along the X axis; false tiles along Y.
     * `repeatMode`: 'stretch' (default) — single stretched draw;
     * 'repeat' — tile at natural size from the leading edge; 'round'
     * — scale-to-fit-whole-tiles.
     */
    private function emitImageEdge(
        ContentStream $stream,
        string $imageName,
        int $srcW,
        int $srcH,
        float $sx,
        float $sy,
        float $sw,
        float $sh,
        float $dx,
        float $dy,
        float $dw,
        float $dh,
        string $repeatMode,
        bool $horizontal,
    ): void {
        if ($repeatMode === 'stretch') {
            $this->emitImageSlice($stream, $imageName, $srcW, $srcH, $sx, $sy, $sw, $sh, $dx, $dy, $dw, $dh);
            return;
        }
        // Natural tile length along the variable axis (the source edge
        // slice scaled to the border thickness); the other dim is fixed.
        $tileLen = $horizontal ? $dh * $sw / $sh : $dw * $sh / $sw;
        $axisDest = $horizontal ? $dw : $dh;
        if ($tileLen <= 0.0 || $axisDest <= 0.0) {
            return;
        }
        // Per CSS Backgrounds 3 §6.2, compute the tile start offsets along
        // the axis for each repeat mode:
        //   round  — rescale so a whole number fits, butted, no gaps.
        //   space  — as many whole tiles as fit, leftover distributed as
        //            equal gaps BETWEEN and AROUND the tiles ((n+1) gaps),
        //            so a single tile is centred; no whole tile → nothing.
        //   repeat — natural size, the tiling centred and partial end
        //            tiles clipped.
        $offsets = [];
        if ($repeatMode === 'round') {
            $n = max(1, (int) round($axisDest / $tileLen));
            $tileLen = $axisDest / $n;
            for ($i = 0; $i < $n; $i++) {
                $offsets[] = $i * $tileLen;
            }
        } elseif ($repeatMode === 'space') {
            $n = (int) floor($axisDest / $tileLen + 1e-6);
            if ($n < 1) {
                return;
            }
            $gap = ($axisDest - $n * $tileLen) / ($n + 1);
            for ($i = 0; $i < $n; $i++) {
                $offsets[] = ($i + 1) * $gap + $i * $tileLen;
            }
        } else {
            $remainder = fmod($axisDest, $tileLen);
            $start = $remainder > 1e-6 ? -($tileLen - $remainder) / 2.0 : 0.0;
            for ($pos = $start; $pos < $axisDest - 1e-6 && count($offsets) < 4096; $pos += $tileLen) {
                $offsets[] = $pos;
            }
        }
        // Clip to the destination edge so partial / spaced tiles crop cleanly.
        $pdfDy = $this->pageHeight - $dy - $dh;
        $stream->saveGraphicsState();
        $stream->rectangle($dx, $pdfDy, $dw, $dh);
        $stream->clip();
        $stream->endPath();
        foreach ($offsets as $off) {
            if ($horizontal) {
                $this->emitImageSlice($stream, $imageName, $srcW, $srcH, $sx, $sy, $sw, $sh, $dx + $off, $dy, $tileLen, $dh);
            } else {
                $this->emitImageSlice($stream, $imageName, $srcW, $srcH, $sx, $sy, $sw, $sh, $dx, $dy + $off, $dw, $tileLen);
            }
        }
        $stream->restoreGraphicsState();
    }

    private function parseBorderImageSliceNumber(?\Phpdftk\Css\Value\Value $value): ?float
    {
        if ($value instanceof \Phpdftk\Css\Value\Number
            || $value instanceof \Phpdftk\Css\Value\Integer
        ) {
            return (float) $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            return $value->value;
        }
        // Single number wrapped in a ValueList with an optional `fill`
        // keyword — accept the leading number; the `fill` middle paint
        // is a follow-up.
        if ($value instanceof \Phpdftk\Css\Value\ValueList && $value->values !== []) {
            return $this->parseBorderImageSliceNumber($value->values[0]);
        }
        return null;
    }

    /**
     * Resolve a single `border-image-slice` component (number, length
     * or percentage). Percentages resolve against the supplied axis
     * extent per CSS Backgrounds 3 §6.4.3. Returns null when the value
     * isn't a slice-shape we recognise.
     */
    private function resolveBorderImageSliceComponent(
        ?\Phpdftk\Css\Value\Value $value,
        float $axisExtent,
    ): ?float {
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $value->value / 100.0 * $axisExtent;
        }
        return $this->parseBorderImageSliceNumber($value);
    }

    /**
     * Expand the 1–4-value `border-image-slice` shorthand into the
     * per-side `[top, right, bottom, left]` tuple. Drops a trailing
     * `fill` keyword (handled separately when middle-fill paint
     * lands).
     *
     * @return array{0:float, 1:float, 2:float, 3:float}|null
     */
    private function resolveBorderImageSliceSides(
        ?\Phpdftk\Css\Value\Value $value,
        float $srcW,
        float $srcH,
    ): ?array {
        $components = [];
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Keyword
                    && strtolower($v->name) === 'fill'
                ) {
                    continue;
                }
                $components[] = $v;
            }
        } elseif ($value !== null) {
            $components[] = $value;
        }
        if ($components === []) {
            return null;
        }
        // Horizontal slices (top, bottom) resolve `%` against srcH;
        // vertical (right, left) against srcW.
        $sides = [
            'top' => $this->resolveBorderImageSliceComponent($components[0], $srcH),
        ];
        if (isset($components[1])) {
            $sides['right'] = $this->resolveBorderImageSliceComponent($components[1], $srcW);
        } else {
            $sides['right'] = $sides['top'];
        }
        if (isset($components[2])) {
            $sides['bottom'] = $this->resolveBorderImageSliceComponent($components[2], $srcH);
        } else {
            $sides['bottom'] = $sides['top'];
        }
        if (isset($components[3])) {
            $sides['left'] = $this->resolveBorderImageSliceComponent($components[3], $srcW);
        } else {
            $sides['left'] = $sides['right'];
        }
        foreach ($sides as $side) {
            if ($side === null) {
                return null;
            }
        }
        return [$sides['top'], $sides['right'], $sides['bottom'], $sides['left']];
    }

    /**
     * Resolve `border-image-width` to the per-side used widths `[top, right,
     * bottom, left]` of the destination border-image area. A `<number>` is a
     * multiple of the corresponding computed border-width; `<length>` is
     * absolute; `<percentage>` is relative to the border-image area (its
     * height for the top/bottom edges, its width for left/right); `auto`
     * falls back to the border-width. Opposite pairs that overflow the area
     * are reduced together (CSS Backgrounds 3 §6.5). Unset → the border
     * widths (the initial `1`).
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function resolveBorderImageWidth(
        ?\Phpdftk\Css\Value\Value $value,
        float $borderTop,
        float $borderRight,
        float $borderBottom,
        float $borderLeft,
        float $areaWidth,
        float $areaHeight,
    ): array {
        $borders = [$borderTop, $borderRight, $borderBottom, $borderLeft];
        // Top/bottom offsets are vertical → resolve `%` against the area
        // height; left/right are horizontal → against the width.
        $areaForEdge = [$areaHeight, $areaWidth, $areaHeight, $areaWidth];
        $components = [];
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $components = $value->values;
        } elseif ($value !== null) {
            $components = [$value];
        }
        // TRBL fill-in.
        $sides = [
            $components[0] ?? null,
            $components[1] ?? ($components[0] ?? null),
            $components[2] ?? ($components[0] ?? null),
            $components[3] ?? ($components[1] ?? ($components[0] ?? null)),
        ];
        $resolveOne = static function (?\Phpdftk\Css\Value\Value $v, int $edge) use ($borders, $areaForEdge): float {
            if ($v instanceof \Phpdftk\Css\Value\Keyword && strtolower($v->name) === 'auto') {
                return $borders[$edge];
            }
            if ($v instanceof \Phpdftk\Css\Value\Number || $v instanceof \Phpdftk\Css\Value\Integer) {
                return max(0.0, (float) $v->value) * $borders[$edge];
            }
            if ($v instanceof \Phpdftk\Css\Value\Length) {
                return max(0.0, $v->value);
            }
            if ($v instanceof \Phpdftk\Css\Value\Percentage) {
                return max(0.0, $areaForEdge[$edge] * $v->value / 100.0);
            }
            return $borders[$edge];
        };
        $wt = $resolveOne($sides[0], 0);
        $wr = $resolveOne($sides[1], 1);
        $wb = $resolveOne($sides[2], 2);
        $wl = $resolveOne($sides[3], 3);
        $f = 1.0;
        if ($wt + $wb > 0.0) {
            $f = min($f, $areaHeight / ($wt + $wb));
        }
        if ($wl + $wr > 0.0) {
            $f = min($f, $areaWidth / ($wl + $wr));
        }
        if ($f < 1.0) {
            $wt *= $f;
            $wr *= $f;
            $wb *= $f;
            $wl *= $f;
        }
        return [$wt, $wr, $wb, $wl];
    }

    /**
     * Resolve `border-image-outset` to the per-side outward extension
     * `[top, right, bottom, left]`. A `<number>` is a multiple of the
     * corresponding computed border-width; `<length>` is absolute.
     * Unset → 0. CSS Backgrounds 3 §6.6.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function resolveBorderImageOutset(
        ?\Phpdftk\Css\Value\Value $value,
        float $borderTop,
        float $borderRight,
        float $borderBottom,
        float $borderLeft,
    ): array {
        $borders = [$borderTop, $borderRight, $borderBottom, $borderLeft];
        $components = [];
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $components = $value->values;
        } elseif ($value !== null) {
            $components = [$value];
        }
        $sides = [
            $components[0] ?? null,
            $components[1] ?? ($components[0] ?? null),
            $components[2] ?? ($components[0] ?? null),
            $components[3] ?? ($components[1] ?? ($components[0] ?? null)),
        ];
        $resolveOne = static function (?\Phpdftk\Css\Value\Value $v, int $edge) use ($borders): float {
            if ($v instanceof \Phpdftk\Css\Value\Number || $v instanceof \Phpdftk\Css\Value\Integer) {
                return max(0.0, (float) $v->value) * $borders[$edge];
            }
            if ($v instanceof \Phpdftk\Css\Value\Length) {
                return max(0.0, $v->value);
            }
            return 0.0;
        };
        return [
            $resolveOne($sides[0], 0),
            $resolveOne($sides[1], 1),
            $resolveOne($sides[2], 2),
            $resolveOne($sides[3], 3),
        ];
    }

    /**
     * `border-image-repeat` is one or two keywords: the first applies to the
     * horizontal edges (top / bottom), the second to the vertical edges
     * (left / right); a single value applies to both. Returns
     * `[horizontal, vertical]`. CSS Backgrounds 3 §6.2.
     *
     * @return array{0: string, 1: string}
     */
    private function parseBorderImageRepeatModes(?\Phpdftk\Css\Value\Value $value): array
    {
        $map = static fn(string $kw): string => match ($kw) {
            'repeat' => 'repeat',
            'round' => 'round',
            'space' => 'space',
            default => 'stretch',
        };
        $kw = static fn(?\Phpdftk\Css\Value\Value $v): ?string => $v instanceof \Phpdftk\Css\Value\Keyword
            ? $map(strtolower($v->name))
            : null;
        if ($value instanceof \Phpdftk\Css\Value\ValueList && $value->values !== []) {
            $h = $kw($value->values[0]) ?? 'stretch';
            $v = $kw($value->values[1] ?? null) ?? $h;
            return [$h, $v];
        }
        $single = $kw($value) ?? 'stretch';
        return [$single, $single];
    }

    private function svgRenderer(): \Phpdftk\SvgToPdf\SvgRenderer
    {
        if ($this->svgRenderer === null) {
            assert($this->page !== null && $this->writer !== null);
            $this->svgRenderer = new \Phpdftk\SvgToPdf\SvgRenderer($this->page, $this->writer);
        }
        return $this->svgRenderer;
    }

    private function inlineSvgAdapter(): \Phpdftk\HtmlToPdf\Svg\InlineSvgAdapter
    {
        if ($this->inlineSvgAdapter === null) {
            $this->inlineSvgAdapter = new \Phpdftk\HtmlToPdf\Svg\InlineSvgAdapter();
        }
        return $this->inlineSvgAdapter;
    }

    private function inlineMathmlAdapter(): \Phpdftk\HtmlToPdf\Mathml\InlineMathmlAdapter
    {
        if ($this->inlineMathmlAdapter === null) {
            $this->inlineMathmlAdapter = new \Phpdftk\HtmlToPdf\Mathml\InlineMathmlAdapter();
        }
        return $this->inlineMathmlAdapter;
    }

    private function mathmlRenderer(): \Phpdftk\MathmlToPdf\MathmlRenderer
    {
        if ($this->mathmlRenderer === null) {
            assert($this->page !== null && $this->writer !== null);
            $this->mathmlRenderer = new \Phpdftk\MathmlToPdf\MathmlRenderer($this->page, $this->writer);
        }
        return $this->mathmlRenderer;
    }

    /**
     * Resolve the math-font {@see OpenTypeData} for `$box` by
     * reading the cascaded `font-family` and looking it up in
     * `$fontDataByFamily`. Returns null when no family matches,
     * the matching font has no MATH table, or the cascade hasn't
     * resolved a usable family name.
     *
     * Used by paintInlineMath to thread the cascade-loaded font
     * (typically via `@font-face`) into the MathmlRenderer so its
     * MATH-table constants (FractionRuleThickness, axis height,
     * etc.) drive layout. Without this hook MathmlRenderer falls
     * back to its tracer-bullet defaults regardless of what
     * `font-family` the cascade resolved.
     */
    private function mathFontDataFor(
        \Phpdftk\HtmlToPdf\Box\AtomicInlineBox $box,
    ): ?\Phpdftk\FontParser\FontFaceData {
        if ($this->fontDataByFamily === []) {
            return null;
        }
        $family = $box->style->get('font-family');
        $names = [];
        if ($family instanceof \Phpdftk\Css\Value\StringValue) {
            $names[] = $family->value;
        } elseif ($family instanceof \Phpdftk\Css\Value\Keyword) {
            $names[] = $family->name;
        } elseif ($family instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($family->values as $entry) {
                if ($entry instanceof \Phpdftk\Css\Value\StringValue) {
                    $names[] = $entry->value;
                } elseif ($entry instanceof \Phpdftk\Css\Value\Keyword) {
                    $names[] = $entry->name;
                }
            }
        }
        foreach ($names as $name) {
            $key = strtolower(trim($name));
            $data = $this->fontDataByFamily[$key] ?? null;
            if ($data !== null
                && $data->mathTable !== null
                && $data->mathTable->hasMathConstants()
            ) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Build a fresh {@see MathmlRenderer} for `$box` with its
     * math-font data threaded in. Falls back to the cached default
     * renderer when no MATH-table font matches the cascade -
     * keeps the common "no math font" path zero-cost.
     *
     * Intentionally retained but not yet wired into the paint path:
     * switching to the per-element renderer regresses
     * `painting-stretchy-operator-001` and `frac-default-padding`
     * (see the call site in {@see paintMathml}). Kept as the #105
     * math-font-handoff substrate.
     *
     * @phpstan-ignore method.unused
     */
    private function mathmlRendererFor(
        \Phpdftk\HtmlToPdf\Box\AtomicInlineBox $box,
    ): \Phpdftk\MathmlToPdf\MathmlRenderer {
        $fontData = $this->mathFontDataFor($box);
        // The MathML renderer needs CFF outlines for its math-table-
        // driven glyph variants. TrueType math fonts exist (`STIXTwoMath`
        // ships both), but the math path is wired through the OT-CFF
        // subset/embed flow today. Drop back to the cached default
        // renderer for non-CFF data — the math layout still runs, just
        // without the size-variant / assembly-part substitution layer.
        if (!$fontData instanceof \Phpdftk\FontParser\OpenTypeData) {
            return $this->mathmlRenderer();
        }
        assert($this->page !== null && $this->writer !== null);
        return new \Phpdftk\MathmlToPdf\MathmlRenderer(
            $this->page,
            $this->writer,
            mathFontData: $fontData,
        );
    }

    /**
     * Paint an inline `<math>` HTML element by adapting its subtree
     * into a typed MathmlDocument and handing the result to the
     * MathmlRenderer.
     *
     * Sizing precedence mirrors the inline-SVG painter (CSS Display
     * §3.5):
     *
     *   1. Box geometry from the CSS-cascaded `width` / `height` —
     *      only populated when InlineLayout actually laid the box
     *      out (see #39).
     *   2. Cascade values read directly (covers the no-font case
     *      where InlineLayout returns early).
     *
     * MathML doesn't have a viewBox or intrinsic-attribute shortcut
     * the way SVG does — when nothing in the cascade declares a
     * size, math content has an intrinsic size derived from its
     * glyph metrics. For the tracer-bullet renderer we default to
     * a one-line strip the same height as the renderer's default
     * font (12 pt × 1 line ≈ 14 pt) and an arbitrary width band
     * (200 pt) so a sized-from-glyphs <math> still produces output.
     * Real intrinsic sizing lands once MathmlRenderer learns to
     * measure its own glyphs (separate follow-up).
     *
     * A parse failure here is swallowed and the MathML silently
     * skipped — one malformed inline expression shouldn't poison
     * the whole page render.
     */
    private function paintInlineMath(
        \Phpdftk\Html\Dom\Element $element,
        Box $box,
        ContentStream $stream,
    ): void {
        $geo = $box->geometry;
        $width = $geo->width;
        $height = $geo->height;
        // Layer 2: read directly from the cascade when layout left
        // geometry zero. Same fix the SVG painter applies for the
        // no-font case (#39).
        if ($width <= 0.0) {
            $cascaded = $box->style->get('width');
            if ($cascaded instanceof \Phpdftk\Css\Value\Length && $cascaded->value > 0.0) {
                $width = $cascaded->value;
            }
        }
        if ($height <= 0.0) {
            $cascaded = $box->style->get('height');
            if ($cascaded instanceof \Phpdftk\Css\Value\Length && $cascaded->value > 0.0) {
                $height = $cascaded->value;
            }
        }
        // Parse the inline MathML before deriving any intrinsic
        // size so we can ask MathmlRenderer for its natural
        // dimensions when the cascade left both axes zero. Layer
        // 3 (intrinsic) sits between the cascade-explicit values
        // and the typographic fallback.
        try {
            $mathDoc = $this->inlineMathmlAdapter()->adapt($element);
        } catch (\Throwable) {
            return;
        }
        // Resolve the CSS-cascaded font size for the <math> element
        // so em-relative children (mpadded height="3em", mspace
        // depth="2em", ...) measure against the right base. WPT
        // fixtures expect CSS default of 16px (== 16pt under
        // html-to-pdf's 1px = 1pt cascade); the painter falls back
        // to MathmlRenderer::DEFAULT_FONT_SIZE when nothing is set.
        $fontSize = $this->dominantFontSize($box);
        if ($width <= 0.0 || $height <= 0.0) {
            [$intrinsicW, $intrinsicH] = $this->mathmlRenderer()
                ->intrinsicSize($mathDoc, $fontSize);
            if ($width <= 0.0 && $intrinsicW > 0.0) {
                $width = $intrinsicW;
            }
            if ($height <= 0.0 && $intrinsicH > 0.0) {
                $height = $intrinsicH;
            }
        }
        // Final fallback: a typographically sensible default sized
        // strip. 14 pt tall is one line of 12 pt math + a sliver
        // of leading; 200 pt wide is wider than any common single-
        // expression token sequence but the renderer just stops
        // emitting glyphs when content runs out.
        if ($height <= 0.0) {
            $height = 14.0;
        }
        if ($width <= 0.0) {
            $width = 200.0;
        }
        // CSS position: absolute / fixed on inline foreign content
        // is not currently honoured by InlineLayout (which always
        // places these along the line box). For the WPT MathML
        // fixtures that use `<math style="position: absolute;
        // top: 0; left: 0;">` to anchor the math to the page edge,
        // override geo->x / geo->y with the cascaded left / top
        // when present. Treats left / top as absolute page
        // coordinates - good enough when the math sits inside a
        // top-level positioned div (the common WPT pattern); a
        // proper containing-block calculation lives behind a
        // bigger layout fix.
        [$layoutX, $layoutY] = $this->resolveInlineAbsoluteOrigin(
            $box,
            $geo->x,
            $geo->y,
        );
        $pdfY = $this->pageHeight - $layoutY - $height;
        // Use the cached default MathmlRenderer. Math-font handoff
        // via `mathmlRendererFor($box)` (#105 substrate) stays
        // gated: even with the per-element CSS cascade now
        // projecting through (#107 + this PR's font-size hook),
        // switching renderers regresses two tests that pass under
        // the default-renderer path (painting-stretchy-operator-001
        // and frac-default-padding). Both expose latent gaps
        // (stretchy operator variant selection that fills the
        // container; fraction-padding metrics that match the
        // browser) which the math-font handoff makes visible but
        // doesn't yet address.
        $renderer = $this->mathmlRenderer();
        $ascentPt = $renderer->intrinsicAscent($mathDoc, $fontSize);
        $renderer->draw(
            $mathDoc,
            $layoutX,
            $pdfY,
            $width,
            $height,
            stream: $stream,
            fontSize: $fontSize,
            ascentPt: $ascentPt,
        );
    }

    /**
     * Resolve the layout-space origin for an inline foreign
     * element when CSS `position` is `absolute` / `fixed`.
     * Returns `[x, y]` in layout (top-down) coordinates.
     *
     * Falls back to the box's layout-derived geometry when the
     * position keyword isn't a positioned form. When it IS
     * positioned, reads cascaded `left` / `top` Length values and
     * treats them as absolute page coordinates - sufficient for
     * the common case where the foreign content is inside the
     * initial containing block (or close enough that the
     * containing-block resolution from BlockLayout has already
     * shifted ancestors into position).
     *
     * @return array{0: float, 1: float}
     */
    private function resolveInlineAbsoluteOrigin(
        Box $box,
        float $defaultX,
        float $defaultY,
    ): array {
        $position = $box->style->get('position');
        if (!($position instanceof \Phpdftk\Css\Value\Keyword)) {
            return [$defaultX, $defaultY];
        }
        $keyword = strtolower($position->name);
        if ($keyword !== 'absolute' && $keyword !== 'fixed') {
            return [$defaultX, $defaultY];
        }
        $left = $box->style->get('left');
        $top = $box->style->get('top');
        $x = $left instanceof \Phpdftk\Css\Value\Length
            ? $left->value
            : $defaultX;
        $y = $top instanceof \Phpdftk\Css\Value\Length
            ? $top->value
            : $defaultY;
        return [$x, $y];
    }

    /**
     * Paint an inline `<svg>` HTML element by adapting its subtree
     * into a typed SvgDocument and handing the result to the existing
     * SvgRenderer.
     *
     * Dimensions: prefer the box's resolved geometry (CSS-cascaded
     * `width` / `height` win over intrinsic). If geometry is zero
     * because the cascade left dimensions unresolved, fall back to
     * the `<svg width="…" height="…">` attributes (treated as CSS
     * pixels) — same precedence the inline-SVG sizing algorithm uses
     * in CSS Display §3.5.
     *
     * A parse failure here is swallowed and the SVG silently skipped
     * — one malformed inline-SVG shouldn't poison the whole page
     * render. The pdf consumer sees an empty box where the SVG would
     * have been; the rest of the document is unaffected.
     */
    /**
     * Paint an `<img src="*.svg">` (or `data:image/svg+xml`)
     * replaced-element by loading the external SVG and rendering it
     * into the box's CSS-laid-out geometry.
     *
     * Unlike `paintInlineSvg`, the SVG document here is an external
     * resource: the box's geometry is already resolved by the layout
     * engine from the cascade plus the intrinsic dimensions reported
     * by `SvgParser`. We just clip to the box rect, apply
     * `object-fit` / `object-position`, and delegate to the SvgRenderer.
     */
    private function paintImgSvg(
        \Phpdftk\Html\Dom\Element $element,
        Box $box,
        ContentStream $stream,
        string $src,
    ): void {
        if ($this->writer === null || $this->page === null) {
            return;
        }
        $geo = $box->geometry;
        if ($geo->width <= 0.0) {
            return;
        }
        $svgDoc = $this->loadSvgDocument($src);
        if ($svgDoc === null) {
            return;
        }
        $height = $geo->height > 0.0 ? $geo->height : $geo->width;
        // CSS Images 3 §5.3 — `object-fit` decides the painted SVG's
        // scale within the box; `object-position` decides where the
        // slack sits. Defaults: `fill` + centre.
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
        $pdfY = $this->pageHeight - $geo->y - $height;
        $stream->saveGraphicsState();
        // Clip to the box rect so `cover` overflow doesn't bleed into
        // sibling boxes — same posture as `paintImage` for raster.
        $stream->rectangle($geo->x, $pdfY, $geo->width, $height);
        $stream->clip();
        $stream->endPath();
        try {
            $this->svgRenderer()->draw(
                $svgDoc,
                $geo->x + $rect['offsetX'],
                $pdfY + ($height - $rect['h'] - $rect['offsetY']),
                $rect['w'],
                $rect['h'],
                stream: $stream,
            );
        } catch (\Throwable) {
            // Swallow paint failures so one malformed SVG doesn't kill
            // the document render. Mirrors the raster path's catch.
        }
        $stream->restoreGraphicsState();
    }

    private function paintInlineSvg(
        \Phpdftk\Html\Dom\Element $element,
        Box $box,
        ContentStream $stream,
    ): void {
        $geo = $box->geometry;
        $width = $geo->width;
        $height = $geo->height;
        // Sizing precedence for the inline SVG:
        //   1. Box geometry from CSS-cascaded width/height — only
        //      populated when InlineLayout actually laid the box out.
        //   2. Cascade values read directly (covers the no-font case
        //      where InlineLayout returns early before tokenisation,
        //      but the cascade still has Length values from author CSS).
        //   3. The svg element's own `width` / `height` attributes,
        //      parsed as CSS pixels.
        // Without precedence #2 a document with `#s { width: 50pt }`
        // and no embedded font would render the SVG at zero size.
        // Precedence #3 catches the bare `<svg width="80" height="60">`
        // case where neither CSS nor the cascade has a Length.
        if ($width <= 0.0) {
            $cascaded = $box->style->get('width');
            if ($cascaded instanceof \Phpdftk\Css\Value\Length && $cascaded->value > 0.0) {
                $width = $cascaded->value;
            }
        }
        if ($height <= 0.0) {
            $cascaded = $box->style->get('height');
            if ($cascaded instanceof \Phpdftk\Css\Value\Length && $cascaded->value > 0.0) {
                $height = $cascaded->value;
            }
        }
        if ($width <= 0.0) {
            $attr = $element->getAttribute('width');
            if ($attr !== null) {
                $width = $this->parseSvgLength($attr);
            }
        }
        if ($height <= 0.0) {
            $attr = $element->getAttribute('height');
            if ($attr !== null) {
                $height = $this->parseSvgLength($attr);
            }
        }
        // Final fallback: intrinsic dimensions from the viewBox's
        // width/height columns. SVG 2 §8.2 — when neither CSS nor a
        // width/height attr declares a size, the viewBox supplies the
        // intrinsic aspect ratio AND, in browsers, an intrinsic
        // pixel size for replaced-element layout (third + fourth
        // viewBox values treated as CSS pixels). The viewBox parser
        // lives in `ViewportElement::viewBox()` but we duplicate the
        // tiny extraction here to avoid forcing the SVG parser to
        // run on a zero-size SVG we'd otherwise have dropped.
        if ($width <= 0.0 || $height <= 0.0) {
            $vb = $this->parseViewBox($element->getAttribute('viewBox'));
            if ($vb !== null) {
                if ($width <= 0.0) {
                    $width = $vb[0] * 0.75;
                }
                if ($height <= 0.0) {
                    $height = $vb[1] * 0.75;
                }
            }
        }
        if ($width <= 0.0 || $height <= 0.0) {
            return;
        }
        try {
            $svgDoc = $this->inlineSvgAdapter()->adapt($element);
        } catch (\Throwable) {
            return;
        }
        // PDF y-axis runs bottom-up; the box geometry's `y` is the
        // top edge in CSS coords, so we flip relative to pageHeight.
        $pdfY = $this->pageHeight - $geo->y - $height;
        // SVG 2 §8.2 / CSS Overflow 3 §3.3 — the SVG viewport clips its
        // content by default (UA `svg { overflow: clip }`). SvgRenderer's
        // own draw() only clips for slice/preserveAspectRatio, so the
        // caller must confine content larger than the viewport (a 150×150
        // rect in a 100×100 svg). Honour per-axis overflow, contain:paint,
        // and overflow-clip-margin via the shared overflow-clip machinery.
        $needsClip = $this->shouldOverflowClip($box);
        if ($needsClip) {
            $stream->saveGraphicsState();
            $this->emitOverflowClipPath($stream, $box);
        }
        $this->svgRenderer()->draw(
            $svgDoc,
            $geo->x,
            $pdfY,
            $width,
            $height,
            stream: $stream,
        );
        if ($needsClip) {
            $stream->restoreGraphicsState();
        }
    }

    /**
     * Pull the width/height columns out of an SVG `viewBox` attribute
     * for the intrinsic-sizing fallback. Returns `[width, height]` in
     * CSS pixels (viewBox values are unitless user-space coordinates,
     * which in the absence of any other sizing input are interpreted
     * as CSS pixels per SVG 2 §8.2). Returns null on parse failure.
     *
     * @return array{0: float, 1: float}|null
     */
    private function parseViewBox(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];
        if (count($parts) !== 4) {
            return null;
        }
        foreach ($parts as $p) {
            if (!is_numeric($p)) {
                return null;
            }
        }
        $w = (float) $parts[2];
        $h = (float) $parts[3];
        if ($w <= 0.0 || $h <= 0.0) {
            return null;
        }
        return [$w, $h];
    }

    /**
     * Tiny SVG-length parser used by the inline-SVG fallback path.
     * Strips an optional `px` / `pt` suffix and clamps to a non-
     * negative float. We accept only `px` and `pt` for now — any
     * other unit (cm, em, %) returns 0 and lets the dimension lookup
     * fall through to "skip".
     */
    private function parseSvgLength(string $value): float
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0.0;
        }
        if (preg_match('/^([\d.]+)\s*(px|pt)?$/i', $trimmed, $m) !== 1) {
            return 0.0;
        }
        $n = (float) $m[1];
        if ($n <= 0.0) {
            return 0.0;
        }
        // `pt` arrives as PDF points already; `px` and the bare form
        // are CSS pixels at 96 dpi, which is 0.75 pt per px.
        $unit = isset($m[2]) ? strtolower($m[2]) : 'px';
        return $unit === 'pt' ? $n : $n * 0.75;
    }

    private function paintBorders(Box $box, ContentStream $stream): void
    {
        // CSS Backgrounds 3 §6 — when `border-image-source` is set and
        // successfully loaded, it REPLACES the per-side border paint.
        // We delegate to the 9-slice painter and skip the legacy
        // border-colour/style rendering for this box.
        if ($this->paintBorderImage($box, $stream)) {
            return;
        }
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
     * centerline of the side, at line-width = thickness. CSS
     * Backgrounds 3 §5 says the dash / dot geometry is
     * implementation-defined; we follow the Chromium + WebKit
     * convention:
     *   - dashed: dash-length = 2w, gap = w (period 3w, butt cap).
     *   - dotted: round-capped point-dashes spaced 2w apart, so each
     *     "dot" renders as a circle of diameter = thickness. The
     *     stroke is inset by half the thickness on each end so the
     *     leading dot sits at the centre-line crossing of the two
     *     meeting borders (matching the corner geometry browsers
     *     emit).
     * The period for both styles is rounded to fit the edge in whole
     * cycles so dashes / dots stay symmetric across the corner; this
     * is what closes the residual ~14% pixel-AE vs Chromium on the
     * dashed / dotted fixtures (issue #27).
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
        $edgeLength = $axis === 'horizontal' ? $width : $height;
        if ($edgeLength <= 0.0) {
            return;
        }
        $stream->saveGraphicsState();
        $stream->setStrokeColorRGB($color->r, $color->g, $color->b);
        $stream->setLineWidth($thickness);

        if ($styleName === 'dotted') {
            // Round-capped point-dash → each "on" of length 0 paints a
            // full-thickness circle. Inset by thickness/2 so the first
            // and last dot land on the corner of the two borders'
            // centre-lines.
            $insetEdge = max($edgeLength - $thickness, 0.0);
            $targetPeriod = $thickness * 2.0;
            $count = max(1, (int) round($insetEdge / $targetPeriod));
            $period = $count > 0 ? $insetEdge / $count : $targetPeriod;
            $stream->setLineCap(1);
            $stream->setDashPattern([0.0, $period], 0);
            [$startX, $startY, $endX, $endY] = $this->dashedSideEndpoints(
                $axis,
                $x,
                $y,
                $width,
                $height,
                $thickness / 2.0,
            );
        } else {
            // Dashed — dash:gap = 2:1, period rounded to whole cycles.
            $targetPeriod = $thickness * 3.0;
            $count = max(1, (int) round($edgeLength / $targetPeriod));
            $period = $edgeLength / $count;
            $dash = $period * 2.0 / 3.0;
            $gap = $period - $dash;
            $stream->setDashPattern([$dash, $gap], 0);
            [$startX, $startY, $endX, $endY] = $this->dashedSideEndpoints(
                $axis,
                $x,
                $y,
                $width,
                $height,
                0.0,
            );
        }
        $stream->moveTo($startX, $startY);
        $stream->lineTo($endX, $endY);
        $stream->stroke();
        $stream->restoreGraphicsState();
    }

    /**
     * Compute the PDF-space stroke endpoints for one dashed / dotted
     * border side, optionally inset by `$inset` units along the axis
     * (so the leading dot of a dotted edge sits at the corner of the
     * two borders' centre-lines rather than poking past it).
     *
     * @return array{float, float, float, float}
     */
    private function dashedSideEndpoints(
        string $axis,
        float $x,
        float $y,
        float $width,
        float $height,
        float $inset,
    ): array {
        if ($axis === 'horizontal') {
            $midPdfY = $this->pageHeight - ($y + $height / 2.0);
            return [$x + $inset, $midPdfY, $x + $width - $inset, $midPdfY];
        }
        $midX = $x + $width / 2.0;
        $topPdfY = $this->pageHeight - $y - $inset;
        $bottomPdfY = $this->pageHeight - ($y + $height) + $inset;
        return [$midX, $topPdfY, $midX, $bottomPdfY];
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
        $width = match (true) {
            $widthValue instanceof \Phpdftk\Css\Value\Length => max(0.0, $widthValue->value),
            // CSS Backgrounds 3 §4.4 keyword resolution.
            $widthValue instanceof \Phpdftk\Css\Value\Keyword => match (strtolower($widthValue->name)) {
                'thin' => 1.0,
                'medium' => 3.0,
                'thick' => 5.0,
                default => 0.0,
            },
            default => 0.0,
        };
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
        if ($styleName === 'dashed' || $styleName === 'dotted') {
            $stream->setDashPattern(
                $styleName === 'dashed' ? [$width * 3, $width * 2] : [$width, $width * 1.5],
                0,
            );
            if ($color->a < 0.999 && $this->page !== null) {
                $stream->setGraphicsState($this->page->ensureOpacityState($color->a, $color->a));
            }
            $stream->rectangle($outerX, $pdfY, $outerWidth, $outerHeight);
            $stream->stroke();
            $stream->restoreGraphicsState();
            return;
        }
        // CSS UI 3 §4 — a `solid` outline (and the groove/ridge/inset/outset
        // fallbacks) is a SOLID FILLED BAND from the box's outer outline edge
        // to its inner edge (even-odd fill), NOT a centred stroke: this fills
        // correctly for large outline widths and honours negative
        // outline-offset (which a centred stroke inverts/leaks through).
        $bbX = $geo->x - $geo->paddingLeft - $geo->borderLeft;
        $bbY = $geo->y - $geo->paddingTop - $geo->borderTop;
        $bbW = $geo->paddingLeft + $geo->width + $geo->paddingRight
            + $geo->borderLeft + $geo->borderRight;
        $bbH = $geo->paddingTop + $geo->height + $geo->paddingBottom
            + $geo->borderTop + $geo->borderBottom;
        // A negative outline-offset shrinks the inner edge; clamp it to a
        // non-negative size so the outer shape never drops below 2×width
        // (CSS UI 3 — outline-013). The band is always `width` thick per side,
        // so the outer size is the clamped inner size + 2×width; centre both
        // on the box so the non-clamped case still lands on the real edges.
        $innW = max(0.0, $bbW + 2.0 * $offset);
        $innH = max(0.0, $bbH + 2.0 * $offset);
        $outW = $innW + 2.0 * $width;
        $outH = $innH + 2.0 * $width;
        $cx = $bbX + $bbW / 2.0;
        $cy = $bbY + $bbH / 2.0;
        $outLeft = $cx - $outW / 2.0;
        $outTop = $cy - $outH / 2.0;
        $innLeft = $cx - $innW / 2.0;
        $innTop = $cy - $innH / 2.0;
        if ($color->a < 0.999 && $this->page !== null) {
            $stream->setGraphicsState($this->page->ensureOpacityState($color->a, $color->a));
        }
        $stream->setFillColorRGB($color->r, $color->g, $color->b);
        $stream->rectangle($outLeft, $this->pageHeight - $outTop - $outH, $outW, $outH);
        $stream->rectangle($innLeft, $this->pageHeight - $innTop - $innH, $innW, $innH);
        $stream->fillEvenOdd();
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

    /**
     * CSS Multi-column 1 §3.3 — paint a `column-fill: auto` fragmented
     * container. Its content was laid out in one tall column at the
     * container's left edge (layout y from `contentTop` down); render it
     * once per column, clipping to column `i`'s `columnHeight` band and
     * translating band `i` up into that column. Bands past the content
     * height simply clip to empty.
     */
    private function paintFragmentedColumns(Box $box, ContentStream $stream, MultiColumnLayout $mc): void
    {
        $colW = $mc->columnWidth;
        $gap = $mc->columnGap;
        $height = $mc->columnHeight;
        if ($height <= 0.0 || $colW <= 0.0) {
            // Degenerate — fall back to a single unsliced pass.
            foreach ($this->paintOrderChildren($box) as $child) {
                $this->paintBox($child, $stream, $box);
            }
            return;
        }
        $baseX = $box->geometry->x;
        $children = $this->paintOrderChildren($box);
        if ($mc->columnWrap) {
            // CSS Multi-column 2 §3 — `column-wrap: wrap`: slice the tall
            // content into bands of `columnHeight` and place band `i` at grid
            // cell (col = i mod columnCount, row = i div columnCount), filling
            // a row of columns left→right then wrapping to the next row.
            $rowGap = $mc->rowGap;
            $bands = (int) ceil($mc->contentHeight / $height - 1e-6);
            $bands = max(1, $bands);
            for ($i = 0; $i < $bands; $i++) {
                $col = $i % $mc->columnCount;
                $row = intdiv($i, $mc->columnCount);
                $colX = $baseX + $col * ($colW + $gap);
                $rowTop = $mc->contentTop + $row * ($height + $rowGap);
                $stream->saveGraphicsState();
                $clipPdfY = $this->pageHeight - ($rowTop + $height);
                $stream->rectangle($colX, $clipPdfY, $colW, $height);
                $stream->clip();
                $stream->endPath();
                // Band i (tall layout-y = contentTop + i·height) → grid cell:
                // PDF translate dy = i·height − row·(height+rowGap).
                $dy = $i * $height - $row * ($height + $rowGap);
                $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, $col * ($colW + $gap), $dy);
                foreach ($children as $child) {
                    $this->paintBox($child, $stream, $box);
                }
                $stream->restoreGraphicsState();
            }
            return;
        }
        for ($i = 0; $i < $mc->columnCount; $i++) {
            $colX = $baseX + $i * ($colW + $gap);
            $stream->saveGraphicsState();
            // Clip to column i's band: layout rect [colX, contentTop, colW,
            // height] → PDF y = pageHeight − (contentTop + height).
            $clipPdfY = $this->pageHeight - ($mc->contentTop + $height);
            $stream->rectangle($colX, $clipPdfY, $colW, $height);
            $stream->clip();
            $stream->endPath();
            // Translate band i into this column: layout (dx = i·(colW+gap),
            // dy = −i·height) → PDF (dx, +i·height).
            if ($i > 0) {
                $stream->concatMatrix(1.0, 0.0, 0.0, 1.0, $i * ($colW + $gap), $i * $height);
            }
            foreach ($children as $child) {
                $this->paintBox($child, $stream, $box);
            }
            $stream->restoreGraphicsState();
        }
    }

    /**
     * CSS Gaps 1 — paint `column-rule` (vertical) and `row-rule`
     * (horizontal) decorations in the gaps of a grid container. Each
     * rule is centred in its gap and spans the full track area on the
     * cross axis. Reuses the multicol rule-stroking convention (solid /
     * dashed / dotted; other styles approximate to solid).
     */
    /**
     * CSS Gaps 1 — true when a grid gap rule on the given axis is a plain
     * continuous line, i.e. the default `spanning-item` break with no
     * per-item visibility restriction. The grid painter only strokes the
     * full-track span, which matches the reference only in this case; the
     * segmented forms (`intersection`/`none` break, `around`/`between`
     * visibility) are not yet modelled, so callers skip that axis instead
     * of painting a wrong continuous rule.
     */
    private function gapRuleIsContinuous(Box $box, string $prefix): bool
    {
        $breakV = $box->style->get("$prefix-break");
        $break = $breakV instanceof Keyword ? strtolower($breakV->name) : 'spanning-item';
        if ($break !== 'spanning-item') {
            return false;
        }
        $visV = $box->style->get("$prefix-visibility-items");
        $vis = $visV instanceof Keyword ? strtolower($visV->name) : 'all';
        return $vis === 'all';
    }

    private function paintGridGapRules(Box $box, ContentStream $stream): void
    {
        if (!$box instanceof \Phpdftk\HtmlToPdf\Box\GridBox) {
            return;
        }
        if ($box->columnGapCenters === [] && $box->rowGapCenters === []) {
            return;
        }
        $stream->saveGraphicsState();
        // CSS Gaps 1 + CSS Overflow 3 — a scroll container (`overflow`
        // other than `visible`) clips its gap decorations to the padding
        // box, so an overflowing grid's rules don't extend past the
        // visible edge (WPT grid-gap-decorations 035-037 clip a 320px
        // track area to a 130px box). `overflow: visible` intentionally
        // lets rule ink spill past the container (029/090/091), so only
        // clip when overflow is not visible.
        if ($this->shouldOverflowClip($box)) {
            $g = $box->geometry;
            $padLeft = $g->x - $g->paddingLeft;
            $padTop = $g->y - $g->paddingTop;
            $padWidth = $g->paddingLeft + $g->width + $g->paddingRight;
            $padHeight = $g->paddingTop + $g->height + $g->paddingBottom;
            $stream->rectangle($padLeft, $this->pageHeight - $padTop - $padHeight, $padWidth, $padHeight);
            $stream->clip();
            $stream->endPath();
        }
        // Column rules: vertical lines, spanning the grid's row-track area.
        if ($box->columnGapCenters !== [] && $box->gridContentBottom > $box->gridContentTop) {
            $pdfTop = $this->pageHeight - $box->gridContentTop;
            $pdfBottom = $this->pageHeight - $box->gridContentBottom;
            foreach ($box->columnGapCenters as $cx) {
                $this->strokeGapRule($box, $stream, 'column-rule', $cx, $pdfBottom, $cx, $pdfTop);
            }
        }
        // Row rules: horizontal lines, spanning the grid's column-track area.
        // The naive full-span stroke below is only correct for a continuous
        // rule: the default `spanning-item` break with no per-item visibility
        // restriction. When the author opts into segmentation we do not yet
        // model (`*-rule-break: intersection`/`none`, or a non-default
        // `*-rule-visibility-items`), painting a continuous rule is worse
        // than painting none — skip that axis rather than draw a wrong rule.
        if ($box->rowGapCenters !== [] && $box->gridContentRight > $box->gridContentLeft
            && $this->gapRuleIsContinuous($box, 'row-rule')) {
            foreach ($box->rowGapCenters as $cy) {
                $pdfY = $this->pageHeight - $cy;
                $this->strokeGapRule(
                    $box,
                    $stream,
                    'row-rule',
                    $box->gridContentLeft,
                    $pdfY,
                    $box->gridContentRight,
                    $pdfY,
                );
            }
        }
        $stream->restoreGraphicsState();
    }

    /**
     * CSS Gaps 1 — paint the `column-rule` / `row-rule` decorations in a
     * flex container's gaps. The segments (in top-down layout space) were
     * resolved per flex line during layout; here they are Y-flipped into
     * PDF space and stroked with the shared {@see strokeGapRule()} helper.
     * No-op for non-flex boxes or when no segments were recorded.
     */
    private function paintFlexGapRules(Box $box, ContentStream $stream): void
    {
        if (!$box instanceof \Phpdftk\HtmlToPdf\Box\FlexBox) {
            return;
        }
        if ($box->gapRuleSegments === []) {
            return;
        }
        $stream->saveGraphicsState();
        // Mirror the grid painter: a scroll container (`overflow` other
        // than `visible`) clips its gap decorations to the padding box.
        if ($this->shouldOverflowClip($box)) {
            $g = $box->geometry;
            $padLeft = $g->x - $g->paddingLeft;
            $padTop = $g->y - $g->paddingTop;
            $padWidth = $g->paddingLeft + $g->width + $g->paddingRight;
            $padHeight = $g->paddingTop + $g->height + $g->paddingBottom;
            $stream->rectangle($padLeft, $this->pageHeight - $padTop - $padHeight, $padWidth, $padHeight);
            $stream->clip();
            $stream->endPath();
        }
        foreach ($box->gapRuleSegments as $seg) {
            $this->strokeGapRule(
                $box,
                $stream,
                $seg['prefix'],
                $seg['x1'],
                $this->pageHeight - $seg['y1'],
                $seg['x2'],
                $this->pageHeight - $seg['y2'],
            );
        }
        $stream->restoreGraphicsState();
    }

    /**
     * Stroke a single gap-decoration rule between two PDF-space points,
     * reading `<prefix>-width` / `-style` / `-color` off the box. No-op
     * when the rule is invisible (zero width, `none` / `hidden` style, or
     * a fully transparent colour).
     */
    private function strokeGapRule(
        Box $box,
        ContentStream $stream,
        string $prefix,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
    ): void {
        $styleV = $box->style->get("$prefix-style");
        $styleName = $styleV instanceof Keyword ? strtolower($styleV->name) : 'none';
        if ($styleName === 'none' || $styleName === 'hidden') {
            return;
        }
        $widthV = $box->style->get("$prefix-width");
        $width = $widthV instanceof \Phpdftk\Css\Value\Length ? $widthV->value : 0.0;
        if ($width <= 0.0) {
            return;
        }
        $color = $this->ruleColor($box, "$prefix-color");
        if ($color->a <= 0.0) {
            return;
        }
        $stream->saveGraphicsState();
        $stream->setStrokeColorRGB($color->r, $color->g, $color->b);
        $stream->setLineWidth($width);
        if ($styleName === 'dashed') {
            $stream->setDashPattern([$width * 3, $width * 2], 0);
        } elseif ($styleName === 'dotted') {
            $stream->setDashPattern([$width, $width * 1.5], 0);
        }
        $stream->moveTo($x1, $y1);
        $stream->lineTo($x2, $y2);
        $stream->stroke();
        $stream->restoreGraphicsState();
    }

    /**
     * Resolve a gap-rule colour property, mapping `currentcolor` to the
     * box's `color`. Mirrors {@see borderColor()}.
     */
    private function ruleColor(Box $box, string $prop): Color
    {
        $color = $box->style->get($prop);
        if ($color instanceof Color) {
            return $color;
        }
        $current = $box->style->get('color');
        if ($current instanceof Color) {
            return $current;
        }
        return new Color(0, 0, 0, 1);
    }

    private function borderIsVisible(Box $box, string $side): bool
    {
        $style = $box->style->get("border-$side-style");
        if (!$style instanceof Keyword) {
            return false;
        }
        $lower = strtolower($style->name);
        if ($lower === 'none' || $lower === 'hidden') {
            return false;
        }
        // CSS Backgrounds 3 §4.4: a fully transparent border colour
        // contributes nothing visible — skipping the paint avoids drawing
        // a black bar where the alpha=0 value would otherwise resolve
        // through the DeviceRGB `rg` operator (which has no alpha).
        return $this->borderColor($box, $side)->a > 0.0;
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
        // CSS Color §10 — translucent fills (Color::a < 1) need to
        // composite over whatever's underneath; that's an ExtGState
        // dictionary in PDF with /ca for non-stroke and /CA for
        // stroke. Without it the painter would emit a fully-opaque
        // rect even when the cascaded color said e.g. `rgba(0,0,0,
        // 0.6)`, masking the background entirely. WPT t422-rgba-*
        // exercise this with checkerboards behind translucent bands.
        if ($fill->a < 0.999 && $this->page !== null) {
            $alphaName = $this->page->ensureOpacityState($fill->a, $fill->a);
            $stream->setGraphicsState($alphaName);
        }
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
     * Per-corner border radii as `[horizontal, vertical]` pairs (CSS
     * Backgrounds 3 §5.1). Resolves `<length>`, `<percentage>` (horizontal %
     * of `$width`, vertical % of `$height`) and the two-value elliptical form
     * (`border-top-right-radius: 75px 50px`). Order: [TL, TR, BR, BL]. A
     * single-value corner yields a circular `[r, r]`, so this is a superset
     * of {@see borderRadii}.
     *
     * @return array{array{float,float}, array{float,float}, array{float,float}, array{float,float}}
     */
    private function borderRadiiXY(Box $box, float $width, float $height): array
    {
        $corner = function (string $name) use ($box, $width, $height): array {
            $v = $box->style->get($name);
            if ($v instanceof \Phpdftk\Css\Value\ValueList && $v->values !== []) {
                $h = $v->values[0];
                $vv = $v->values[1] ?? $v->values[0];
                return [
                    $this->resolveRadiusComponent($h, $width),
                    $this->resolveRadiusComponent($vv, $height),
                ];
            }
            return [
                $this->resolveRadiusComponent($v, $width),
                $this->resolveRadiusComponent($v, $height),
            ];
        };
        return [
            $corner('border-top-left-radius'),
            $corner('border-top-right-radius'),
            $corner('border-bottom-right-radius'),
            $corner('border-bottom-left-radius'),
        ];
    }

    private function resolveRadiusComponent(?\Phpdftk\Css\Value\Value $v, float $extent): float
    {
        if ($v instanceof \Phpdftk\Css\Value\Length) {
            return max(0.0, $v->value);
        }
        if ($v instanceof \Phpdftk\Css\Value\Percentage) {
            return max(0.0, $v->value / 100.0 * $extent);
        }
        return 0.0;
    }

    /**
     * @param array<array{float,float}> $radii
     */
    private function radiiAnyPositive(array $radii): bool
    {
        foreach ($radii as $r) {
            if ($r[0] > 0.0 || $r[1] > 0.0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Emit a rounded-rectangle fill path using cubic Béziers at the four
     * corners. Topology in layout-Y (top-down) with the painter's flip
     * applied at emission time. The 0.5522847498 constant is the standard
     * cubic-Bézier circle approximation factor.
     *
     * Each corner is an `[rx, ry]` pair (elliptical corners, CSS Backgrounds
     * 3 §5.1); `rx == ry` gives the circular case (byte-identical to the old
     * scalar path).
     *
     * @param array{array{float,float}, array{float,float}, array{float,float}, array{float,float}} $radii [tl, tr, br, bl]
     * @param list<string> $shapes [tl, tr, br, bl] corner shapes; empty → all round
     */
    private function emitRoundedFill(
        ContentStream $stream,
        float $x,
        float $topY,
        float $width,
        float $height,
        array $radii,
        Color $fill,
        array $shapes = [],
    ): void {
        $stream->saveGraphicsState();
        $stream->setFillColorRGB($fill->r, $fill->g, $fill->b);
        $this->buildRoundedRectPath($stream, $x, $topY, $width, $height, $radii, $shapes);
        $stream->fill();
        $stream->restoreGraphicsState();
    }

    /**
     * Emit a rounded-rectangle SUBPATH (moveTo → lineTo/curveTo → closePath)
     * in PDF coords, without any graphics-state / colour / paint operator, so
     * callers can either fill or `clip` it. Each corner is an `[rx, ry]` pair
     * (elliptical). Layout-Y (top-down) in, PDF-Y flip applied here.
     *
     * Each corner may carry a CSS Borders 4 `corner-shape` (`$shapes` as
     * `[tl, tr, br, bl]` strings): `round` (arc, the default), `bevel`
     * (straight chamfer), `square` (fills to the box corner), `notch`
     * (inward square), or `scoop` (concave arc). An empty `$shapes` keeps
     * every corner round (byte-identical to the pre-corner-shape path).
     *
     * @param array{array{float,float}, array{float,float}, array{float,float}, array{float,float}} $radii [tl, tr, br, bl]
     * @param list<string> $shapes [tl, tr, br, bl] corner shapes; empty → all round
     */
    private function buildRoundedRectPath(
        ContentStream $stream,
        float $x,
        float $topY,
        float $width,
        float $height,
        array $radii,
        array $shapes = [],
    ): void {
        $clamp = static fn(array $r): array => [
            min(max(0.0, $r[0]), $width / 2.0),
            min(max(0.0, $r[1]), $height / 2.0),
        ];
        [$tl, $tr, $br, $bl] = array_map($clamp, $radii);
        $shapeTl = $shapes[0] ?? 'round';
        $shapeTr = $shapes[1] ?? 'round';
        $shapeBr = $shapes[2] ?? 'round';
        $shapeBl = $shapes[3] ?? 'round';
        $bottomPdfY = $this->pageHeight - $topY - $height;
        $topPdfY = $this->pageHeight - $topY;
        // Walk clockwise starting at the top-left straight edge. Each corner
        // runs from its incoming edge point (A) to the outgoing edge point (B),
        // shaped around either the box corner (convex) or the inner corner
        // (concave) per `corner-shape`.
        $stream->moveTo($x + $tl[0], $topPdfY);
        $stream->lineTo($x + $width - $tr[0], $topPdfY);
        $this->emitCornerSegment(
            $stream,
            $shapeTr,
            $x + $width - $tr[0],
            $topPdfY,
            $x + $width,
            $topPdfY - $tr[1],
            $x + $width,
            $topPdfY,
        );
        $stream->lineTo($x + $width, $bottomPdfY + $br[1]);
        $this->emitCornerSegment(
            $stream,
            $shapeBr,
            $x + $width,
            $bottomPdfY + $br[1],
            $x + $width - $br[0],
            $bottomPdfY,
            $x + $width,
            $bottomPdfY,
        );
        $stream->lineTo($x + $bl[0], $bottomPdfY);
        $this->emitCornerSegment(
            $stream,
            $shapeBl,
            $x + $bl[0],
            $bottomPdfY,
            $x,
            $bottomPdfY + $bl[1],
            $x,
            $bottomPdfY,
        );
        $stream->lineTo($x, $topPdfY - $tl[1]);
        $this->emitCornerSegment(
            $stream,
            $shapeTl,
            $x,
            $topPdfY - $tl[1],
            $x + $tl[0],
            $topPdfY,
            $x,
            $topPdfY,
        );
        $stream->closePath();
    }

    /**
     * Emit one corner of {@see buildRoundedRectPath()} from the current point
     * A=(ax,ay) to B=(bx,by), shaped per `corner-shape`. `(boxX,boxY)` is the
     * physical box corner (the convex direction); the inner corner (concave
     * direction) is its reflection A+B−box. The 0.5522847498 constant is the
     * standard cubic-Bézier quarter-ellipse factor.
     */
    private function emitCornerSegment(
        ContentStream $stream,
        string $shape,
        float $ax,
        float $ay,
        float $bx,
        float $by,
        float $boxX,
        float $boxY,
    ): void {
        // Zero-radius corner: A == B, nothing to add (the edge lineTos meet
        // at the box corner already).
        if ($ax === $bx && $ay === $by) {
            return;
        }
        $k = 0.5522847498;
        $innerX = $ax + $bx - $boxX;
        $innerY = $ay + $by - $boxY;
        switch ($shape) {
            case 'bevel':
                $stream->lineTo($bx, $by);
                return;
            case 'square':
                $stream->lineTo($boxX, $boxY);
                $stream->lineTo($bx, $by);
                return;
            case 'notch':
                $stream->lineTo($innerX, $innerY);
                $stream->lineTo($bx, $by);
                return;
            case 'scoop':
                // Concave quarter: pull the Bézier handles toward the inner
                // corner instead of the box corner.
                $stream->curveTo(
                    $ax + $k * ($innerX - $ax),
                    $ay + $k * ($innerY - $ay),
                    $bx + $k * ($innerX - $bx),
                    $by + $k * ($innerY - $by),
                    $bx,
                    $by,
                );
                return;
            case 'round':
            case 'squircle':
            default:
                // Convex quarter-ellipse toward the box corner.
                $stream->curveTo(
                    $ax + $k * ($boxX - $ax),
                    $ay + $k * ($boxY - $ay),
                    $bx + $k * ($boxX - $bx),
                    $by + $k * ($boxY - $by),
                    $bx,
                    $by,
                );
                return;
        }
    }

    /**
     * CSS Borders 4 §5 — resolve `corner-shape` into `[tl, tr, br, bl]`
     * shape names (the 1-4 value shorthand expanded like `border-radius`).
     * `superellipse(<n>)` maps to the nearest implemented keyword by its
     * exponent (large → square, ~1 → round, ~0 → bevel, very negative →
     * notch). Absent / unrecognised → all `round`.
     *
     * @return list<string> [tl, tr, br, bl]
     */
    private function cornerShapes(Box $box): array
    {
        $value = $box->style->get('corner-shape');
        if ($value === null) {
            return ['round', 'round', 'round', 'round'];
        }
        $items = $value instanceof \Phpdftk\Css\Value\ValueList ? $value->values : [$value];
        $shapes = [];
        foreach ($items as $item) {
            $shapes[] = $this->cornerShapeName($item);
        }
        return match (count($shapes)) {
            0 => ['round', 'round', 'round', 'round'],
            1 => [$shapes[0], $shapes[0], $shapes[0], $shapes[0]],
            2 => [$shapes[0], $shapes[1], $shapes[0], $shapes[1]],
            3 => [$shapes[0], $shapes[1], $shapes[2], $shapes[1]],
            default => [$shapes[0], $shapes[1], $shapes[2], $shapes[3]],
        };
    }

    private function cornerShapeName(\Phpdftk\Css\Value\Value $item): string
    {
        if ($item instanceof Keyword) {
            $name = strtolower($item->name);
            if (in_array($name, ['round', 'bevel', 'square', 'notch', 'scoop', 'squircle'], true)) {
                return $name;
            }
            return 'round';
        }
        if ($item instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($item->name) === 'superellipse'
        ) {
            $s = $this->superellipseExponent($item->arguments[0] ?? null);
            return match (true) {
                $s >= 3.0 => 'square',
                $s >= 1.5 => 'squircle',
                $s >= 0.5 => 'round',
                $s >= -0.5 => 'bevel',
                $s >= -1.5 => 'scoop',
                default => 'notch',
            };
        }
        return 'round';
    }

    private function superellipseExponent(?\Phpdftk\Css\Value\Value $arg): float
    {
        if ($arg instanceof Keyword) {
            $name = strtolower($arg->name);
            if ($name === 'infinity') {
                return INF;
            }
            if ($name === '-infinity') {
                return -INF;
            }
        }
        if ($arg instanceof \Phpdftk\Css\Value\Integer || $arg instanceof \Phpdftk\Css\Value\Number) {
            return (float) $arg->value;
        }
        if ($arg instanceof \Phpdftk\Css\Value\Length) {
            return $arg->value;
        }
        return 1.0;
    }

    /**
     * Reduce per-corner border radii from the border box inward to a
     * reference box (CSS Backgrounds 3 §5.3): each corner's rx shrinks by the
     * border (+ padding for content-box) on its horizontal side, ry by the
     * vertical side. `border-box` / `margin-box` pass through unchanged.
     *
     * @param array{array{float,float}, array{float,float}, array{float,float}, array{float,float}} $radii
     * @return array{array{float,float}, array{float,float}, array{float,float}, array{float,float}}
     */
    private function reduceRadiiToBox(array $radii, string $refBox, BoxGeometry $g): array
    {
        if ($refBox !== 'padding-box' && $refBox !== 'content-box') {
            return $radii;
        }
        $insL = $g->borderLeft;
        $insT = $g->borderTop;
        $insR = $g->borderRight;
        $insB = $g->borderBottom;
        if ($refBox === 'content-box') {
            $insL += $g->paddingLeft;
            $insT += $g->paddingTop;
            $insR += $g->paddingRight;
            $insB += $g->paddingBottom;
        }
        $sub = static fn(float $r, float $i): float => max(0.0, $r - $i);
        return [
            [$sub($radii[0][0], $insL), $sub($radii[0][1], $insT)], // TL
            [$sub($radii[1][0], $insR), $sub($radii[1][1], $insT)], // TR
            [$sub($radii[2][0], $insR), $sub($radii[2][1], $insB)], // BR
            [$sub($radii[3][0], $insL), $sub($radii[3][1], $insB)], // BL
        ];
    }

    /**
     * A `border-collapse: collapse` table cell whose border must paint in the
     * late (post-content) phase so a descendant cannot overpaint the collapsed
     * table border (CSS Tables 3 §4.3 painting order).
     */
    private function isCollapsedBorderCell(Box $box): bool
    {
        if (!($box instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox)) {
            return false;
        }
        $bc = $box->style->get('border-collapse');
        return $bc instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($bc->name) === 'collapse';
    }
}
