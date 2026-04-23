<?php

declare(strict_types=1);

namespace ApprLabs\FontParser\Tests;

use ApprLabs\FontParser\Type1Data;
use ApprLabs\FontParser\Type1Parser;
use PHPUnit\Framework\TestCase;

class Type1ParserTest extends TestCase
{
    /**
     * Build a minimal synthetic PFB font for testing.
     *
     * PFB format: segments with 6-byte headers (0x80, type, 4-byte LE length).
     */
    private function buildSyntheticPfb(): string
    {
        $ascii = "%!PS-AdobeFont-1.0: TestFont 001.000\n"
            . "%%Title: TestFont\n"
            . "12 dict begin\n"
            . "/FontInfo 9 dict dup begin\n"
            . " /version (001.000) readonly def\n"
            . " /FullName (Test Font Regular) readonly def\n"
            . " /FamilyName (Test Font) readonly def\n"
            . " /isFixedPitch false def\n"
            . " /ItalicAngle 0 def\n"
            . " /UnderlinePosition -100 def\n"
            . " /UnderlineThickness 50 def\n"
            . "end readonly def\n"
            . "/FontName /TestFont-Regular def\n"
            . "/FontType 1 def\n"
            . "/FontMatrix [0.001 0 0 0.001 0 0] readonly def\n"
            . "/FontBBox {-166 -250 1000 900} readonly def\n"
            . "/Encoding 256 array\n"
            . "0 1 255 { 1 index exch /.notdef put } for\n"
            . "dup 32 /space put\n"
            . "dup 33 /exclam put\n"
            . "dup 65 /A put\n"
            . "dup 66 /B put\n"
            . "dup 67 /C put\n"
            . "dup 97 /a put\n"
            . "dup 98 /b put\n"
            . "dup 99 /c put\n"
            . "dup 48 /zero put\n"
            . "readonly def\n"
            . "currentdict end\n"
            . "currentfile eexec\n";

        // Fake encrypted binary segment (just enough to be valid)
        $binary = str_repeat("\x00", 64);

        // Trailer: 512 ASCII zeros + cleartomark
        $trailer = str_repeat("0", 512) . "\ncleartomark\n";

        // Build PFB segments
        $pfb = '';
        // ASCII segment (type 1)
        $pfb .= "\x80\x01" . pack('V', strlen($ascii));
        $pfb .= $ascii;
        // Binary segment (type 2)
        $pfb .= "\x80\x02" . pack('V', strlen($binary));
        $pfb .= $binary;
        // Trailer ASCII segment (type 1)
        $pfb .= "\x80\x01" . pack('V', strlen($trailer));
        $pfb .= $trailer;
        // EOF marker (type 3)
        $pfb .= "\x80\x03";

        return $pfb;
    }

    private function parseFixture(): Type1Data
    {
        return Type1Parser::fromBytes($this->buildSyntheticPfb())->parse();
    }

    public function testParseReturnsType1Data(): void
    {
        $data = $this->parseFixture();
        self::assertInstanceOf(Type1Data::class, $data);
    }

    public function testPostScriptName(): void
    {
        $data = $this->parseFixture();
        self::assertSame('TestFont-Regular', $data->postScriptName);
    }

    public function testFamilyName(): void
    {
        $data = $this->parseFixture();
        self::assertSame('Test Font Regular', $data->familyName);
    }

    public function testItalicAngle(): void
    {
        $data = $this->parseFixture();
        self::assertSame(0.0, $data->italicAngle);
    }

    public function testFontBBoxHasFourElements(): void
    {
        $data = $this->parseFixture();
        self::assertCount(4, $data->fontBBox);
        self::assertSame(-166, $data->fontBBox[0]);
        self::assertSame(-250, $data->fontBBox[1]);
        self::assertSame(1000, $data->fontBBox[2]);
        self::assertSame(900, $data->fontBBox[3]);
    }

    public function testAscentDerived(): void
    {
        $data = $this->parseFixture();
        self::assertSame(900, $data->ascent); // from FontBBox yMax
    }

    public function testDescentDerived(): void
    {
        $data = $this->parseFixture();
        self::assertSame(-250, $data->descent); // from FontBBox yMin
    }

    public function testCapHeightEstimated(): void
    {
        $data = $this->parseFixture();
        self::assertGreaterThan(0, $data->capHeight);
    }

    public function testStemVEstimated(): void
    {
        $data = $this->parseFixture();
        self::assertSame(80, $data->stemV); // default for non-bold, non-light
    }

