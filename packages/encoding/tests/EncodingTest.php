<?php declare(strict_types=1);

namespace ApprLabs\Encoding\Tests;

use PHPUnit\Framework\TestCase;
use ApprLabs\Encoding\WinAnsiTable;
use ApprLabs\Encoding\MacRomanTable;
use ApprLabs\Encoding\StandardEncodingTable;
use ApprLabs\Encoding\MacExpertEncodingTable;
use ApprLabs\Encoding\PdfDocEncodingTable;
use ApprLabs\Encoding\GlyphList;
use ApprLabs\Encoding\CMapParser;

class EncodingTest extends TestCase
{
    public function testWinAnsiTableHas256Entries(): void
    {
        $table = WinAnsiTable::getTable();
        $this->assertCount(256, $table);
    }

    public function testWinAnsiTableSpotChecks(): void
    {
        $table = WinAnsiTable::getTable();
        $this->assertSame('space', $table[32]);
        $this->assertSame('A', $table[65]);
        $this->assertSame('Z', $table[90]);
        $this->assertSame('a', $table[97]);
        $this->assertSame('z', $table[122]);
        $this->assertSame('zero', $table[48]);
        $this->assertSame('nine', $table[57]);
        $this->assertSame('Euro', $table[128]);
        $this->assertSame('OE', $table[140]);
        $this->assertSame('trademark', $table[153]);
        $this->assertSame('Agrave', $table[192]);
        $this->assertSame('ydieresis', $table[255]);
    }

    public function testWinAnsiTableNotdefEntries(): void
    {
        $table = WinAnsiTable::getTable();
        $this->assertSame('.notdef', $table[0]);
        $this->assertSame('.notdef', $table[31]);
        $this->assertSame('.notdef', $table[127]);
        $this->assertSame('.notdef', $table[129]);
        $this->assertSame('.notdef', $table[141]);
        $this->assertSame('.notdef', $table[143]);
        $this->assertSame('.notdef', $table[144]);
        $this->assertSame('.notdef', $table[157]);
    }

    public function testMacRomanTableHas256Entries(): void
    {
        $table = MacRomanTable::getTable();
        $this->assertCount(256, $table);
    }

    public function testMacRomanTableSpotChecks(): void
    {
        $table = MacRomanTable::getTable();
        $this->assertSame('space', $table[32]);
        $this->assertSame('A', $table[65]);
        $this->assertSame('Adieresis', $table[128]);
        $this->assertSame('aring', $table[140]);
        $this->assertSame('caron', $table[255]);
    }

    public function testGlyphListGlyphToUnicode(): void
    {
        $this->assertSame(65, GlyphList::glyphToUnicode('A'));
        $this->assertSame(97, GlyphList::glyphToUnicode('a'));
        $this->assertSame(48, GlyphList::glyphToUnicode('zero'));
        $this->assertSame(57, GlyphList::glyphToUnicode('nine'));
        $this->assertSame(32, GlyphList::glyphToUnicode('space'));
        $this->assertSame(8364, GlyphList::glyphToUnicode('Euro'));
    }

    public function testGlyphListGlyphToUnicodeUnknown(): void
    {
        $this->assertNull(GlyphList::glyphToUnicode('nonexistentglyph'));
    }

    public function testGlyphListUnicodeToGlyph(): void
    {
        $this->assertSame('Euro', GlyphList::unicodeToGlyph(8364));
        $this->assertSame('A', GlyphList::unicodeToGlyph(65));
        $this->assertSame('space', GlyphList::unicodeToGlyph(32));
    }

    public function testGlyphListUnicodeToGlyphUnknown(): void
    {
        $this->assertNull(GlyphList::unicodeToGlyph(999999));
    }

    // --- StandardEncoding ---

    public function testStandardEncodingTableHas256Entries(): void
    {
        $table = StandardEncodingTable::getTable();
        $this->assertCount(256, $table);
    }

