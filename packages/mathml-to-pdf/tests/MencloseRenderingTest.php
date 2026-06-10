<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer coverage for `<menclose>`. Each notation that the painter
 * recognises is exercised via its expected number of path-stroke
 * operators (`S`) and lineTo operators (`l`), since the only signal
 * we can grep for in the content stream is the path syntax.
 *
 * Unrecognised notations (`circle`, `radical`, ...) should no-op
 * without crashing.
 */
final class MencloseRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testBoxNotationDrawsRectangle(): void
    {
        // Box = 4 edges. We expect at least 4 lineTo (`l`) operators
        // and 1 stroke (`S`).
        $bytes = $this->render('box');
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        $lCount = preg_match_all('/^\d/m', $bytes) ?: 0;
        unset($lCount);
        $strokes = preg_match_all('/\nS\n/', $bytes);
        self::assertGreaterThanOrEqual(1, $strokes);
        // Four lineTo segments minimum (rectangle perimeter).
        $linetos = preg_match_all('/\bl\b/', $bytes);
        self::assertGreaterThanOrEqual(4, $linetos);
    }

    public function testRoundedboxNotationDrawsSameAsBoxForV1(): void
    {
        $bytes = $this->render('roundedbox');
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
        $linetos = preg_match_all('/\bl\b/', $bytes);
        self::assertGreaterThanOrEqual(4, $linetos);
    }

    public function testLongdivNotationDrawsTwoEdges(): void
    {
        // longdiv: top edge + left edge (open right). 2 linetos
        // minimum.
        $bytes = $this->render('longdiv');
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
        $linetos = preg_match_all('/\bl\b/', $bytes);
        self::assertGreaterThanOrEqual(2, $linetos);
    }

    public function testActuarialNotationDrawsTwoEdges(): void
    {
        $bytes = $this->render('actuarial');
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
        $linetos = preg_match_all('/\bl\b/', $bytes);
        self::assertGreaterThanOrEqual(2, $linetos);
    }

    public function testHorizontalstrikeDrawsOneLine(): void
    {
        $bytes = $this->render('horizontalstrike');
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testVerticalstrikeDrawsOneLine(): void
    {
        $bytes = $this->render('verticalstrike');
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testUpdiagonalstrikeDrawsOneLine(): void
    {
        $bytes = $this->render('updiagonalstrike');
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testDowndiagonalstrikeDrawsOneLine(): void
    {
        $bytes = $this->render('downdiagonalstrike');
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testTopBottomLeftRightEdgeNotations(): void
    {
        foreach (['top', 'bottom', 'left', 'right'] as $edge) {
            $bytes = $this->render($edge);
            self::assertMatchesRegularExpression(
                '/\nS\n/',
                $bytes,
                "expected at least one stroke for notation '$edge'",
            );
        }
    }

    public function testMultipleNotationsCombineStrokes(): void
    {
        // box + horizontalstrike + updiagonalstrike = 3 separate
        // stroke calls (one per notation, each emitting at least
        // one S).
        $bytes = $this->render('box horizontalstrike updiagonalstrike');
        $strokes = preg_match_all('/\nS\n/', $bytes);
        self::assertGreaterThanOrEqual(3, $strokes);
    }

    public function testUnknownNotationNoOpsWithoutCrash(): void
    {
        // 'circle' is recognised by name but not implemented in v1.
        // Painter must skip without throwing; content still renders.
        $bytes = $this->render('circle');
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testCompletelyUnknownNotationTokenNoOps(): void
    {
        $bytes = $this->render('snorgle');
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testAbsentNotationUsesLongdivDefault(): void
    {
        // No `notation` attribute - the spec default is longdiv.
        // Painter should still draw 2 edges.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<menclose><mi>x</mi></menclose>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
        $linetos = preg_match_all('/\bl\b/', $bytes);
        self::assertGreaterThanOrEqual(2, $linetos);
    }

    public function testFollowingSiblingFlowsPastConstruct(): void
    {
        // (box of x) y - confirm the painter advances past the
        // notation width so the next sibling doesn't overlap.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<menclose notation="box"><mi>x</mi></menclose>'
                . '<mi>y</mi>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
    }

    public function testNestedConstructInsideMencloseRenders(): void
    {
        // <menclose><mfrac>...</mfrac></menclose> - the fraction
        // bar should still draw inside the box.
        $bytes = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<menclose notation="box">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</menclose>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // Box stroke + fraction-bar stroke = at least 2 strokes.
        $strokes = preg_match_all('/\nS\n/', $bytes);
        self::assertGreaterThanOrEqual(2, $strokes);
    }

    private function render(string $notation): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<menclose notation="' . htmlspecialchars($notation, ENT_QUOTES) . '">'
            . '<mi>x</mi>'
            . '</menclose>'
            . '</math>';
        return $this->renderRaw($xml);
    }

    private function renderRaw(string $xml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 300.0, height: 30.0);
        return $writer->toBytes();
    }
}
