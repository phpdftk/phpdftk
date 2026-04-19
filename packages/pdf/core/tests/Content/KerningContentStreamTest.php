<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Content;

use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\PdfDictionary;
use PHPUnit\Framework\TestCase;

class KerningContentStreamTest extends TestCase
{
    private function makeStream(): ContentStream
    {
        return new ContentStream(new Resources(), '');
    }

    public function testShowUnicodeTextKernedEmitsTjWhenNoKernPairs(): void
    {
        $cs = $this->makeStream();
        $unicodeToGid = [0x41 => 1, 0x42 => 2]; // A=1, B=2

        $cs->showUnicodeTextKerned('AB', $unicodeToGid, [], 1000);

        $pdf = $cs->toPdf();
        $this->assertStringContainsString('<00010002> Tj', $pdf);
    }

    public function testShowUnicodeTextKernedEmitsTjArrayWithAdjustments(): void
    {
        $cs = $this->makeStream();
        $unicodeToGid = [0x41 => 1, 0x56 => 2]; // A=1, V=2

        // kern: GID 1 + GID 2 = -80 design units (tighten)
        $kernPairs = [1 => [2 => -80]];

        $cs->showUnicodeTextKerned('AV', $unicodeToGid, $kernPairs, 1000);

        $pdf = $cs->toPdf();
        // kern -80 in 1000 upm → -(-80)*1000/1000 = 80 in TJ
        $this->assertStringContainsString('[ <0001> 80 <0002> ] TJ', $pdf);
    }

    public function testShowUnicodeTextKernedSignConvention(): void
    {
        $cs = $this->makeStream();
        $unicodeToGid = [0x41 => 1, 0x56 => 2];

        // Positive kern value (loosen) should produce negative TJ value
        $kernPairs = [1 => [2 => 50]];
        $cs->showUnicodeTextKerned('AV', $unicodeToGid, $kernPairs, 1000);

        $pdf = $cs->toPdf();
        $this->assertStringContainsString('-50', $pdf);
    }

    public function testShowUnicodeTextKernedScalesWithUnitsPerEm(): void
    {
        $cs = $this->makeStream();
        $unicodeToGid = [0x41 => 1, 0x56 => 2];

        // kern -100 in 2048 upm → -(-100)*1000/2048 = 49 in TJ (rounded)
        $kernPairs = [1 => [2 => -100]];
        $cs->showUnicodeTextKerned('AV', $unicodeToGid, $kernPairs, 2048);

        $pdf = $cs->toPdf();
        $this->assertStringContainsString('49', $pdf);
    }

    public function testShowUnicodeTextKernedWithMultipleAdjustments(): void
    {
        $cs = $this->makeStream();
        $unicodeToGid = [0x41 => 1, 0x56 => 2, 0x41 => 1];

        // A-V kern and V-A kern
        $kernPairs = [1 => [2 => -80], 2 => [1 => -60]];

        $cs->showUnicodeTextKerned('AVA', $unicodeToGid, $kernPairs, 1000);

        $pdf = $cs->toPdf();
        $this->assertStringContainsString('TJ', $pdf);
        // Should have two kern adjustments
        $this->assertStringContainsString('80', $pdf);
        $this->assertStringContainsString('60', $pdf);
    }

    public function testShowUnicodeTextKernedEmptyString(): void
    {
        $cs = $this->makeStream();
        $cs->showUnicodeTextKerned('', [], [], 1000);

        $pdf = $cs->toPdf();
        // Empty string should produce no operators
        $this->assertStringNotContainsString('Tj', $pdf);
        $this->assertStringNotContainsString('TJ', $pdf);
    }

    public function testShowUnicodeTextKernedConsecutiveNoKern(): void
    {
        $cs = $this->makeStream();
        $unicodeToGid = [0x41 => 1, 0x42 => 2, 0x43 => 3];

        // Kern only between B-C, not A-B
        $kernPairs = [2 => [3 => -40]];

        $cs->showUnicodeTextKerned('ABC', $unicodeToGid, $kernPairs, 1000);

        $pdf = $cs->toPdf();
        // A and B should be in one hex run, then kern, then C
        $this->assertStringContainsString('[ <00010002> 40 <0003> ] TJ', $pdf);
    }
}
