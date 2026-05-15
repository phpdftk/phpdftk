<?php

declare(strict_types=1);

namespace Phpdftk\Encoding\Tests;

use Phpdftk\Encoding\CMapParser;
use PHPUnit\Framework\TestCase;

class CMapParserTest extends TestCase
{
    public function testEmptyStreamReturnsEmpty(): void
    {
        $parser = new CMapParser();
        $this->assertSame([], $parser->parse(''));
    }

    public function testBeginbfcharSection(): void
    {
        $cmap = <<<CMAP
        2 beginbfchar
        <0041> <0041>
        <0042> <0042>
        endbfchar
        CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame(0x41, $result[0x41]);
        $this->assertSame(0x42, $result[0x42]);
    }

    public function testBeginbfrangeSectionWithStartDst(): void
    {
        $cmap = <<<CMAP
        1 beginbfrange
        <0030> <0039> <0030>
        endbfrange
        CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        for ($i = 0x30; $i <= 0x39; $i++) {
            $this->assertSame($i, $result[$i]);
        }
    }

    public function testBeginbfrangeSectionWithArray(): void
    {
        $cmap = <<<CMAP
        1 beginbfrange
        <0000> <0002> [<00A0> <00A1> <00A2>]
        endbfrange
        CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame(0xA0, $result[0x00]);
        $this->assertSame(0xA1, $result[0x01]);
        $this->assertSame(0xA2, $result[0x02]);
    }

    public function testBeginbfrangeArrayWithShorterListLeavesExtraCodesUnmapped(): void
    {
        $cmap = <<<CMAP
        1 beginbfrange
        <0010> <0014> [<00E0> <00E1>]
        endbfrange
        CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame(0xE0, $result[0x10]);
        $this->assertSame(0xE1, $result[0x11]);
        $this->assertArrayNotHasKey(0x12, $result);
    }

    public function testMultipleSectionsCombined(): void
    {
        $cmap = <<<CMAP
        2 beginbfchar
        <0041> <0041>
        endbfchar
        1 beginbfrange
        <0050> <0052> <00B0>
        endbfrange
        CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame(0x41, $result[0x41]);
        $this->assertSame(0xB0, $result[0x50]);
        $this->assertSame(0xB1, $result[0x51]);
        $this->assertSame(0xB2, $result[0x52]);
    }

    public function testSkipsBlankAndMalformedLines(): void
    {
        $cmap = <<<CMAP
        beginbfchar

        not-a-pdf-line
        <0041> <0041>

        endbfchar
        CMAP;
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame([0x41 => 0x41], $result);
    }

    public function testCrlfLineEndings(): void
    {
        $cmap = "beginbfchar\r\n<0041> <0041>\r\nendbfchar";
        $parser = new CMapParser();
        $result = $parser->parse($cmap);
        $this->assertSame(0x41, $result[0x41]);
    }
}
