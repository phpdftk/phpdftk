<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Content;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use PHPUnit\Framework\TestCase;

class LigatureContentStreamTest extends TestCase
{
    private function makeStream(): ContentStream
    {
        return new ContentStream(new Resources(), '');
    }

    public function testShowUnicodeTextShapedAppliesLigature(): void
    {
        $cs = $this->makeStream();
        // f=GID1, i=GID2; fi ligature = GID 100
        $unicodeToGid = [0x66 => 1, 0x69 => 2]; // 'f' => 1, 'i' => 2
        $ligatures = [
            1 => [['components' => [2], 'ligature' => 100]],
        ];

        $cs->showUnicodeTextShaped('fi', $unicodeToGid, $ligatures);

        $pdf = $cs->toPdf();
        // Should emit ligature GID 100 (0x0064) as hex
        $this->assertStringContainsString('<0064>', $pdf);
        $this->assertStringContainsString('Tj', $pdf);
    }

    public function testShowUnicodeTextShapedWithKerning(): void
    {
        $cs = $this->makeStream();
        // A=GID1, V=GID2
        $unicodeToGid = [0x41 => 1, 0x56 => 2];
        $ligatures = []; // no ligatures
        $kernPairs = [1 => [2 => -80]]; // AV kern

        $cs->showUnicodeTextShaped('AV', $unicodeToGid, $ligatures, $kernPairs, 1000);

        $pdf = $cs->toPdf();
        // Should have kern adjustment in TJ
        $this->assertStringContainsString('80', $pdf);
        $this->assertStringContainsString('TJ', $pdf);
    }

    public function testShowUnicodeTextShapedWithBothLigaturesAndKerning(): void
    {
        $cs = $this->makeStream();
        // f=1, i=2, A=3, V=4; fi ligature = 100
        $unicodeToGid = [0x66 => 1, 0x69 => 2, 0x41 => 3, 0x56 => 4];
        $ligatures = [
            1 => [['components' => [2], 'ligature' => 100]],
        ];
        $kernPairs = [3 => [4 => -80]]; // AV kern

        // "fiAV" → [100, 3, 4] with kern between 3-4
        $cs->showUnicodeTextShaped('fiAV', $unicodeToGid, $ligatures, $kernPairs, 1000);

        $pdf = $cs->toPdf();
        $this->assertStringContainsString('TJ', $pdf);
        $this->assertStringContainsString('0064', $pdf); // ligature GID 100 = 0x0064
        $this->assertStringContainsString('80', $pdf);    // kern value
    }

    public function testShowUnicodeTextShapedEmptyString(): void
    {
        $cs = $this->makeStream();
        $cs->showUnicodeTextShaped('', [], []);

        $pdf = $cs->toPdf();
        $this->assertStringNotContainsString('Tj', $pdf);
        $this->assertStringNotContainsString('TJ', $pdf);
    }

    public function testShowUnicodeTextShapedNoLigatureMatch(): void
    {
        $cs = $this->makeStream();
        $unicodeToGid = [0x41 => 1, 0x42 => 2]; // A=1, B=2
        $ligatures = [
            5 => [['components' => [6], 'ligature' => 100]], // unrelated
        ];

        $cs->showUnicodeTextShaped('AB', $unicodeToGid, $ligatures);

        $pdf = $cs->toPdf();
        // Should render both GIDs normally
        $this->assertStringContainsString('<00010002>', $pdf);
    }
}
