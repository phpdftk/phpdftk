<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * PDF417 encoder — multi-row stacked 2D barcode (ISO/IEC 15438, AIM USS-PDF417).
 *
 * Encodes input bytes using Byte Compaction mode (mode 901 / 924) only — this
 * covers any arbitrary 8-bit payload at a slightly worse density than the
 * Text or Numeric specialised modes, but keeps the encoder simple and lossless.
 * Selects (rows, columns, ECC level) automatically to fit the payload while
 * staying within the spec limits (3–90 rows, 1–30 columns, levels 0–8).
 *
 * Each row consists of: start pattern (17 modules) → left row indicator
 * codeword (17 modules) → C data codewords (17 modules each) → right row
 * indicator codeword (17 modules) → stop pattern (18 modules). All codewords
 * within a row use the same cluster slot, cycling 0 → 1 → 2 → 0 across rows.
 *
 * @internal
 */
final class Pdf417
{
    /** Codeword pattern shared by all rows. */
    private const START_PATTERN = [8, 1, 1, 1, 1, 1, 1, 3];
    /** Stop pattern is 18 modules (one extra closing bar). */
    private const STOP_PATTERN = [7, 1, 1, 3, 1, 1, 1, 2, 1];

    /** Byte-compaction latch codewords. */
    private const LATCH_BYTE = 901;
    /** Byte-compaction latch when payload length is a multiple of 6. */
    private const LATCH_BYTE_MULT6 = 924;
    /** Padding codeword (text mode latch — interpreted as filler at this position). */
    private const PAD = 900;

    /** Maximum payload codewords (including SLD + ECC). */
    private const MAX_TOTAL_CODEWORDS = 928;

    public static function encode(string $data, BarcodeOptions $options): BarcodeBitmap
    {
        if ($data === '') {
            throw new \InvalidArgumentException('PDF417 requires non-empty input.');
        }

        $payload = self::byteCompact($data);
        [$rows, $cols, $level] = self::pickGeometry(count($payload));

        $eccCount = Pdf417ReedSolomon::ECC_COUNT[$level];
        $dataSlots = $rows * $cols;
        $sldAndPayloadLen = $dataSlots - $eccCount;

        // Prepend the symbol length descriptor (SLD = total data codewords including SLD).
        $data = [$sldAndPayloadLen];
        foreach ($payload as $cw) {
            $data[] = $cw;
        }
        // Pad up to the data-section length with PAD codewords.
        while (count($data) < $sldAndPayloadLen) {
            $data[] = self::PAD;
        }

        $ecc = Pdf417ReedSolomon::generate($data, $level);
        $codewords = array_merge($data, $ecc);

        return self::layout($codewords, $rows, $cols, $level, $options);
    }

    /**
     * Convert a byte string into PDF417 codewords using Byte Compaction mode.
     *
     * @return list<int>
     */
    private static function byteCompact(string $data): array
    {
        $len = strlen($data);
        $isMult6 = ($len > 0 && $len % 6 === 0);
        $codewords = [$isMult6 ? self::LATCH_BYTE_MULT6 : self::LATCH_BYTE];

        $i = 0;
        // Complete 6-byte groups → 5 codewords each (base-900 of base-256).
        while ($i + 6 <= $len) {
            $accum = 0;
            for ($j = 0; $j < 6; $j++) {
                $accum = $accum * 256 + ord($data[$i + $j]);
            }
            $group = [];
            for ($j = 0; $j < 5; $j++) {
                $group[] = $accum % 900;
                $accum = intdiv($accum, 900);
            }
            // Group was produced LSB-first; emit MSB-first.
            for ($j = 4; $j >= 0; $j--) {
                $codewords[] = $group[$j];
            }
            $i += 6;
        }
        // Trailing bytes (1–5) are emitted as single-byte codewords with byte value.
        while ($i < $len) {
            $codewords[] = ord($data[$i]);
            $i++;
        }
        return $codewords;
    }

