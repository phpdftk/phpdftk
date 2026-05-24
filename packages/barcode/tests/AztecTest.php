<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Tests;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\BarcodeRenderer;
use Phpdftk\Barcode\Encoder\Aztec;
use Phpdftk\Barcode\Encoder\AztecGaloisField;
use Phpdftk\Barcode\Encoder\AztecHighLevelEncoder;
use Phpdftk\Barcode\Encoder\AztecReedSolomon;
use Phpdftk\Barcode\Encoder\AztecSpec;
use Phpdftk\Barcode\Symbology;
use PHPUnit\Framework\TestCase;

class AztecTest extends TestCase
{
    public function testEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Aztec::encode('', new BarcodeOptions());
    }

    public function testEncodesShortByteStringAsCompactL1(): void
    {
        $bitmap = Aztec::encode('HI', new BarcodeOptions());
        // Short payloads fit in Compact L=1 (15×15).
        self::assertSame(15, $bitmap->rows());
        self::assertSame(15, $bitmap->columns());
    }

    public function testEncodesLargerPayloadAtHigherLayer(): void
    {
        // ~30 bytes blows past L=1 capacity and lands in L=2 (19×19) or larger.
        $bitmap = Aztec::encode(str_repeat('A', 30), new BarcodeOptions());
        self::assertGreaterThanOrEqual(19, $bitmap->rows());
        self::assertSame($bitmap->rows(), $bitmap->columns(), 'Aztec symbols are square');
    }

    public function testPayloadOverflowsFullFormatCeiling(): void
    {
        // Even with the high-level encoder's text-mode density gains, ~4 000
        // bytes of arbitrary input blows past Full L=32's data capacity.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds Aztec Full format capacity');
        // Use non-ASCII bytes so the encoder is forced into binary shift mode
        // (text-mode tables would compress raw ASCII enough to fit).
        Aztec::encode(str_repeat("\xC0\xC1", 2500), new BarcodeOptions());
    }

    public function testHighLevelEncoderBeatsRawByteModeForAscii(): void
    {
        // A pure-ASCII payload must encode in fewer bits than 5 (B/S latch)
        // + 5/16 (length) + 8N (byte payload). For 30 upper-case chars the
        // byte-mode baseline is 5 + 5 + 240 = 250 bits; high-level should
        // compress to 5 × 30 = 150 bits via Upper mode.
        $payload = str_repeat('A', 30);
        $bits = AztecHighLevelEncoder::encode($payload);
        self::assertLessThan(250, count($bits), 'high-level beats byte mode');
        self::assertLessThanOrEqual(150, count($bits), 'Upper mode is 5 bits/char');
    }

    public function testHighLevelEncoderHandlesMixedAlphabets(): void
    {
        // Mixed casing + digits + punct exercises the full state search.
        $bits = AztecHighLevelEncoder::encode("Hello, World! 123");
        self::assertGreaterThan(0, count($bits));
        // Smaller than 5+5+8*17 = 146 bits byte-mode baseline.
        self::assertLessThan(146, count($bits));
    }

    public function testHighLevelEncoderRoundTripsArbitraryBinary(): void
    {
        // Bytes outside the text tables must fall through to Binary Shift —
        // the encoder still produces a valid stream, just at byte-mode density.
        $payload = "\x00\x01\x02\xff\xfe\xfd";
        $bits = AztecHighLevelEncoder::encode($payload);
        // 5 (B/S entry from Upper) + 5 (length=6) + 48 (6 bytes × 8) = 58 bits.
        self::assertSame(58, count($bits));
    }

    public function testPromotesToFullFormatWhenCompactOverflows(): void
    {
        // ~150 bytes exceeds Compact L=4 (max 64 data codewords × 8 bits = 64
        // bytes), so the picker must roll over to Full format. Full L=1 = 19×19.
        $bitmap = Aztec::encode(str_repeat('A', 150), new BarcodeOptions());
        self::assertGreaterThanOrEqual(19, $bitmap->rows());
        self::assertSame($bitmap->rows(), $bitmap->columns());
    }

    public function testFullFormatLayerCountTransitions(): void
    {
        // Sanity that growing payloads hit increasingly larger symbols and
        // the size formula stays consistent across the word-size boundaries
        // (6→8 at L=3, 8→10 at L=9, 10→12 at L=23).
        $sizes = [];
        foreach ([10, 100, 300, 800, 1500] as $n) {
            $b = Aztec::encode(str_repeat('Z', $n), new BarcodeOptions());
            $sizes[] = $b->rows();
        }
        // Strictly monotonic (each bigger payload → bigger or equal symbol).
        for ($i = 1; $i < count($sizes); $i++) {
            self::assertGreaterThanOrEqual($sizes[$i - 1], $sizes[$i]);
        }
    }

    public function testFullFormatBullseyeIs13Wide(): void
    {
        // Force Full L=1 (19×19) by passing ~75 bytes (overflows Compact).
        $bitmap = Aztec::encode(str_repeat('A', 80), new BarcodeOptions());
        $size = $bitmap->rows();
        self::assertGreaterThanOrEqual(19, $size);
        // For Full, the bullseye uses size=7 (13×13). Check the central rings.
        $center = intdiv($size, 2);
        self::assertTrue($bitmap->modules[$center][$center], 'centre bar');
        self::assertFalse($bitmap->modules[$center][$center + 1], 'd=1 space');
        self::assertTrue($bitmap->modules[$center][$center + 2], 'd=2 bar');
        self::assertTrue($bitmap->modules[$center][$center + 6], 'd=6 outer bullseye bar');
        // Asymmetric orientation: TL corner of 15×15 bullseye area is set.
        self::assertTrue($bitmap->modules[$center - 7][$center - 7], 'TL corner');
    }

    public function testRendererDelegatesToAztecEncoder(): void
    {
        $bitmap = BarcodeRenderer::render(Symbology::Aztec, 'phpdftk');
        self::assertSame($bitmap->rows(), $bitmap->columns());
    }

    public function testCompactBullseyeAndAsymmetricOrientationPixels(): void
    {
        // Render the smallest symbol and verify the 9×9 bullseye + 6
        // asymmetric orientation pixels per AIM ISO/IEC 24778 § 7.3.2.
        $bitmap = Aztec::encode('A', new BarcodeOptions());
        $cx = 7;
        $cy = 7;
        // Bullseye: centre module is bar, ring at distance 1 is space,
        // distance 2 is bar, distance 4 is bar (outer bullseye ring).
        self::assertTrue($bitmap->modules[$cy][$cx], 'centre is bar');
        self::assertFalse($bitmap->modules[$cy][$cx + 1], 'd=1 is space');
        self::assertTrue($bitmap->modules[$cy][$cx + 2], 'd=2 is bar');
        self::assertTrue($bitmap->modules[$cy][$cx + 4], 'd=4 is bar');
        // Orientation pixels (asymmetric):
        //   TL (cx-5, cy-5) BAR — plus (cx-4, cy-5) and (cx-5, cy-4) → 3 modules
        self::assertTrue($bitmap->modules[$cy - 5][$cx - 5], 'TL corner');
        self::assertTrue($bitmap->modules[$cy - 5][$cx - 4], 'TL+right');
        self::assertTrue($bitmap->modules[$cy - 4][$cx - 5], 'TL+below');
        //   TR (cx+5, cy-5) BAR — plus (cx+5, cy-4) → 2 modules
        self::assertTrue($bitmap->modules[$cy - 5][$cx + 5], 'TR corner');
        self::assertTrue($bitmap->modules[$cy - 4][$cx + 5], 'TR+below');
        //   BR (cx+5, cy+5) — actually only (cx+5, cy+4) is set, NOT the corner
        self::assertTrue($bitmap->modules[$cy + 4][$cx + 5], 'BR-above');
        //   BL (cx-5, cy+5) — no orientation pixel here (data territory)
    }

    public function testGaloisField16Multiplication(): void
    {
        // GF(16) with poly 0x13: known products derived from log/antilog tables.
        $gf = new AztecGaloisField(16, 0x13);
        self::assertSame(0, $gf->multiply(0, 5));
        self::assertSame(0, $gf->multiply(5, 0));
        self::assertSame(5, $gf->multiply(1, 5));
        self::assertSame(5, $gf->multiply(5, 1));
        // Verify (α^i) × (α^j) = α^(i+j mod 15).
        $alpha = $gf->antilog;
        for ($i = 0; $i < 15; $i++) {
            for ($j = 0; $j < 15; $j++) {
                self::assertSame(
                    $alpha[($i + $j) % 15],
                    $gf->multiply($alpha[$i], $alpha[$j]),
                    "α^$i × α^$j",
                );
            }
        }
    }

    public function testGaloisField64Multiplication(): void
    {
        $gf = new AztecGaloisField(64, 0x43);
        // α^i × α^j = α^(i+j mod 63)
        $alpha = $gf->antilog;
        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j < 10; $j++) {
                self::assertSame(
                    $alpha[($i + $j) % 63],
                    $gf->multiply($alpha[$i], $alpha[$j]),
                );
            }
        }
    }

    public function testReedSolomonDeterministic(): void
    {
        $gf = new AztecGaloisField(64, 0x43);
        $rs = new AztecReedSolomon($gf, 4);
        $a = $rs->encode([1, 2, 3, 4]);
        $b = $rs->encode([1, 2, 3, 4]);
        self::assertSame($a, $b);
        self::assertCount(4, $a);
        foreach ($a as $cw) {
            self::assertGreaterThanOrEqual(0, $cw);
            self::assertLessThan(64, $cw);
        }
    }

    public function testReedSolomonGf16ProducesValidEccRange(): void
    {
        $gf = new AztecGaloisField(16, 0x13);
        $rs = new AztecReedSolomon($gf, 5);
        // Compact mode-message inputs: 2 four-bit codewords.
        $ecc = $rs->encode([0, 12]);
        self::assertCount(5, $ecc);
        foreach ($ecc as $cw) {
            self::assertGreaterThanOrEqual(0, $cw);
            self::assertLessThan(16, $cw);
        }
    }

    public function testCompactLayerParametersMatchSpec(): void
    {
        // AIM ISO/IEC 24778 Compact format: size = 11 + 4L.
        foreach (AztecSpec::COMPACT_LAYERS as $L => $info) {
            self::assertSame(11 + 4 * $L, $info['size']);
            self::assertSame($L <= 2 ? 6 : 8, $info['wordSize']);
            // Total data area in bits ≥ codewords × wordSize.
            $dataAreaBits = $info['size'] ** 2 - 121; // 11×11 central core
            self::assertGreaterThanOrEqual($info['totalCodewords'] * $info['wordSize'], $dataAreaBits);
        }
    }

    public function testFullSymbolSizeMatchesSpec(): void
    {
        // Full format sizes derived from ZXing's formula
        //   matrixSize = (14 + 4L) + 1 + 2·floor((((14 + 4L)/2) − 1) / 15)
        // Each multiple of 15 in `(baseMatrixSize/2 − 1)` adds another pair of
        // reference grid lines, so L=5 jumps by 6 (first extra line), L=12 by
        // another 6 (second line), L=20 by another 6 (third line), etc.
        $expected = [
            1 => 19, 2 => 23, 3 => 27, 4 => 31,
            5 => 37, 6 => 41, 7 => 45, 8 => 49, 9 => 53, 10 => 57, 11 => 61,
            12 => 67, 13 => 71, 14 => 75, 15 => 79, 19 => 95,
            20 => 101, 22 => 109, 23 => 113, 32 => 151,
        ];
        foreach ($expected as $L => $size) {
            self::assertSame($size, AztecSpec::fullSize($L), "Full L=$L size");
        }
    }

    public function testWordSizeMatchesZxingTable(): void
    {
        // WORD_SIZE = { 4, 6, 6, 8, 8, 8, 8, 8, 8, 10, ... } in ZXing.NET,
        // indexed by layer count (0 = mode message, 1..32 = data layers).
        $expected = [
            1 => 6, 2 => 6, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8, 8 => 8,
            9 => 10, 10 => 10, 22 => 10, 23 => 12, 32 => 12,
        ];
        foreach ($expected as $L => $ws) {
            self::assertSame($ws, AztecSpec::wordSize($L));
        }
    }

    public function testFullAlignmentMapInsertsGapAtCentre(): void
    {
        // Full L=1: baseMatrixSize=18, matrixSize=19. The map should cover 18
        // logical positions and map them to 0..17 and 18 in the 19-wide matrix
        // — physical column 9 (the centre) is the reference grid line.
        $map = AztecSpec::fullAlignmentMap(1);
        self::assertCount(18, $map);
        // Lower half 0..8 maps to physical 0..8; upper half 9..17 maps to 10..18.
        for ($i = 0; $i < 9; $i++) {
            self::assertSame($i, $map[$i]);
        }
        for ($i = 0; $i < 9; $i++) {
            self::assertSame($i + 10, $map[$i + 9]);
        }
        // Physical column 9 is therefore unused by any logical index — the gap.
        self::assertNotContains(9, $map);
    }

    public function testGaloisField1024Multiplication(): void
    {
        // GF(1024) is used for Full L=9..22 (10-bit codewords).
        $gf = new AztecGaloisField(1024, 0x409);
        $alpha = $gf->antilog;
        // α^i × α^j = α^(i+j mod 1023)
        for ($i = 0; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                self::assertSame(
                    $alpha[($i + $j) % 1023],
                    $gf->multiply($alpha[$i], $alpha[$j]),
                );
            }
        }
    }

    public function testGaloisField4096Multiplication(): void
    {
        // GF(4096) is used for Full L=23..32 (12-bit codewords).
        $gf = new AztecGaloisField(4096, 0x1069);
        $alpha = $gf->antilog;
        for ($i = 0; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                self::assertSame(
                    $alpha[($i + $j) % 4095],
                    $gf->multiply($alpha[$i], $alpha[$j]),
                );
            }
        }
    }
}
