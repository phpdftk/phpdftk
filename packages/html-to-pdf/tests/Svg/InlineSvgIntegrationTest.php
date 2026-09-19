<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Svg;

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage for inline `<svg>` rendering inside an HTML
 * document. Drives the full Renderer pipeline (HTML parse → cascade
 * → box tree → layout → paint) with HTML that contains inline SVG,
 * then asserts the resulting PDF actually carries the SVG shapes —
 * before the InlineSvgAdapter / Painter routing landed, the SVG
 * subtree was silently dropped (text leaked through, shapes never
 * painted).
 *
 * Tests use `compressStreams: false` so the assertions can grep the
 * content stream for PDF operators directly.
 */
final class InlineSvgIntegrationTest extends TestCase
{
    public function testInlineSvgRectProducesFilledRectanglePath(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <p>before</p>
              <svg width="80" height="60" xmlns="http://www.w3.org/2000/svg">
                <rect x="0" y="0" width="80" height="60" fill="#ff0000"/>
              </svg>
              <p>after</p>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();

        self::assertStringStartsWith('%PDF-', $bytes);
        // Red fill colour: `1 0 0 rg` (rgb 255,0,0 normalised to 0..1).
        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'red fill colour from inline-SVG rect not emitted',
        );
        // The rect's fill operator `f` (or `B`) must appear after the
        // path is constructed. We assert presence of the fill verb
        // alone; the exact path-construction sequence is the SVG
        // renderer's call.
        self::assertMatchesRegularExpression('/\nf\b/', $bytes, 'no fill operator from inline-SVG rect');
    }

    public function testInlineSvgWithoutDimensionsSkipsCleanly(): void
    {
        // No width/height on the SVG and no CSS → box geometry is zero.
        // The painter must skip (returning early) rather than crash;
        // a valid PDF still emerges. We don't assert text content
        // makes it through (text is encoded via hex Tj strings, not
        // raw ASCII), only that the renderer produced a well-formed
        // PDF with a real content stream.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<p>before</p>'
                . '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10" fill="#000"/></svg>'
                . '<p>after</p>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringEndsWith("%%EOF", trim($bytes));
    }

    public function testInlineSvgClipsOversizedContentToViewport(): void
    {
        // SVG 2 §8.2 / CSS Overflow 3 — the SVG viewport clips content
        // larger than its box (UA default `svg { overflow: clip }`). A
        // 150x150 rect inside a CSS-sized 100x100 svg emits an EXTRA clip
        // (`W n`) around the draw versus the same svg with `overflow:
        // visible`, which paints the rect unconfined. Comparing the two
        // isolates the SVG viewport clip from the always-present page clip.
        $clips = fn(string $extraSvgStyle): int => substr_count(
            (function () use ($extraSvgStyle): string {
                $writer = new PdfWriter(compressStreams: false);
                (new Renderer())->renderInto(
                    $writer,
                    '<html><body>'
                        . '<svg style="width:100px;height:100px;' . $extraSvgStyle . '" xmlns="http://www.w3.org/2000/svg">'
                        . '<rect width="150" height="150" fill="#008000"/>'
                        . '</svg>'
                        . '</body></html>',
                );
                return $writer->toBytes();
            })(),
            "W\nn",
        );

        $default = $clips('');
        $visible = $clips('overflow:visible;');
        self::assertGreaterThan($visible, $default, 'default SVG overflow must add a viewport clip the overflow:visible case omits');
    }

    public function testMalformedInlineSvgSkipsRatherThanCrashes(): void
    {
        // An `<svg>` element with a child whose markup the SVG parser
        // can't make sense of. The render must produce a valid PDF —
        // the malformed inline SVG just doesn't contribute anything.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="40" height="40" xmlns="http://www.w3.org/2000/svg">'
                . '<rect width="40" height="40" fill="#fff" stroke=""/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringEndsWith("%%EOF", trim($bytes));
    }

    public function testMultipleInlineSvgsRenderIndependently(): void
    {
        // Two distinct <svg> elements in one document. Each must paint
        // its own content (different fills here so we can verify both
        // ended up in the stream). Confirms the adapter cache keys on
        // element identity and doesn't conflate the two.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <svg width="30" height="30" xmlns="http://www.w3.org/2000/svg">
                <rect width="30" height="30" fill="#ff0000"/>
              </svg>
              <svg width="30" height="30" xmlns="http://www.w3.org/2000/svg">
                <rect width="30" height="30" fill="#0000ff"/>
              </svg>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'red fill from first inline SVG missing',
        );
        self::assertMatchesRegularExpression(
            '/\b0(?:\.0+)?\s+0(?:\.0+)?\s+1(?:\.0+)?\s+rg\b/',
            $bytes,
            'blue fill from second inline SVG missing',
        );
    }

    /**
     * SVG 2 §6.7 — a presentation attribute is an author-origin CSS
     * declaration with specificity zero, so an INHERITED property set
     * that way (`fill` on a `<g>`) must reach descendants that do not
     * set it themselves.
     *
     * Inline SVG in an HTML document is styled by projecting the HTML
     * cascade onto the subtree; before the presentation-attribute sheet
     * landed, that cascade never saw `fill="#ff0000"` on the `<g>`, so
     * it projected the INITIAL `fill: #000000` onto the `<rect>` and the
     * shape painted black.
     */
    public function testPresentationAttributeOnGroupInheritsToChild(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="80" height="60" xmlns="http://www.w3.org/2000/svg">'
                . '<g fill="#ff0000"><rect x="0" y="0" width="80" height="60"/></g>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'fill="#ff0000" on the <g> did not inherit to the <rect>',
        );
    }

    /**
     * Same rule applied at the outermost `<svg>`: its own presentation
     * attributes participate in the cascade the subtree inherits from.
     */
    public function testPresentationAttributeOnSvgRootInheritsToDescendant(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="80" height="60" fill="#ff0000" xmlns="http://www.w3.org/2000/svg">'
                . '<rect x="0" y="0" width="80" height="60"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'fill="#ff0000" on the <svg> root did not inherit to the <rect>',
        );
    }

    /**
     * A document stylesheet rule out-specifies a presentation attribute
     * (SVG 2 §6.7 pins presentation attributes at specificity zero), so
     * the descendant must inherit the STYLESHEET colour, not the
     * attribute one.
     */
    public function testStylesheetRuleOutranksPresentationAttributeForInheritance(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><head><style>#g { fill: #0000ff }</style></head><body>'
                . '<svg width="80" height="60" xmlns="http://www.w3.org/2000/svg">'
                . '<g id="g" fill="#ff0000"><rect x="0" y="0" width="80" height="60"/></g>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b0(?:\.0+)?\s+0(?:\.0+)?\s+1(?:\.0+)?\s+rg\b/',
            $bytes,
            'the #g stylesheet rule should have beaten the fill presentation attribute',
        );
    }

    /**
     * CSS Variables 1 §3 + SVG 2 §6.7 — an SVG geometry attribute is a
     * presentation attribute for the CSS property of the same name, so
     * `r="var(--radii)"` must resolve against the element's custom
     * properties. Without substitution the raw text `var(--radii)`
     * reaches the length parser, yields 0, and the circle vanishes.
     */
    public function testVarOnGeometryAttributeResolvesFromCustomProperty(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><head><style>circle { --radii: 40px }</style></head><body>'
                . '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">'
                . '<circle cx="50" cy="50" r="var(--radii)" fill="#ff0000"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'circle with r="var(--radii)" painted nothing',
        );
    }

    /** The `var()` fallback is used when the custom property is unset. */
    public function testVarFallbackOnGeometryAttributeIsUsed(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">'
                . '<circle cx="50" cy="50" r="var(--radii, 40px)" fill="#ff0000"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'circle with r="var(--radii, 40px)" painted nothing',
        );
    }

    /**
     * CSS Variables 1 §4 — an unresolvable `var()` with no fallback is
     * invalid at computed-value time, so the geometry attribute it was
     * written on contributes nothing and the shape does not paint.
     */
    public function testUnresolvableVarOnGeometryAttributePaintsNothing(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">'
                . '<circle cx="50" cy="50" r="var(--radii)" fill="#ff0000"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertDoesNotMatchRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'an unresolvable var() must leave the circle unpainted',
        );
    }

    /**
     * `var()` is substituted only in attributes that ARE presentation
     * attributes. `viewBox` is not a CSS property, so the text stays
     * literal and no 50x50-viewBox-to-100x100-viewport scale appears.
     */
    public function testVarInNonPresentationAttributeIsNotSubstituted(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><head><style>svg { --vb: 0 0 50 50 }</style></head><body>'
                . '<svg width="100" height="100" viewBox="var(--vb)" xmlns="http://www.w3.org/2000/svg">'
                . '<rect width="100" height="100" fill="#ff0000"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringNotContainsString('2 0 0 2 ', $bytes);
    }

    /**
     * SVG 2 §5.6 — `<use>` deep-clones the element it references into a
     * SHADOW TREE whose host is the `<use>`. The clone therefore inherits
     * from the `<use>`, so `<use fill="#ff0000">` paints a referenced
     * `<rect>` that sets no fill of its own red.
     *
     * This is the `<use href="#icon" fill="currentColor">` sprite idiom.
     * Before the shadow tree was materialised the referenced subtree was
     * styled only from its position in the document (inside `<defs>`),
     * so it painted the initial black no matter what the `<use>` said.
     */
    public function testUseInheritsItsOwnFillIntoTheInstance(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">'
                . '<defs><g id="square"><rect width="100" height="100"/></g></defs>'
                . '<g id="test"><use href="#square" fill="#ff0000"/></g>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'fill on the <use> did not inherit into the instance',
        );
    }

    /**
     * SVG 2 §5.6 / Selectors 4 — a selector from the document tree does
     * NOT cross the shadow boundary, so `#test rect` must not reach the
     * instance even though the `<use>` host sits inside `#test`.
     */
    public function testDocumentSelectorDoesNotCrossTheUseShadowBoundary(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><head><style>#test rect { stroke: #0000ff; stroke-width: 10 }</style></head><body>'
                . '<svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">'
                . '<defs><g id="square"><rect width="100" height="100"/></g></defs>'
                . '<g id="test"><use href="#square" fill="#ff0000"/></g>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertDoesNotMatchRegularExpression(
            '/\b0(?:\.0+)?\s+0(?:\.0+)?\s+1(?:\.0+)?\s+RG\b/',
            $bytes,
            '#test rect crossed the <use> shadow boundary and stroked the instance',
        );
    }

    /**
     * The other half of the same rule: a selector whose whole compound
     * chain lives INSIDE the shadow tree does match. `.inside rect` has
     * both ends within the clone.
     */
    public function testSelectorContainedInTheShadowTreeStillMatches(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><head><style>.inside rect { fill: #00ff00 }</style></head><body>'
                . '<svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">'
                . '<defs><g id="square"><g class="inside"><rect width="100" height="100"/></g></g></defs>'
                . '<g id="test"><use href="#square"/></g>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b0(?:\.0+)?\s+1(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            '.inside rect should have matched inside the <use> shadow tree',
        );
    }

    /**
     * An element the referenced subtree styles itself wins over the
     * `<use>`: inheritance only fills in what the instance leaves unset.
     */
    public function testInstanceOwnFillBeatsTheUseFill(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">'
                . '<defs><g id="square" fill="#0000ff"><rect width="100" height="100"/></g></defs>'
                . '<g id="test"><use href="#square" fill="#ff0000"/></g>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b0(?:\.0+)?\s+0(?:\.0+)?\s+1(?:\.0+)?\s+rg\b/',
            $bytes,
            'the referenced group\'s own fill should have beaten the <use> fill',
        );
    }

    /**
     * A `<use>` that references an ancestor of itself is a cycle. It must
     * terminate rather than clone forever.
     */
    public function testSelfReferencingUseTerminates(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">'
                . '<g id="loop"><use href="#loop"/><rect width="10" height="10"/></g>'
                . '</svg>'
                . '</body></html>',
        );
        self::assertStringStartsWith('%PDF-', $writer->toBytes());
    }

    /**
     * The cloned instance must not become addressable by its id: a
     * document-order id lookup would otherwise be able to land on the
     * clone instead of the original, which is exactly what happens when
     * the `<use>` precedes the `<defs>` it references.
     */
    public function testUseBeforeDefsStillResolvesTheOriginal(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">'
                . '<use href="#square" fill="#ff0000"/>'
                . '<defs><g id="square"><rect width="100" height="100"/></g></defs>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();

        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'a <use> written before its <defs> did not paint the instance',
        );
    }
}
