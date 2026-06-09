<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer coverage for `<mfrac>`. Confirms the Translator emits
 * numerator and denominator content via Tj operators and uses Td to
 * reposition the text-line matrix between them so they stack
 * vertically rather than running left-to-right.
 *
 * The actual visual positioning (correct baselines, centring) is
 * approximate at the tracer-bullet stage — these tests assert what
 * the renderer claims to do, not pixel-perfect math typography.
 */
final class MfracRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testMfracRendersNumeratorAndDenominator(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // Both digits reach the content stream as separate Tj calls.
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testMfracUsesTdForVerticalRepositioning(): void
    {
        // The Translator uses moveTextPosition (Td) to:
        //   1. Shift to the numerator's centred position above
        //      baseline.
        //   2. Shift from there to the denominator's centred position
        //      below baseline.
        //   3. Advance to the fraction's right edge.
        // So a single <mfrac> emits at least 3 Td operations beyond
        // whatever the surrounding renderer set up.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
        );
        $tdCount = preg_match_all('/\s+Td\b/', $bytes);
        self::assertGreaterThanOrEqual(
            3,
            $tdCount,
            'mfrac should emit at least 3 Td operations for stacking + advance',
        );
    }

    public function testInvalidMfracWithOneChildFallsBackToWalkChildren(): void
    {
        // <mfrac> with only one child is invalid per Core §3.3.2; the
        // Translator's fallback walks children inline rather than
        // dropping content on the floor.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>7</mn></mfrac>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(7\)\s+Tj/', $bytes);
    }

    public function testInvalidMfracWithThreeChildrenFallsBack(): void
    {
        // Same as above for the >2 children case — Core mandates
        // exactly two; extras are author error. Fallback recovers
        // the visible content rather than failing closed.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn><mn>3</mn></mfrac>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
    }

    public function testEmptyMfracEmitsNoTokens(): void
    {
        // <mfrac></mfrac> with no children → no element children →
        // walkChildren fallback emits nothing.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac></mfrac>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // No Tj operators inside the BT/ET block.
        self::assertDoesNotMatchRegularExpression(
            '/\([^)]+\)\s+Tj/',
            $bytes,
        );
    }

    public function testNestedMfracStacksRecursively(): void
    {
        // Continued fractions: <mfrac><mn>1</mn><mfrac><mn>2</mn><mn>3</mn></mfrac></mfrac>
        // The recursion through paint() handles arbitrary nesting; each
        // level positions itself relative to the parent's denominator
        // position.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac>'
                . '<mn>1</mn>'
                . '<mfrac><mn>2</mn><mn>3</mn></mfrac>'
                . '</mfrac>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
    }

    public function testMfracInMrowFlowsAfterFraction(): void
    {
        // <mfrac> followed by a token. The Translator's final Td
        // should advance to the fraction's right edge so subsequent
        // tokens (the <mo>=</mo>) flow horizontally and don't sit
        // on top of the denominator.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '<mo>=</mo>'
                . '<mn>5</mn>'
                . '</mrow>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(=\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(5\)\s+Tj/', $bytes);
    }

    private function render(string $mathmlXml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($mathmlXml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
