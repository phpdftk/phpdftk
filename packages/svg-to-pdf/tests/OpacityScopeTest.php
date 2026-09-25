<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.2 vs §15.2 — `fill-opacity` and `stroke-opacity` are
 * INHERITED properties that modulate the element's own paint
 * operations. `opacity` is the group property that composites a
 * subtree as a unit.
 *
 * The painter conflated them: it folded all three into one PDF
 * `ExtGState` emitted inside the element's `q…Q`, so a container's
 * `fill-opacity` stuck to every descendant. A child declaring
 * `fill-opacity: 1` then had nothing to emit — its value matched the
 * default — and was painted at the ancestor's alpha anyway.
 */
final class OpacityScopeTest extends TestCase
{
    private function paint(string $body): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        (new Translator())->paint($doc, $stream, $page, $writer);
        return implode("\n", $stream->getOperators());
    }

    public function testAContainerFillOpacityDoesNotOverrideAChildsOwn(): void
    {
        $ops = $this->paint(
            '<g style="fill-opacity: 0">'
            . '<rect width="10" height="10" fill="green" style="fill-opacity: 1"/></g>',
        );
        self::assertStringNotContainsString('gs', $ops);
    }

    public function testAContainerFillOpacityStillInheritsToAChildThatDeclaresNone(): void
    {
        // The other direction: inheritance must still reach the child,
        // it just has to arrive through the CASCADE rather than through
        // a leaked graphics state.
        $ops = $this->paint(
            '<g style="fill-opacity: 0.5"><rect width="10" height="10" fill="green"/></g>',
        );
        self::assertStringContainsString('gs', $ops);
    }

    public function testGroupOpacityOnAContainerStillEmitsAGraphicsState(): void
    {
        // Guard: `opacity` is a GROUP property and genuinely does apply
        // to the container as a whole, so it must keep its `gs`.
        $ops = $this->paint(
            '<g opacity="0.5"><rect width="10" height="10" fill="green"/></g>',
        );
        self::assertStringContainsString('gs', $ops);
    }

    public function testFillOpacityOnTheShapeItselfStillEmitsAGraphicsState(): void
    {
        // Guard: the whole point of the property on a painting element.
        $ops = $this->paint(
            '<rect width="10" height="10" fill="green" fill-opacity="0.5"/>',
        );
        self::assertStringContainsString('gs', $ops);
    }

    public function testAUseFillOpacityDoesNotOverrideTheReferentsOwn(): void
    {
        // WPT svg/struct/reftests/use-inheritance-nth-child-of.
        $ops = $this->paint(
            '<defs><rect id="r" width="60" height="60" style="fill-opacity:1"/></defs>'
            . '<use href="#r" style="fill: green; fill-opacity:0"/>',
        );
        self::assertStringNotContainsString('gs', $ops);
    }

    public function testStrokeOpacityFollowsTheSameRule(): void
    {
        $ops = $this->paint(
            '<g style="stroke-opacity: 0">'
            . '<rect width="10" height="10" stroke="green" style="stroke-opacity: 1"/></g>',
        );
        self::assertStringNotContainsString('gs', $ops);
    }
}