    public function testStandardEncodingTableSpotChecks(): void
    {
        $table = StandardEncodingTable::getTable();
        $this->assertSame('space', $table[32]);
        $this->assertSame('A', $table[65]);
        $this->assertSame('Z', $table[90]);
        $this->assertSame('a', $table[97]);
        $this->assertSame('z', $table[122]);
        $this->assertSame('zero', $table[48]);
        $this->assertSame('nine', $table[57]);
        // StandardEncoding-specific differences from WinAnsi
        $this->assertSame('quoteright', $table[39]);  // WinAnsi has 'quotesingle'
        $this->assertSame('quoteleft', $table[96]);   // WinAnsi has 'grave'
        $this->assertSame('fi', $table[174]);
        $this->assertSame('fl', $table[175]);
        $this->assertSame('endash', $table[177]);
        $this->assertSame('emdash', $table[208]);
        $this->assertSame('AE', $table[225]);
        $this->assertSame('Oslash', $table[233]);
        $this->assertSame('ae', $table[241]);
        $this->assertSame('oslash', $table[249]);
        $this->assertSame('germandbls', $table[251]);
    }

    public function testStandardEncodingTableNotdefEntries(): void
    {
        $table = StandardEncodingTable::getTable();
        $this->assertSame('.notdef', $table[0]);
        $this->assertSame('.notdef', $table[31]);
        $this->assertSame('.notdef', $table[127]);
        $this->assertSame('.notdef', $table[128]);
        $this->assertSame('.notdef', $table[160]);
        $this->assertSame('.notdef', $table[176]);
        $this->assertSame('.notdef', $table[181]);
        $this->assertSame('.notdef', $table[190]);
        $this->assertSame('.notdef', $table[192]);
    }

    // --- MacExpertEncoding ---

    public function testMacExpertEncodingTableHas256Entries(): void
    {
        $table = MacExpertEncodingTable::getTable();
        $this->assertCount(256, $table);
    }

    public function testMacExpertEncodingTableSpotChecks(): void
    {
        $table = MacExpertEncodingTable::getTable();
        $this->assertSame('space', $table[202]);
        $this->assertSame('zerooldstyle', $table[48]);
        $this->assertSame('oneoldstyle', $table[49]);
        $this->assertSame('nineoldstyle', $table[57]);
        $this->assertSame('Asmall', $table[94]);
        $this->assertSame('Zsmall', $table[119]);
        $this->assertSame('fi', $table[242]);
        $this->assertSame('fl', $table[243]);
        $this->assertSame('onequarter', $table[133]);
        $this->assertSame('onehalf', $table[134]);
        $this->assertSame('threequarters', $table[135]);
        $this->assertSame('onesuperior', $table[144]);
        $this->assertSame('zeroinferior', $table[153]);
    }

    // --- PdfDocEncoding ---

    public function testPdfDocEncodingTableHas256Entries(): void
    {
        $table = PdfDocEncodingTable::getTable();
        $this->assertCount(256, $table);
    }

    public function testPdfDocEncodingAsciiRange(): void
    {
        $table = PdfDocEncodingTable::getTable();
        // ASCII range maps directly to Unicode code points
        for ($i = 32; $i <= 126; $i++) {
            $this->assertSame($i, $table[$i], "Mismatch at byte $i");
        }
    }

    public function testPdfDocEncodingControlCodes(): void
    {
        $table = PdfDocEncodingTable::getTable();
        $this->assertNull($table[0]);
        $this->assertNull($table[7]);
        $this->assertSame(0x0009, $table[9]);   // TAB
        $this->assertSame(0x000A, $table[10]);  // LF
        $this->assertSame(0x000D, $table[13]);  // CR
        $this->assertNull($table[14]);
        $this->assertNull($table[127]);
    }

