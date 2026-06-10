<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlCodepointCollector;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the token-codepoint collector that pre-scans a
 * MathmlDocument so the PdfWriter's CFF subsetter has the right
 * codepoint list to subset against.
 */
final class MathmlCodepointCollectorTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testCollectsTokenCodepoints(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mn>2</mn><mi>x</mi><mo>+</mo><mi>y</mi>'
                . '</math>',
        );
        $cps = MathmlCodepointCollector::collect($doc);
        sort($cps);
        self::assertSame(
            [ord('+'), ord('2'), ord('x'), ord('y')],
            $cps,
        );
    }

    public function testCollectsCodepointsThroughContainers(): void
    {
        // mfrac wraps the tokens but the collector walks through it.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
        );
        $cps = MathmlCodepointCollector::collect($doc);
        sort($cps);
        self::assertSame([ord('1'), ord('2')], $cps);
    }

    public function testDedupesRepeatedCodepoints(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow><mn>2</mn><mo>+</mo><mn>2</mn></mrow>'
                . '</math>',
        );
        $cps = MathmlCodepointCollector::collect($doc);
        sort($cps);
        self::assertSame([ord('+'), ord('2')], $cps);
    }

    public function testCollectsUnicodeOperators(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mo>' . "\u{2211}" . '</mo>' // n-ary summation
                . '<mi>n</mi>'
                . '</math>',
        );
        $cps = MathmlCodepointCollector::collect($doc);
        sort($cps);
        self::assertSame([ord('n'), 0x2211], $cps);
    }

    public function testIgnoresNonTokenTextChildren(): void
    {
        // mrow's direct text content isn't a MathML token and gets
        // dropped by the parser anyway, so the collector sees only
        // the wrapped tokens.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow><mi>x</mi></mrow>'
                . '</math>',
        );
        $cps = MathmlCodepointCollector::collect($doc);
        self::assertSame([ord('x')], $cps);
    }

    public function testEmptyDocumentReturnsEmptyList(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"></math>',
        );
        self::assertSame([], MathmlCodepointCollector::collect($doc));
    }
}