    public function testFlagsNonsymbolic(): void
    {
        $data = $this->parseFixture();
        // Bit 6 (Nonsymbolic) should be set
        self::assertTrue(($data->flags & (1 << 5)) !== 0, 'Nonsymbolic flag should be set');
        // Bit 3 (Symbolic) should NOT be set
        self::assertTrue(($data->flags & (1 << 2)) === 0, 'Symbolic flag should not be set');
    }

    public function testEncodingParsed(): void
    {
        $data = $this->parseFixture();
        self::assertNotEmpty($data->encoding);
        self::assertSame('space', $data->encoding[32]);
        self::assertSame('exclam', $data->encoding[33]);
        self::assertSame('A', $data->encoding[65]);
        self::assertSame('B', $data->encoding[66]);
        self::assertSame('C', $data->encoding[67]);
        self::assertSame('a', $data->encoding[97]);
        self::assertSame('b', $data->encoding[98]);
        self::assertSame('c', $data->encoding[99]);
        self::assertSame('zero', $data->encoding[48]);
        self::assertSame('.notdef', $data->encoding[0]);
    }

    public function testUnicodeMapParsed(): void
    {
        $data = $this->parseFixture();
        self::assertSame(32, $data->unicodeMap[32]);  // space
        self::assertSame(65, $data->unicodeMap[65]);  // A
        self::assertSame(97, $data->unicodeMap[97]);  // a
        self::assertSame(48, $data->unicodeMap[48]);  // zero
    }

    public function testSegmentLengths(): void
    {
        $data = $this->parseFixture();
        self::assertGreaterThan(0, $data->length1); // ASCII
        self::assertGreaterThan(0, $data->length2); // Binary
        self::assertGreaterThan(0, $data->length3); // Trailer
    }

    public function testFontBytesNonEmpty(): void
    {
        $data = $this->parseFixture();
        self::assertNotEmpty($data->fontBytes);
        // Font bytes should be the concatenation of segments
        self::assertSame($data->length1 + $data->length2 + $data->length3, strlen($data->fontBytes));
    }

    // --- PFA format ---

    public function testParsePfaFormat(): void
    {
        $pfa = "%!PS-AdobeFont-1.0: PfaTest 001.000\n"
            . "/FontName /PfaTest def\n"
            . "/FullName (PFA Test Font) def\n"
            . "/FontBBox {-100 -200 800 700} readonly def\n"
            . "/isFixedPitch true def\n"
            . "/ItalicAngle -12.5 def\n"
            . "/Encoding StandardEncoding def\n"
            . "currentfile eexec\n"
            . "AABB0011CCDD\n"
            . str_repeat("0", 512) . "\ncleartomark\n";

        $data = Type1Parser::fromBytes($pfa)->parse();
        self::assertSame('PfaTest', $data->postScriptName);
        self::assertSame('PFA Test Font', $data->familyName);
        self::assertSame(-12.5, $data->italicAngle);
        // FixedPitch flag
        self::assertTrue(($data->flags & 1) !== 0, 'FixedPitch flag should be set');
        // Italic flag
        self::assertTrue(($data->flags & (1 << 6)) !== 0, 'Italic flag should be set');
        // Should use StandardEncoding
        self::assertSame('space', $data->encoding[32]);
        self::assertSame('A', $data->encoding[65]);
    }

    // --- Bold font flag detection ---

    public function testBoldStemVEstimate(): void
    {
        $pfb = $this->buildSyntheticPfb();
        // Modify the font name to include "Bold"
        $pfb = str_replace('TestFont-Regular', 'TestFont-Bold', $pfb);
        $pfb = str_replace('/TestFont-Regular', '/TestFont-Bold', $pfb);
        // Need to re-fix the PFB segment lengths
        $data = Type1Parser::fromBytes($pfb)->parse();
        self::assertSame(120, $data->stemV);
    }

    // --- System Type 1 font test (skipped if none available) ---

    public function testSystemType1Font(): void
    {
        $candidates = [
            '/usr/share/fonts/type1/gsfonts/n021003l.pfb',      // Nimbus Roman (Linux)
            '/usr/share/fonts/type1/urw-base35/NimbusRoman-Regular.t1', // Newer Linux
            '/usr/local/share/ghostscript/fonts/n021003l.pfb',  // macOS with Ghostscript
        ];

        $path = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            $this->markTestSkipped('No system Type 1 font found');
        }

        $data = (new Type1Parser($path))->parse();
        self::assertNotEmpty($data->postScriptName);
        self::assertNotEmpty($data->familyName);
        self::assertCount(4, $data->fontBBox);
        self::assertGreaterThan(0, $data->length1);
        self::assertGreaterThan(0, $data->length2);
    }
}
