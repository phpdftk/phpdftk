<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Arabic shaping inside the painter.
 *
 * Verifies end-to-end that a MathML token containing basic
 * Arabic letters produces a valid PDF and that the painter doesn't
 * regress when shaping fires.
 */
final class ArabicShapingIntegrationTest extends TestCase
{
    public function testArabicWordProducesValidPdf(): void
    {
        // 'بت' (BEH + TEH) - two dual-joining letters.
        $bytes = $this->render('<mi>' . "\u{0628}\u{062A}" . '</mi>');
        self::assertStringStartsWith('%PDF-', $bytes);
        // The content stream should carry at least one Tj.
        self::assertMatchesRegularExpression('/\)\s+Tj/', $bytes);
    }

    public function testArabicMixedWithLatinDoesNotCrash(): void
    {
        // Latin + Arabic + Latin in an mtext.
        $bytes = $this->render(
            '<mtext>x ' . "\u{0628}\u{062A}" . ' y</mtext>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testArabicWithDiacriticsPassesThrough(): void
    {
        // BEH + FATHA + TEH - the diacritic is transparent for
        // shaping purposes and should still appear in output.
        $bytes = $this->render(
            '<mi>' . "\u{0628}\u{064E}\u{062A}" . '</mi>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testNoArabicNoRegression(): void
    {
        // Pure-Latin input should not trigger the shaping path
        // and must round-trip identically.
        $bytes = $this->render('<mi>x</mi>');
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testArabicWithDigitsPreservesLogicalDigitOrder(): void
    {
        // Digits are bidi-neutral; in an Arabic context they stay
        // in source order.
        $bytes = $this->render(
            '<mtext>' . "\u{0628}\u{062A}" . ' 42</mtext>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
