<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Painter;

use Phpdftk\Css\Cascade\WritingMode;
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
            );
        }
        if ($hasGradient) {
            $this->paintLinearGradient($bgImage, $stream, 0.0, 0.0, $this->pageWidth, $this->pageHeight);
        }
        if ($hasRadial) {
            $this->paintRadialGradient($bgImage, $stream, 0.0, 0.0, $this->pageWidth, $this->pageHeight);
        }
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
        return $bgImage instanceof \Phpdftk\Css\Value\Url
            || $bgImage instanceof \Phpdftk\Css\Value\LinearGradient
            || $bgImage instanceof \Phpdftk\Css\Value\RadialGradient;
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
        if ($clipRect !== null) {
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
    private function paintImage(Box $box, ContentStream $stream): void
    {
        if (!($box instanceof \Phpdftk\HtmlToPdf\Box\AtomicInlineBox)) {
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
        if ($element->namespaceUri() === \Phpdftk\Svg\Parser::SVG_NS
            && strtolower($element->localName) === 'svg'
        ) {
            $this->paintInlineSvg($element, $box, $stream);
            return;
        }
        if ($element->namespaceUri() === \Phpdftk\Mathml\Parser::MATHML_NS
            && strtolower($element->localName) === 'math'
        ) {
            $this->paintInlineMath($element, $box, $stream);
            return;
        }
        if (strtolower($element->localName) !== 'img') {
            return;
        }
        $src = $element->getAttribute('src');
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
        // root, the root's effective overflow is the body's
        // (whichever axis the body constrained on).
        if ($box === $this->propagatedOverflowRoot && $this->propagatedOverflowBox !== null) {
            return $this->originalAxisClips($this->propagatedOverflowBox, $axis);
        }
        return $this->originalAxisClips($box, $axis);
    }

    private function originalAxisClips(Box $box, string $axis): bool
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
        if (!($clip instanceof \Phpdftk\Css\Value\CssFunction)
            || strtolower($clip->name) !== 'rect'
            || count($clip->arguments) !== 4
        ) {
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
        [$topV, $rightV, $bottomV, $leftV] = $clip->arguments;
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
            $columnTopLayoutY = $box->geometry->y + $line->y + $offsetY;
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
            $radii = $this->borderRadii($box);
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
            } elseif (array_sum($radii) > 0.0) {
                $this->emitRoundedFill($stream, $x, $top, $width, $height, $radii, $color);
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
                    $useTilePath = !$this->isDefaultGradientSize($sizeValue)
                        && $this->isNoRepeat($repeatValue);
                    if ($useTilePath) {
                        $tile = $this->computeGradientTileRect($sizeValue, $positionValue, $originRect);
                        $this->paintLinearGradient(
                            $layer,
                            $stream,
                            $tile['x'],
                            $tile['top'],
                            $tile['w'],
                            $tile['h'],
                            [$x, $top, $width, $height],
                        );
                    } else {
                        $this->paintLinearGradient($layer, $stream, $x, $top, $width, $height);
                    }
                } elseif ($layer instanceof \Phpdftk\Css\Value\RadialGradient) {
                    $this->paintRadialGradient($layer, $stream, $x, $top, $width, $height);
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
     * @return list<\Phpdftk\Css\Value\Url|\Phpdftk\Css\Value\LinearGradient|\Phpdftk\Css\Value\RadialGradient>
     */
    private function extractBackgroundLayers(mixed $value): array
    {
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Comma
        ) {
            $layers = [];
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Url
                    || $v instanceof \Phpdftk\Css\Value\LinearGradient
                    || $v instanceof \Phpdftk\Css\Value\RadialGradient
                ) {
                    $layers[] = $v;
                }
            }
            return $layers;
        }
        if ($value instanceof \Phpdftk\Css\Value\Url
            || $value instanceof \Phpdftk\Css\Value\LinearGradient
            || $value instanceof \Phpdftk\Css\Value\RadialGradient
        ) {
            return [$value];
        }
        return [];
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
        // For radial, the gradient line is from centre to the ending
        // shape — its length is the outer radius (the larger axis).
        $stopList = $this->resolveGradientStops($gradient->stops, max($rx, $ry));
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
     * @param float $gradientLineLength Length of the gradient line in
     *   user units. Used to convert `<length>` stop positions to
     *   fractional [0, 1] offsets. Pass 0 to skip length-position
     *   resolution (degrades length-positioned stops to "no authored
     *   position" — the spec's interpolation algorithm fills them in).
     * @return list<array{offset: float, rgb: array{float, float, float}}>
     */
    private function resolveGradientStops(array $stops, float $gradientLineLength = 0.0): array
    {
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
        if ($this->writer === null || $gradient->stops === []) {
            return;
        }
        if ($tileWidth <= 0.0 || $tileHeight <= 0.0) {
            return;
        }
        [$clipX, $clipTop, $clipWidth, $clipHeight] = $clipRect
            ?? [$tileX, $tileTop, $tileWidth, $tileHeight];
        $tilePdfY = $this->pageHeight - $tileTop - $tileHeight;
        $clipPdfY = $this->pageHeight - $clipTop - $clipHeight;
        // CSS angle convention: 0deg points up, increases clockwise. The
        // gradient line passes through the centre of the *tile*. Compute
        // its start and end points on the tile's edge per CSS Images 3 §3.1.
        $angle = fmod($gradient->angleDeg, 360.0);
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
        $stopList ??= $this->resolveGradientStops($gradient->stops, $lineLength);
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
        // Clip first to the bg-clip rect, then fill at the (typically
        // smaller) tile rect. When tile == clip the two are identical
        // and the behaviour is byte-for-byte the same as before.
        $stream->rectangle($clipX, $clipPdfY, $clipWidth, $clipHeight);
        $stream->clip();
        $stream->endPath();
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
                    $stream->saveGraphicsState();
                    $stream->concatMatrix(
                        $tileW,
                        0.0,
                        0.0,
                        $tileH,
                        $originX + $offsetX,
                        $tileBottomY,
                    );
                    assert($name !== null);
                    $stream->doXObject($name);
                    $stream->restoreGraphicsState();
                }
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
     * Return true when `background-repeat` paints a *single tile*
     * (or close to it). Used to gate the gradient tile-rect path —
     * single-tile semantics only make sense when the gradient
     * actually paints once.
     *
     * Honoured as single-tile:
     *   • `no-repeat` — one tile, exact spec
     *   • `space` — degenerate to no-repeat when only one tile
     *     fits, which is the common case for small tiles in small
     *     boxes. Tests in this cluster use `space` with positioned
     *     tiles where exactly one tile fits — matches `no-repeat`
     *     visually until we ship a real `space` distributor.
     */
    private function isNoRepeat(?\Phpdftk\Css\Value\Value $repeatValue): bool
    {
        if ($repeatValue instanceof \Phpdftk\Css\Value\Keyword) {
            $kw = strtolower($repeatValue->name);
            return $kw === 'no-repeat' || $kw === 'space';
        }
        if ($repeatValue instanceof \Phpdftk\Css\Value\ValueList
            && $repeatValue->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $allNoRepeat = $repeatValue->values !== [];
            foreach ($repeatValue->values as $v) {
                if (!$v instanceof \Phpdftk\Css\Value\Keyword) {
                    $allNoRepeat = false;
                    break;
                }
                $kw = strtolower($v->name);
                if ($kw !== 'no-repeat' && $kw !== 'space') {
                    $allNoRepeat = false;
                    break;
                }
            }
            return $allNoRepeat;
        }
        return false;
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
     * @param array{x: float, top: float, width: float, height: float} $originRect
     * @return array{x: float, top: float, w: float, h: float}
     */
    private function computeGradientTileRect(
        ?\Phpdftk\Css\Value\Value $sizeValue,
        ?\Phpdftk\Css\Value\Value $positionValue,
        array $originRect,
    ): array {
        $originWidth = $originRect['width'];
        $originHeight = $originRect['height'];
        [$tileW, $tileH] = $this->resolveGradientTileSize(
            $sizeValue,
            $originWidth,
            $originHeight,
        );
        // resolveBackgroundPosition takes (image-w, image-h, box-w, box-h).
        // The gradient *tile* is the "image" being positioned within the
        // bg-positioning area (the "box"). When bg-position is null /
        // empty, the helper already defaults to 50%/50% — matches the
        // existing paintBackgroundImage handling.
        $offsets = $positionValue === null
            ? ['offsetX' => max(0.0, ($originWidth - $tileW) / 2),
                'offsetY' => max(0.0, ($originHeight - $tileH) / 2)]
            : $this->resolveBackgroundPosition(
                $positionValue,
                $tileW,
                $tileH,
                $originWidth,
                $originHeight,
            );
        return [
            'x' => $originRect['x'] + $offsets['offsetX'],
            'top' => $originRect['top'] + $offsets['offsetY'],
            'w' => $tileW,
            'h' => $tileH,
        ];
    }

    /**
     * Resolve a CSS `background-size` value to a concrete (w, h)
     * for a gradient (no intrinsic dimensions, no intrinsic ratio).
     * See {@see computeGradientTileRect} for the matrix this
     * implements.
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
        if ($st <= 0.0 && $sr <= 0.0 && $sb <= 0.0 && $sl <= 0.0) {
            return false;
        }

        $repeatMode = $this->parseBorderImageRepeat($box->style->get('border-image-repeat'));

        // Destination border area: the box's border-box rect.
        $geo = $box->geometry;
        $bx = $geo->x - $geo->paddingLeft - $geo->borderLeft;
        $by = $geo->y - $geo->paddingTop - $geo->borderTop;
        $bw = $geo->paddingLeft + $geo->width + $geo->paddingRight
            + $geo->borderLeft + $geo->borderRight;
        $bh = $geo->paddingTop + $geo->height + $geo->paddingBottom
            + $geo->borderTop + $geo->borderBottom;
        $bt = $geo->borderTop;
        $br = $geo->borderRight;
        $bb = $geo->borderBottom;
        $bl = $geo->borderLeft;
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

        // Edges — apply repeat mode to the tile axis only.
        if ($midSrcW > 0.0 && $midDstW > 0.0 && $bt > 0.0) {
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
                $repeatMode,
                horizontal: true,
            );
        }
        if ($midSrcW > 0.0 && $midDstW > 0.0 && $bb > 0.0) {
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
                $repeatMode,
                horizontal: true,
            );
        }
        if ($midSrcH > 0.0 && $midDstH > 0.0 && $bl > 0.0) {
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
                $repeatMode,
                horizontal: false,
            );
        }
        if ($midSrcH > 0.0 && $midDstH > 0.0 && $br > 0.0) {
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
                $repeatMode,
                horizontal: false,
            );
        }
        return true;
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
        // Tile dim along the variable axis; the other dim is fixed
        // (border thickness).
        $tileLen = $horizontal ? $dh * $sw / $sh : $dw * $sh / $sw;
        $axisDest = $horizontal ? $dw : $dh;
        if ($tileLen <= 0.0 || $axisDest <= 0.0) {
            return;
        }
        if ($repeatMode === 'round') {
            $n = max(1, (int) round($axisDest / $tileLen));
            $tileLen = $axisDest / $n;
        }
        // Iterate tiles. Clip to the destination edge so partial
        // tiles get cropped cleanly.
        $pdfDy = $this->pageHeight - $dy - $dh;
        $stream->saveGraphicsState();
        $stream->rectangle($dx, $pdfDy, $dw, $dh);
        $stream->clip();
        $stream->endPath();
        $maxTiles = 4096;
        $count = 0;
        if ($horizontal) {
            $cursor = 0.0;
            while ($cursor < $dw && $count++ < $maxTiles) {
                $this->emitImageSlice($stream, $imageName, $srcW, $srcH, $sx, $sy, $sw, $sh, $dx + $cursor, $dy, $tileLen, $dh);
                $cursor += $tileLen;
            }
        } else {
            $cursor = 0.0;
            while ($cursor < $dh && $count++ < $maxTiles) {
                $this->emitImageSlice($stream, $imageName, $srcW, $srcH, $sx, $sy, $sw, $sh, $dx, $dy + $cursor, $dw, $tileLen);
                $cursor += $tileLen;
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

    private function parseBorderImageRepeat(?\Phpdftk\Css\Value\Value $value): string
    {
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            $kw = strtolower($value->name);
            return match ($kw) {
                'repeat' => 'repeat',
                'round' => 'round',
                'space' => 'repeat', // approximate — true space distribution is a follow-up
                'stretch' => 'stretch',
                default => 'stretch',
            };
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList && $value->values !== []) {
            return $this->parseBorderImageRepeat($value->values[0]);
        }
        return 'stretch';
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
        \Phpdftk\HtmlToPdf\Box\AtomicInlineBox $box,
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
        \Phpdftk\HtmlToPdf\Box\AtomicInlineBox $box,
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
        \Phpdftk\HtmlToPdf\Box\AtomicInlineBox $box,
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
        \Phpdftk\HtmlToPdf\Box\AtomicInlineBox $box,
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
        $this->svgRenderer()->draw(
            $svgDoc,
            $geo->x,
            $pdfY,
            $width,
            $height,
            stream: $stream,
        );
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
