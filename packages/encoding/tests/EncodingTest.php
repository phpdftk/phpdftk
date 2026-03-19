<?php declare(strict_types=1);

namespace ApprLabs\Encoding\Tests;

use PHPUnit\Framework\TestCase;
use ApprLabs\Encoding\WinAnsiTable;
use ApprLabs\Encoding\MacRomanTable;
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
