<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §13.4 — a paint server defined where it is not allowed (a
 * `<linearGradient>` inside a `<text>`, for instance) is not part of
 * the render tree. A reference to it does not resolve, and neither
 * does an `href` template chain that ends there.
 *
 * We resolved any id anywhere in the document, so an orange gradient
 * hidden inside `<text>` painted over the green the fixture expects.
 */
final class PaintServerRenderTreeTest extends TestCase
{
    private const string GREEN = '0 0.5019607843 0 rg';

    private function paint(string $body): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        (new Translator())->paint($doc, $stream, $page, $writer);
        return implode("\n", $stream->getOperators());
    }

    public function testAGradientTemplateInsideTextDoesNotResolve(): void
    {
        // WPT pservers/gradient-inheritance-not-in-rendertree-01.
        $ops = $this->paint(
            '<linearGradient id="g1" href="#g0"/>'
            . '<rect width="100" height="100" fill="green"/>'
            . '<rect width="100" height="100" fill="url(#g1) yellow"/>'
            . '<text><linearGradient id="g0"><stop stop-color="orange"/></linearGradient></text>',
        );
        // The green rect underneath must stay visible. §13.4: a
        // gradient with no stops paints as `none`, so the yellow
        // FALLBACK must NOT apply — that would overpaint the green.
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString('scn', $ops);
        self::assertStringNotContainsString('1 1 0 rg', $ops);
    }

    public function testAGradientReferencedDirectlyFromInsideTextDoesNotResolve(): void
    {
        $ops = $this->paint(
            '<rect width="100" height="100" fill="url(#g0) green"/>'
            . '<text><linearGradient id="g0"><stop stop-color="orange"/></linearGradient></text>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
    }

    public function testAStoplessGradientPaintsAsNoneRatherThanFallingBack(): void
    {
        // §13.4 — the server resolves and says "paint nothing"; that is
        // not a failed reference, so the fallback must stay unused.
        $ops = $this->paint(
            '<linearGradient id="g"/>'
            . '<rect width="100" height="100" fill="url(#g) yellow"/>',
        );
        self::assertStringNotContainsString('1 1 0 rg', $ops);
        self::assertStringNotContainsString('scn', $ops);
    }

    public function testAnEmptyPatternFallsBackInstead(): void
    {
        // §13.3 gives `<pattern>` no "paint as none" rule, so an empty
        // one is simply an unusable paint server and §13.2's fallback
        // applies. The asymmetry with a stop-less gradient is the
        // spec's, not ours.
        $ops = $this->paint(
            '<pattern id="p" width="1" height="1"/>'
            . '<rect width="100" height="100" fill="url(#p) green"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
    }

    public function testAPatternInsideTextDoesNotResolve(): void
    {
        $ops = $this->paint(
            '<rect width="100" height="100" fill="url(#p0) green"/>'
            . '<text><pattern id="p0" width="1" height="1">'
            . '<rect width="100" height="100" fill="orange"/></pattern></text>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
    }

    public function testAGradientInDefsStillResolves(): void
    {
        // Guard: `<defs>` and the other containers must keep working —
        // the check is a denylist of ancestors that cannot hold
        // definitions, not an allowlist of two.
        $ops = $this->paint(
            '<defs><g><linearGradient id="g"><stop stop-color="orange"/>'
            . '<stop offset="1" stop-color="blue"/></linearGradient></g></defs>'
            . '<rect width="100" height="100" fill="url(#g) green"/>',
        );
        self::assertStringContainsString('scn', $ops);
        self::assertStringNotContainsString(self::GREEN, $ops);
    }

    public function testAGradientWithItsOwnStopsIgnoresABrokenTemplate(): void
    {
        // Guard: §13.4 only walks the href chain when the gradient has
        // no stops of its own, so an unreachable template is harmless
        // here. WPT's -02 variants cover exactly this.
        $ops = $this->paint(
            '<linearGradient id="g1" href="#g0"><stop stop-color="green"/></linearGradient>'
            . '<rect width="100" height="100" fill="url(#g1) yellow"/>'
            . '<text><linearGradient id="g0"><stop stop-color="orange"/></linearGradient></text>',
        );
        self::assertStringContainsString('scn', $ops);
    }
}
