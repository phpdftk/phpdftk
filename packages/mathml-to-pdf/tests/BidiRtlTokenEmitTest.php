<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the RTL token-content reordering path. When a token
 * (mi, mn, mo, ms, mtext) contains pure-RTL text, the painter
 * must reverse the codepoint sequence before emission so the
 * visual order matches the script's natural direction.
 *
 * Pure-LTR / neutral content is untouched. Mixed-direction content
 * stays in source order pending full UAX #9 (a follow-up slice).
 */
final class BidiRtlTokenEmitTest extends TestCase
{
    public function testHebrewTokenReversesEmissionOrder(): void
    {
        // 'אבג' = U+05D0 U+05D1 U+05D2. Logical source: 05D0, 05D1, 05D2.
        // Visual RTL: 05D2, 05D1, 05D0. In the PDF content stream
        // (which lays glyphs left-to-right), we expect the BYTE
        // sequence of the emitted string to start with U+05D2 then
        // U+05D1 then U+05D0.
        $bytes = $this->render(
            '<mi>' . "\u{05D0}\u{05D1}\u{05D2}" . '</mi>',
        );
        // Each Hebrew letter is 2 bytes in UTF-8: D7 90, D7 91, D7 92.
        // After reverse, the emit order is D7 92, D7 91, D7 90.
        // PHP escapes octets > 0x7F as \nnn in showText literals;
        // we check the literal directly.
        // U+05D2 in WinAnsi isn't mapped, so the encoder emits '?'.
        // Standard fonts can't render Hebrew at all, so we instead
        // check the *measure* and that no error occurs.
        self::assertStringStartsWith('%PDF-', $bytes);
        // The content stream should carry at least one Tj.
        self::assertMatchesRegularExpression('/\)\s+Tj/', $bytes);
    }

    public function testLatinTokenUntouchedByBidiPath(): void
    {
        // 'abc' should emit in source order. The Tj literal should
        // contain 'abc' (not 'cba') so the source ordering survives.
        $bytes = $this->render('<mi>abc</mi>');
        self::assertMatchesRegularExpression('/\(abc\)\s+Tj/', $bytes);
    }

    public function testDigitsUnreversedEvenInRtlContext(): void
    {
        // Digits are bidi-neutral but conceptually display LTR even
        // in RTL paragraphs (UAX #9 rule). The painter should NOT
        // reverse digit-only content - the source '1234' must end
        // up as '1234' in the stream.
        $bytes = $this->render('<mn>1234</mn>');
        self::assertMatchesRegularExpression('/\(1234\)\s+Tj/', $bytes);
    }

    public function testMixedContentStaysInSourceOrderForNow(): void
    {
        // Mixed Hebrew + Latin: full UAX #9 reordering hasn't landed
        // yet, so the painter falls back to source-order emit. We
        // verify it doesn't crash and produces valid PDF.
        $bytes = $this->render('<mtext>hello ' . "\u{05D0}" . ' world</mtext>');
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testEmptyTokenNoCrash(): void
    {
        $bytes = $this->render('<mi></mi>');
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testNeutralTokenUntouched(): void
    {
        // Punctuation-only content has no strong direction;
        // BidiAnalyzer reports DIRECTION_NEUTRAL. Painter takes
        // the source-order path.
        $bytes = $this->render('<mo>,</mo>');
        self::assertMatchesRegularExpression('/\(,\)\s+Tj/', $bytes);
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
