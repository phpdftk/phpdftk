<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

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

        // Register the two faces we need for token rendering. The
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

        // Baseline: position so the first glyph's top edge sits at
        // (x, y + height). PDF y grows upward; box top is y + height;
        // we drop by the font size to get a baseline that keeps the
        // glyph inside the requested rect.
        $boxHeight = $height ?? $fontSize;
        $baselineY = $y + $boxHeight - $fontSize;

        $stream->saveGraphicsState();
        $stream->beginText();
        $stream->setFont($upright, $fontSize);
        $stream->moveTextPosition($x, $baselineY);

        $ctx = new MathmlPaintContext(
            stream: $stream,
            upright: $upright,
            italic: $italic,
            fontSize: $fontSize,
            cursorX: $x,
            baselineY: $baselineY,
        );
        $this->translator->paint($math, $ctx);

        $stream->endText();
        $stream->restoreGraphicsState();
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
