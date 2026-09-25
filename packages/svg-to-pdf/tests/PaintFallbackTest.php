<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.2 — `<paint>` is `<url> [none | <color>]?`. When the
 * referenced paint server does not resolve, the fallback paint applies;
 * when there is no fallback, the element is in error and is not
 * painted.
 *
 * We parsed the fallback but never consulted it, so
 * `fill="url(#missing) green"` painted nothing at all.
 */
final class PaintFallbackTest extends TestCase
{
    private function paint(string $body, bool $withCascade = false): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">' . $body . '</svg>',
        );
        if ($withCascade) {
            (new SvgCascadeProjector())->project($doc);
        }
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testUnresolvableFillFallsBackToItsColour(): void
    {
        $ops = $this->paint('<rect width="100" height="100" fill="url(#null) green"/>');
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
        self::assertStringContainsString('f', $ops);
    }

    public function testUnresolvableStrokeFallsBackToItsColour(): void
    {
        $ops = $this->paint(
            '<rect width="100" height="100" fill="none"'
            . ' stroke="url(#null) blue" stroke-width="4"/>',
        );
        self::assertStringContainsString('0 0 1 RG', $ops);
        self::assertStringContainsString('S', $ops);
    }

    public function testNoneFallbackLeavesTheShapeUnpainted(): void
    {
        $ops = $this->paint('<rect width="100" height="100" fill="url(#null) none"/>');
        self::assertStringNotContainsString(' rg', $ops);
    }

    public function testMissingFallbackLeavesTheShapeUnpainted(): void
    {
        // Guard. SVG 2 error processing: an unresolvable reference with
        // no fallback is an error, and the element is NOT rendered.
        // Falling back to the black default here would paint a big
        // black box over documents that stack a correct shape
        // underneath, which is how several WPT fixtures are built.
        $ops = $this->paint('<rect width="100" height="100" fill="url(#null)"/>');
        self::assertStringNotContainsString(' rg', $ops);
    }

    public function testAResolvableReferenceStillWinsOverItsFallback(): void
    {
        // Guard: the fallback must only apply when the server fails.
        // Needs the writer + page wired, or nothing can register.
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<linearGradient id="g"><stop stop-color="green"/></linearGradient>'
            . '<rect width="100" height="100" fill="url(#g) red"/></svg>',
        );
        (new Translator())->paint($doc, $stream, $page, $writer);
        $ops = implode("\n", $stream->getOperators());
        self::assertStringNotContainsString('1 0 0 rg', $ops);
        self::assertStringContainsString('scn', $ops);
    }

    public function testCurrentColorFallbackResolvesAgainstTheInheritedColour(): void
    {
        // `fill="url(#notfound) currentColor"` inside `<g color="#008000">`.
        // WPT svg/pservers/reftests/fill-fallback-currentcolor-2.
        $ops = $this->paint(
            '<g color="#008000" fill="#ff0000">'
            . '<rect width="100" height="100" fill="url(#notfound) currentColor"/></g>',
            withCascade: true,
        );
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
    }

    public function testCurrentColorResolvesAgainstTheColourProperty(): void
    {
        $ops = $this->paint(
            '<g color="#008000"><rect width="10" height="10" fill="currentColor"/></g>',
            withCascade: true,
        );
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
    }

    public function testCurrentColorWithNoColourDeclarationStaysBlack(): void
    {
        // Guard: `color`'s initial value is black, so an undeclared
        // `currentColor` must not start resolving to something else.
        $ops = $this->paint(
            '<rect width="10" height="10" fill="currentColor"/>',
            withCascade: true,
        );
        self::assertStringContainsString('0 0 0 rg', $ops);
    }
}
