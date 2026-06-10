<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\FontParser\MathConstantsParser;
use Phpdftk\FontParser\MathGlyphInfoParser;
use Phpdftk\FontParser\MathVariantsParser;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use Phpdftk\Mathml\MathmlDocument;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\Page as CorePage;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Render a {@see MathmlDocument} onto a PDF page. Sibling to
 * {@see \Phpdftk\SvgToPdf\SvgRenderer}.
 *
 * Tracer-bullet scope (issue #30, slice 1): MathML Core tokens
 * (`<mn>`, `<mi>`, `<mo>`, `<ms>`, `<mtext>`) plus the `<mrow>`
 * container. Tokens render left-to-right on a single baseline in the
 * PDF standard Type 1 fonts (`Times-Roman` upright, `Times-Italic`
 * for single-character `<mi>` per Core §3.2.3). Unknown elements
 * round-trip through {@see \Phpdftk\Mathml\GenericElement} and
 * contribute their children's content but no extra layout.
 *
 * Out of scope for this slice (each is a separate follow-up):
 *
 *  - Fractions (`<mfrac>`), radicals (`<msqrt>`, `<mroot>`)
 *  - Scripts (`<msub>`, `<msup>`, `<msubsup>`, `<munder>`, `<mover>`,
 *    `<munderover>`, `<mmultiscripts>`)
 *  - Tables (`<mtable>`, `<mtr>`, `<mtd>`)
 *  - Spacing / framing (`<mpadded>`, `<mspace>`, `<menclose>`)
 *  - The MathML Operator Dictionary (operator-specific spacing,
 *    stretchiness, largeop / movablelimits behaviour)
 *  - Directionality (`<math dir="rtl">`)
 *  - OpenType MATH-table font features (italic correction, glyph
 *    variants, kerning) — the existing `phpdftk/font-parser`
 *    extension lands those alongside the script / radical work
 */
final class MathmlRenderer
{
    /**
     * Default em size for the rendered math. The CSS Math 1
     * recommendation is "the parent's effective font size"; with no
     * parent context to consult here, a reasonable typographic value
     * (12 pt) is picked so the tracer-bullet output is readable.
     */
    private const float DEFAULT_FONT_SIZE = 12.0;

    public function __construct(
        private readonly Page $page,
        private readonly PdfWriter $writer,
        private readonly Translator $translator = new Translator(),
        /**
         * Optional math metrics. When supplied (typically built from
         * an OpenType MATH-table font via
         * {@see MathmlMetricsFactory::fromMathFont()}), layout
         * constants flow from the font instead of the tracer-bullet
         * defaults. When null and no `$mathFontPath` is supplied,
         * paint behaviour is unchanged.
         */
        private readonly ?MathmlMetrics $mathMetrics = null,
        /**
         * Optional OpenType math-font path. When supplied, the
         * renderer loads the font, registers it on each draw() with
         * the document's used codepoints, and threads it through to
         * the painter so token glyphs render via the font's full
         * Unicode coverage instead of standard Times-Roman. Layout
         * metrics also come from the font (overriding any explicit
         * `$mathMetrics`).
         */
        private readonly ?string $mathFontPath = null,
    ) {}

    /**
     * Paint `$math` onto the renderer's page. Tokens flow horizontally
     * starting at `(x, y + height)` — i.e. the top of the box, with
     * the baseline `fontSize` points down so the first glyph sits
     * within the box. `width` / `height` are accepted but not yet used
     * for layout (no shrink-to-fit in this slice; tokens overflow if
     * they don't fit).
     *
     * @param ContentStream|null $stream Override the page's primary
     *   content stream. Same semantics as
     *   {@see \Phpdftk\SvgToPdf\SvgRenderer::draw()} — callers
     *   composing inside a graphics-state scope MUST pass the
     *   active stream so the math draw lands inside their wrap.
     */
    public function draw(
        MathmlDocument $math,
        float $x,
        float $y,
        ?float $width = null,
        ?float $height = null,
        ?ContentStream $stream = null,
    ): void {
        $stream ??= $this->page->contentStream();
        $fontSize = self::DEFAULT_FONT_SIZE;

        // Register the two standard faces for token rendering. The
        // writer dedupes by font identity — adding the same standard
        // font twice on the same page returns the same resource name,
        // so calling addFont per draw() is safe.
        $upright = $this->writer->addFont(
            new Type1Font(StandardFont::TimesRoman),
            $this->corePage(),
        );
        $italic = $this->writer->addFont(
            new Type1Font(StandardFont::TimesItalic),
            $this->corePage(),
        );

        // Optional math font: load + register against the document's
        // used codepoints. The PdfWriter subsets the CFF program so
        // only the glyphs we'll actually emit get embedded.
        [$mathFont, $effectiveMetrics] = $this->loadMathFontFor($math);

        // Baseline: position so the first glyph's top edge sits at
        // (x, y + height). PDF y grows upward; box top is y + height;
        // we drop by the font size to get a baseline that keeps the
        // glyph inside the requested rect.
        $boxHeight = $height ?? $fontSize;
        $baselineY = $y + $boxHeight - $fontSize;

        $stream->saveGraphicsState();
        $stream->beginText();
        // Activate the math font when present so token paint emits
        // through the right glyph table; otherwise stay with upright
        // Times-Roman for backwards compatibility.
        $stream->setFont($mathFont?->font ?? $upright, $fontSize);
        $stream->moveTextPosition($x, $baselineY);

        // Initial direction from <math dir>. Token elements deeper in
        // the tree can introduce their own boundaries via their `dir`
        // attribute; the Translator picks them up through paint().
        $ctx = new MathmlPaintContext(
            stream: $stream,
            upright: $upright,
            italic: $italic,
            fontSize: $fontSize,
            cursorX: $x,
            baselineY: $baselineY,
            direction: $math->dir() ?? 'ltr',
            metrics: $effectiveMetrics,
            mathFont: $mathFont,
        );
        $this->translator->paint($math, $ctx);

        $stream->endText();
        $stream->restoreGraphicsState();
    }

    /**
     * Load the math font (if configured), pre-scan the document for
     * used codepoints, register a Type 0 / CIDFontType0 stack
     * against the writer, and return both the math-font handle and
     * a populated {@see MathmlMetrics}.
     *
     * Returns [null, fallbackMetrics] when no math font is
     * configured.
     *
     * @return array{0: ?MathmlMathFont, 1: MathmlMetrics}
     */
    private function loadMathFontFor(MathmlDocument $math): array
    {
        if ($this->mathFontPath === null) {
            return [null, $this->mathMetrics ?? new MathmlMetrics()];
        }

        // Parse the font - accept raw OTF/TTF or WOFF1.
        $extension = strtolower(pathinfo($this->mathFontPath, PATHINFO_EXTENSION));
        if ($extension === 'woff') {
            $fontBytes = WoffParser::decompress($this->mathFontPath);
            $data = OpenTypeParser::fromBytes($fontBytes)->parse();
        } else {
            $data = (new OpenTypeParser($this->mathFontPath))->parse();
        }

        if ($data->mathTable === null || !$data->mathTable->hasMathConstants()) {
            throw new \RuntimeException(
                "Math font at {$this->mathFontPath} has no usable MATH table",
            );
        }

        // Collect codepoints first so the writer's CFF subsetter
        // produces a minimal embedded program.
        $codepoints = MathmlCodepointCollector::collect($math);

        // Parse MathVariants ahead of font registration so we can
        // collect the variant + assembly-part GIDs the painter will
        // want to render stretchy delimiters. Without these in the
        // subset, the variant lookups in MathmlMathFont would
        // succeed at the data layer but the variant GIDs wouldn't
        // map to any post-subset GID, so paintMo would fall through
        // to the standard emit and the stretchy machinery would be
        // dead code.
        $variants = $data->mathTable->hasMathVariants()
            ? (new MathVariantsParser())->parse($data->mathTable->mathVariantsBytes)
            : null;
        $extraGids = $this->collectVariantGids($data, $variants, $codepoints);

        // Register the Type 0 font stack. The Font handle carries
        // a post-subset Unicode -> GID map for hex emission; we
        // rebuild the post-subset GID -> width map from the parsed
        // data + the pre-/post-subset GID translation.
        $font = $this->writer->addOpenTypeFont(
            $data,
            $codepoints,
            $this->corePage(),
            $extraGids,
        );

        $unicodeToGidSubset = $font->getUnicodeToGidMap();
        $oldToNewGid = $font->getOldToNewGidMap();
        $glyphWidthsSubset = [];
        foreach ($oldToNewGid as $oldGid => $newGid) {
            $w = $data->glyphWidths[$oldGid] ?? null;
            if ($w !== null) {
                $glyphWidthsSubset[$newGid] = $w;
            }
        }

        $glyphInfo = $data->mathTable->hasMathGlyphInfo()
            ? (new MathGlyphInfoParser())->parse($data->mathTable->mathGlyphInfoBytes)
            : null;

        $mathFont = new MathmlMathFont(
            font: $font,
            unicodeToGid: $unicodeToGidSubset,
            oldToNewGid: $oldToNewGid,
            glyphWidths: $glyphWidthsSubset,
            unitsPerEm: $data->unitsPerEm,
            glyphInfo: $glyphInfo,
            variants: $variants,
        );

        $constants = (new MathConstantsParser())
            ->parse($data->mathTable->mathConstantsBytes);
        $metrics = new MathmlMetrics(
            constants: $constants,
            unitsPerEm: $data->unitsPerEm,
        );

        return [$mathFont, $metrics];
    }

    /**
     * Walk every base GID the document will render and collect any
     * vertical-stretch variants + assembly-part GIDs the font has
     * registered for it via MathVariants. Those GIDs have no
     * Unicode codepoint, so the CFF subsetter would otherwise drop
     * them. Passing them as `extraGids` keeps them embedded.
     *
     * Horizontal constructions (over/under-braces, wide arrows) are
     * collected the same way - the painter doesn't yet emit them
     * but a follow-up that does will already have the GIDs in the
     * subset.
     *
     * @param int[] $codepoints
     * @return list<int> Pre-subset GIDs
     */
    private function collectVariantGids(
        \Phpdftk\FontParser\OpenTypeData $data,
        ?\Phpdftk\FontParser\MathVariants $variants,
        array $codepoints,
    ): array {
        if ($variants === null) {
            return [];
        }
        $extras = [];
        $collectFromConstructions = function (array $constructions) use (&$extras): void {
            /** @var array<int, \Phpdftk\FontParser\MathGlyphConstruction> $constructions */
            foreach ($constructions as $construction) {
                foreach ($construction->variants as $variant) {
                    $extras[] = $variant['glyphId'];
                }
                if ($construction->assembly !== null) {
                    foreach ($construction->assembly->parts as $part) {
                        $extras[] = $part['glyphId'];
                    }
                }
            }
        };
        // Walk only the entries whose base GID corresponds to a
        // codepoint in the document - keeps the subset tight.
        foreach ($codepoints as $cp) {
            $baseGid = $data->fullUnicodeToGid[$cp] ?? null;
            if ($baseGid === null) {
                continue;
            }
            if (isset($variants->verticalConstructions[$baseGid])) {
                $collectFromConstructions([$variants->verticalConstructions[$baseGid]]);
            }
            if (isset($variants->horizontalConstructions[$baseGid])) {
                $collectFromConstructions([$variants->horizontalConstructions[$baseGid]]);
            }
        }
        return array_values(array_unique($extras));
    }

    /**
     * Page wrapper convenience: the writer's `addFont()` accepts
     * either the higher-level {@see Page} or the core
     * {@see CorePage}. We hold the higher-level wrapper for ergonomic
     * dispatch; pull the core page out when handing to the writer.
     */
    private function corePage(): CorePage|Page
    {
        return $this->page;
    }
}
