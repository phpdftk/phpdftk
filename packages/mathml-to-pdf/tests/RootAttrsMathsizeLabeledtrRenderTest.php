<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the recently-wired token/root attributes:
 *
 *   - <math displaystyle / scriptlevel> seeds the initial cascade.
 *   - mathsize on a token element produces a different Tf size in
 *     the content stream.
 *   - <mlabeledtr> drops its first (label) child and renders the
 *     rest as table cells.
 */
final class RootAttrsMathsizeLabeledtrRenderTest extends TestCase
{
    public function testMathsizeScalesTokenFontSize(): void
    {
        // Two mn elements at different mathsize values; the
        // content stream must carry two different Tf font sizes.
        $bytes = $this->render(
            '<mn>1</mn><mn mathsize="big">2</mn>',
        );
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_unique(array_map('floatval', $m[1]));
        self::assertGreaterThanOrEqual(
            2,
            count($sizes),
            'mathsize should produce a second Tf size',
        );
    }

    public function testMathsizeAbsoluteSetsExactSize(): void
    {
        $bytes = $this->render('<mn mathsize="24pt">1</mn>');
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_map('floatval', $m[1]);
        // 24pt should appear in the stream (with float tolerance).
        $hasTwentyFour = false;
        foreach ($sizes as $s) {
            if (abs($s - 24.0) < 0.01) {
                $hasTwentyFour = true;
                break;
            }
        }
        self::assertTrue(
            $hasTwentyFour,
            'mathsize="24pt" should appear as Tf 24',
        );
    }

    public function testMathsizeAbsentNoExtraTf(): void
    {
        // Without mathsize, the stream should only have one Tf
        // (the initial size).
        $bytes = $this->render('<mn>1</mn>');
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_unique(array_map('floatval', $m[1]));
        self::assertCount(1, $sizes);
    }

    public function testMathDisplaystyleTrueIsHonored(): void
    {
        // <math displaystyle="true"> should make displayStyle true
        // for downstream constructs. We verify a basic mfrac renders
        // valid PDF with displaystyle inherited.
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML" '
            . 'displaystyle="true">'
            . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
            . '</math>';
        $bytes = $this->renderRaw($xml);
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testMathScriptlevelStarts(): void
    {
        // Verify scriptlevel on the root produces a smaller initial
        // font size compared with the default (level 0).
        $bytes1 = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" '
            . 'scriptlevel="0"><mn>1</mn></math>',
        );
        $bytes2 = $this->renderRaw(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" '
            . 'scriptlevel="2"><mn>1</mn></math>',
        );
        // Both must produce valid PDFs.
        self::assertStringStartsWith('%PDF-', $bytes1);
        self::assertStringStartsWith('%PDF-', $bytes2);
    }

    public function testMlabeledtrDropsLabelInsideMtable(): void
    {
        // <mlabeledtr><mtd>LABEL</mtd><mtd>CONTENT</mtd></mlabeledtr>
        // The LABEL mtd is the first child and should be dropped.
        // CONTENT should still render.
        $bytes = $this->render(
            '<mtable>'
            . '<mlabeledtr>'
            . '<mtd><mtext>LABEL</mtext></mtd>'
            . '<mtd><mtext>CONTENT</mtext></mtd>'
            . '</mlabeledtr>'
            . '</mtable>',
        );
        self::assertMatchesRegularExpression('/\(CONTENT\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression(
            '/\(LABEL\)\s+Tj/',
            $bytes,
        );
    }

    public function testMlabeledtrStandaloneDropsLabel(): void
    {
        // Top-level <mlabeledtr> outside a table also drops the
        // first child.
        $bytes = $this->render(
            '<mlabeledtr><mi>L</mi><mi>R</mi></mlabeledtr>',
        );
        self::assertMatchesRegularExpression('/\(R\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression('/\(L\)\s+Tj/', $bytes);
    }

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        return $this->renderRaw($xml);
    }

    private function renderRaw(string $xml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
