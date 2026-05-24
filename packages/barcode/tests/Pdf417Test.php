<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\BarcodeRenderer;
use Phpdftk\Barcode\Encoder\Pdf417;
use Phpdftk\Barcode\Encoder\Pdf417ReedSolomon;
use Phpdftk\Barcode\Encoder\Pdf417Spec;
use Phpdftk\Barcode\Symbology;
use PHPUnit\Framework\TestCase;

class Pdf417Test extends TestCase
{
    public function testEncodesNonEmptyInput(): void
    {
        $bitmap = Pdf417::encode('PDF417 test', new BarcodeOptions());
        self::assertGreaterThanOrEqual(3 * 3, $bitmap->rows(), 'minimum 3 rows × 3 height pixels');
        self::assertGreaterThan(17 + 17 + 17 + 18, $bitmap->columns(), 'row width must exceed start+left RI+right RI+stop');
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Pdf417::encode('', new BarcodeOptions());
    }

    public function testEveryRowStartsWithFullStartPattern(): void
    {
        $bitmap = Pdf417::encode('row check', new BarcodeOptions());
        // Each logical row is rendered as 3 pixel rows; sample the first pixel row of each.
        $logicalRows = intdiv($bitmap->rows(), 3);
        for ($r = 0; $r < $logicalRows; $r++) {
            $row = $bitmap->modules[$r * 3];
            // Start pattern: 8 leading bars (modules 0–7 all dark).
            for ($i = 0; $i < 8; $i++) {
                self::assertTrue($row[$i], "row $r module $i must be bar (start pattern prefix)");
            }
            self::assertFalse($row[8], "row $r module 8 must be space (start pattern S1)");
            // Stop pattern ends with a bar (the trailing closing module).
            self::assertTrue($row[count($row) - 1], "row $r must end with bar (stop pattern closer)");
        }
    }

    public function testRendererDelegatesToPdf417Encoder(): void
    {
        $bitmap = BarcodeRenderer::render(Symbology::PDF417, 'phpdftk');
        self::assertGreaterThanOrEqual(3, intdiv($bitmap->rows(), 3));
    }

    public function testPayloadTooLargeThrows(): void
    {
        // Maximum capacity is 928 codewords minus ECC. ~1100 bytes of byte
        // compaction roughly fills the largest symbol. 4000 bytes overflows.
        $this->expectException(\InvalidArgumentException::class);
        Pdf417::encode(str_repeat('A', 4000), new BarcodeOptions());
    }

    public function testReedSolomonGeneratesDeterministicEcc(): void
    {
        // Same data + same level → identical ECC.
        $data = [10, 453, 178, 121, 239];
        $eccA = Pdf417ReedSolomon::generate($data, 1);
        $eccB = Pdf417ReedSolomon::generate($data, 1);
        self::assertSame($eccA, $eccB);
        // Level 1 → 4 ECC codewords.
        self::assertCount(4, $eccA);
        // All codewords in valid range.
        foreach ($eccA as $cw) {
            self::assertGreaterThanOrEqual(0, $cw);
            self::assertLessThan(929, $cw);
        }
    }

    public function testReedSolomonEccLengthMatchesLevel(): void
    {
        $data = [1, 2, 3, 4, 5];
        foreach (range(0, 8) as $level) {
            $ecc = Pdf417ReedSolomon::generate($data, $level);
            self::assertCount(2 ** ($level + 1), $ecc, "level $level → 2^(level+1) ECC codewords");
        }
    }

    public function testEveryCodewordPatternHas17Modules(): void
    {
        for ($slot = 0; $slot < 3; $slot++) {
            foreach (Pdf417Spec::CLUSTERS[$slot] as $v => $packed) {
                $modules = Pdf417Spec::modulesFor($v, $slot);
                self::assertCount(17, $modules, "cluster slot $slot codeword $v");
                self::assertTrue($modules[0], "first module of cluster $slot codeword $v must be bar");
            }
        }
    }

    public function testClustersEachHave929UniquePatterns(): void
    {
        foreach (Pdf417Spec::CLUSTERS as $slot => $cluster) {
            self::assertCount(929, $cluster, "cluster slot $slot must have 929 codewords");
            self::assertCount(929, array_unique($cluster), "cluster slot $slot patterns must be unique");
        }
    }

    public function testClusterConstraintHoldsForEverySpecEntry(): void
    {
        $expectedK = [0, 3, 6];
        foreach (Pdf417Spec::CLUSTERS as $slot => $cluster) {
            foreach ($cluster as $v => $packed) {
                $b1 = intdiv($packed, 10_000_000) % 10;
                $b2 = intdiv($packed, 100_000) % 10;
                $b3 = intdiv($packed, 1000) % 10;
                $b4 = intdiv($packed, 10) % 10;
                $K = ($b1 - $b2 + $b3 - $b4 + 9) % 9;
                self::assertSame($expectedK[$slot], $K, "cluster slot $slot codeword $v: pattern $packed must be in cluster {$expectedK[$slot]}");
            }
        }
    }
}