    /**
     * Pick (rows, cols, ECC level) minimally fitting `payloadCount` payload codewords
     * (excluding the SLD itself, which the caller adds) plus the ECC codewords for
     * the chosen level. Errs on smaller symbols, then bumps ECC level up to the
     * recommended minimum for the codeword count.
     *
     * @return array{0:int,1:int,2:int}
     */
    private static function pickGeometry(int $payloadCount): array
    {
        // +1 for SLD
        $dataNeeded = $payloadCount + 1;
        // Recommended minimum ECC level based on data codeword count
        // (AIM spec table). Always at least level 2.
        $level = match (true) {
            $dataNeeded <= 40 => 2,
            $dataNeeded <= 160 => 3,
            $dataNeeded <= 320 => 4,
            $dataNeeded <= 863 => 5,
            default => 6,
        };
        $eccCount = Pdf417ReedSolomon::ECC_COUNT[$level];
        $totalNeeded = $dataNeeded + $eccCount;

        if ($totalNeeded > self::MAX_TOTAL_CODEWORDS) {
            throw new \InvalidArgumentException(
                "Payload too large for PDF417: needs $totalNeeded codewords (max " . self::MAX_TOTAL_CODEWORDS . ').',
            );
        }

        // Pick columns and rows. Default aspect ratio target: roughly 3:1 (cols:rows).
        $bestCols = null;
        $bestRows = null;
        for ($c = 1; $c <= 30; $c++) {
            $r = (int) ceil($totalNeeded / $c);
            if ($r < 3 || $r > 90) {
                continue;
            }
            // Prefer the smallest area; tiebreak by closer to a 3:1 cols:rows aspect.
            $area = $c * $r;
            if ($bestCols === null) {
                $bestCols = $c;
                $bestRows = $r;
                continue;
            }
            $bestArea = $bestCols * $bestRows;
            if (
                $area < $bestArea
                || ($area === $bestArea && abs($c / $r - 3) < abs($bestCols / $bestRows - 3))
            ) {
                $bestCols = $c;
                $bestRows = $r;
            }
        }
        if ($bestCols === null || $bestRows === null) {
            throw new \InvalidArgumentException(
                "Payload too large for PDF417: $totalNeeded codewords cannot fit in 3..90 rows × 1..30 cols.",
            );
        }

        return [$bestRows, $bestCols, $level];
    }

    /**
     * Convert codewords + geometry into the rendered bitmap.
     *
     * @param list<int> $codewords data + ECC, total `rows*cols` codewords.
     */
    private static function layout(array $codewords, int $rows, int $cols, int $level, BarcodeOptions $options): BarcodeBitmap
    {
        $modulesPerRow = 8 + 17 + ($cols * 17) + 17 + 18;
        $modules = [];

        for ($r = 0; $r < $rows; $r++) {
            $rowMods = self::renderRow($r, $rows, $cols, $level, $codewords);
            // PDF417 modules are spec-defined as 3:1 height:width — emit each
            // logical row as 3 pixel rows so the rendered bitmap matches the
            // canonical aspect ratio at the chosen module width.
            $modules[] = $rowMods;
            $modules[] = $rowMods;
            $modules[] = $rowMods;
        }

        return new BarcodeBitmap(
            modules: $modules,
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }

    /**
     * Render one row's 17×modules array.
     *
     * @param list<int> $codewords
     * @return list<bool>
     */
    private static function renderRow(int $r, int $rows, int $cols, int $level, array $codewords): array
    {
        $clusterSlot = $r % 3;
        // Compute left and right row indicators.
        [$leftRI, $rightRI] = self::rowIndicators($r, $rows, $cols, $level);

        $out = self::patternModules(self::START_PATTERN, true);

        // Left row indicator
        $out = array_merge($out, Pdf417Spec::modulesFor($leftRI, $clusterSlot));

        // Data codewords for this row
        for ($c = 0; $c < $cols; $c++) {
            $idx = $r * $cols + $c;
            $cw = $codewords[$idx] ?? self::PAD;
            $out = array_merge($out, Pdf417Spec::modulesFor($cw, $clusterSlot));
        }

        // Right row indicator
        $out = array_merge($out, Pdf417Spec::modulesFor($rightRI, $clusterSlot));

        // Stop pattern (18 modules)
        $out = array_merge($out, self::patternModules(self::STOP_PATTERN, true));

        return $out;
    }

    /**
     * Compute the (left, right) row indicator codeword values for row `r`.
     *
     * @return array{0:int,1:int}
     */
    private static function rowIndicators(int $r, int $rows, int $cols, int $level): array
    {
        $rowMod3 = $r % 3;
        $rowDiv3 = intdiv($r, 3);
        $rowsField = intdiv($rows - 1, 3);
        $rowsMod3 = ($rows - 1) % 3;
        $colsField = $cols - 1;
        $eccField = 3 * $level + $rowsMod3;

        $left = match ($rowMod3) {
            0 => 30 * $rowDiv3 + $rowsField,
            1 => 30 * $rowDiv3 + $eccField,
            2 => 30 * $rowDiv3 + $colsField,
            default => 0, // unreachable
        };
        $right = match ($rowMod3) {
            0 => 30 * $rowDiv3 + $colsField,
            1 => 30 * $rowDiv3 + $rowsField,
            2 => 30 * $rowDiv3 + $eccField,
            default => 0, // unreachable
        };
        return [$left, $right];
    }

    /**
     * Expand a width sequence (e.g., 8,1,1,1,1,1,1,3) into bar/space modules.
     * Starts with bar = `$startsBar`.
     *
     * @param list<int> $widths
     * @return list<bool>
     */
    private static function patternModules(array $widths, bool $startsBar): array
    {
        $out = [];
        $bar = $startsBar;
        foreach ($widths as $w) {
            for ($i = 0; $i < $w; $i++) {
                $out[] = $bar;
            }
            $bar = !$bar;
        }
        return $out;
    }
}
