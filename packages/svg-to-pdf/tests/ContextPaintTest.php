<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\Value\Paint;
use Phpdftk\Svg\Value\Paint\ContextPaint;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.2.1 — `context-fill` and `context-stroke`.
 *
 * Both defer to the element that referenced the subtree the keyword
 * appears in, so one arrowhead definition can take the colour of every
 * line it is attached to. Landing markers without these made
 * `svg/text/reftests/text-context-fill` fail for the first time: the
 * marker had never painted before, so a `fill="context-stroke"` that
 * resolved to nothing had nothing to be wrong about.
 */
final class ContextPaintTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    // ---------------------------------------------------------------
    // Parsing.
    // ---------------------------------------------------------------

    public function testKeywordsParseToTheRightChannel(): void
    {
        $fill = Paint::parse('context-fill');
        self::assertInstanceOf(ContextPaint::class, $fill);
        self::assertFalse($fill->stroke);

        $stroke = Paint::parse('CONTEXT-STROKE');
        self::assertInstanceOf(ContextPaint::class, $stroke);
        self::assertTrue($stroke->stroke);
    }

    public function testNeighbouringKeywordsAreNotMistakenForContextPaint(): void
    {
        self::assertNotInstanceOf(ContextPaint::class, Paint::parse('currentColor'));
        self::assertNotInstanceOf(ContextPaint::class, Paint::parse('none'));
        self::assertNull(Paint::parse('context-filll'));
        self::assertNull(Paint::parse('context'));
    }

    // ---------------------------------------------------------------
    // Resolution.
    // ---------------------------------------------------------------

    /**
     * Outside any reference there is no context element, so the keyword
     * paints NOTHING. Falling through to `fill`'s black default would
     * stamp an opaque black shape over whatever is underneath.
     */
    public function testWithNoContextElementTheKeywordPaintsNothing(): void
    {
        $ops = $this->paint('<rect width="10" height="10" fill="context-fill"/>');
        self::assertStringNotContainsString('rg', $ops);
    }

    /** A context element with no `stroke` gives `context-stroke` nothing. */
    public function testAnAbsentContextChannelPaintsNothing(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" overflow="visible">'
            . '<rect width="4" height="4" fill="context-stroke"/></marker>'
            . '<path d="M 10,20 L 50,20" fill="#ff0000" marker-start="url(#m)"/>',
        );
        // The marker is placed and its rect's geometry is built, but
        // the path is discarded with `n` rather than filled.
        self::assertStringContainsString('1 0 0 1 10 20 cm', $ops);
        self::assertStringContainsString("0 0 4 4 re\nn", $ops);
    }

    public function testContextFillTakesTheReferencingShapesFill(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" overflow="visible">'
            . '<rect width="4" height="4" fill="context-fill"/></marker>'
            . '<path d="M 10,20 L 50,20" fill="#3300cc" stroke="#00ff00"'
            . ' marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('0.2 0 0.8 rg', $ops);
    }

    public function testContextStrokeTakesTheReferencingShapesStroke(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" overflow="visible">'
            . '<rect width="4" height="4" fill="context-stroke"/></marker>'
            . '<path d="M 10,20 L 50,20" fill="#3300cc" stroke="#00ff00"'
            . ' marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('0 1 0 rg', $ops);
    }

    /** The keyword crosses the channel: `stroke="context-fill"` is legal. */
    public function testTheKeywordMayCrossFillAndStrokeChannels(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="20" markerHeight="20" overflow="visible">'
            . '<rect width="4" height="4" fill="none" stroke="context-fill"/></marker>'
            . '<path d="M 10,20 L 50,20" fill="#3300cc" marker-start="url(#m)"/>',
        );
        self::assertStringContainsString('0.2 0 0.8 RG', $ops);
    }

    /**
     * The context is captured at the point of reference and restored
     * afterwards, so a marker on a marker's own content cannot keep the
     * outer shape's paint, and the shape painted after the marker is
     * back to its own.
     */
    public function testTheContextDoesNotLeakPastTheMarker(): void
    {
        $ops = $this->paint(
            '<marker id="m" markerWidth="10" markerHeight="10" overflow="visible">'
            . '<rect width="4" height="4" fill="context-fill"/></marker>'
            . '<path d="M 10,20 L 50,20" fill="#3300cc" marker-start="url(#m)"/>'
            . '<rect x="100" y="100" width="4" height="4" fill="context-fill"/>',
        );
        // Two `0.2 0 0.8 rg`: the path's own fill and the marker's
        // rect deferring to it. The trailing rect has no context
        // element, so it builds its geometry and discards it with `n`.
        self::assertSame(2, substr_count($ops, '0.2 0 0.8 rg'));
        self::assertStringEndsWith("100 100 4 4 re\nn", $ops);
    }
}