    public function testPdfDocEncodingDiacritics(): void
    {
        $table = PdfDocEncodingTable::getTable();
        $this->assertSame(0x02D8, $table[24]);  // BREVE
        $this->assertSame(0x02C7, $table[25]);  // CARON
        $this->assertSame(0x02C6, $table[26]);  // CIRCUMFLEX
        $this->assertSame(0x02DC, $table[31]);  // TILDE
    }

    public function testPdfDocEncodingHighBytes(): void
    {
        $table = PdfDocEncodingTable::getTable();
        $this->assertSame(0x2022, $table[128]); // BULLET
        $this->assertSame(0x2020, $table[129]); // DAGGER
        $this->assertSame(0x2014, $table[132]); // EM DASH
        $this->assertSame(0x2013, $table[133]); // EN DASH
        $this->assertSame(0x20AC, $table[160]); // EURO SIGN
        $this->assertSame(0xFB01, $table[147]); // FI LIGATURE
        $this->assertSame(0xFB02, $table[148]); // FL LIGATURE
        $this->assertSame(0x0152, $table[150]); // OE LIGATURE
    }

    public function testPdfDocEncodingLatin1Supplement(): void
    {
        $table = PdfDocEncodingTable::getTable();
        // 161-255 map directly to Unicode (same as ISO 8859-1)
        for ($i = 161; $i <= 255; $i++) {
            $this->assertSame($i, $table[$i], "Mismatch at byte $i");
        }
    }

    public function testPdfDocEncodingDecodeAscii(): void
    {
        $this->assertSame('Hello', PdfDocEncodingTable::decode('Hello'));
    }

    public function testPdfDocEncodingDecodeHighBytes(): void
    {
        // Byte 128 = BULLET (U+2022)
        $result = PdfDocEncodingTable::decode("\x80");
        $this->assertSame("\xE2\x80\xA2", $result); // UTF-8 for U+2022
    }

    public function testPdfDocEncodingDecodeTextStringUtf16(): void
    {
        // UTF-16BE BOM + "AB"
        $utf16 = "\xFE\xFF\x00\x41\x00\x42";
        $this->assertSame('AB', PdfDocEncodingTable::decodeTextString($utf16));
    }

    public function testPdfDocEncodingDecodeTextStringUtf8Bom(): void
    {
        // UTF-8 BOM + "Hello"
        $utf8 = "\xEF\xBB\xBFHello";
        $this->assertSame('Hello', PdfDocEncodingTable::decodeTextString($utf8));
    }

    public function testPdfDocEncodingDecodeTextStringFallback(): void
    {
        // No BOM — uses PDFDocEncoding
        $this->assertSame('Test', PdfDocEncodingTable::decodeTextString('Test'));
    }

    public function testCMapParserBfchar(): void
    {
        $cmap = <<<'CMAP'
/CIDInit /ProcSet findresource begin
12 dict begin
begincmap
/CIDSystemInfo 3 dict dup begin
  /Registry (Adobe) def
  /Ordering (UCS) def
  /Supplement 0 def
end def
/CMapName /Adobe-Identity-UCS def
/CMapType 2 def
2 beginbfchar
<0041> <0041>
<0042> <0042>
endbfchar
endcmap
CMapType end end
CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame(0x41, $result[0x41]);
        $this->assertSame(0x42, $result[0x42]);
    }

    public function testCMapParserBfrange(): void
    {
        $cmap = <<<'CMAP'
begincmap
2 beginbfrange
<0041> <005A> <0041>
endbfrange
endcmap
CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        // A-Z range
        for ($i = 0; $i < 26; $i++) {
            $this->assertSame(0x41 + $i, $result[0x41 + $i]);
        }
    }

    public function testCMapParserMultipleSections(): void
    {
        $cmap = <<<'CMAP'
begincmap
1 beginbfchar
<0020> <0020>
endbfchar
1 beginbfrange
<0061> <007A> <0061>
endbfrange
endcmap
CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame(0x20, $result[0x20]);
        $this->assertSame(0x61, $result[0x61]);
        $this->assertSame(0x7A, $result[0x7A]);
    }
}
