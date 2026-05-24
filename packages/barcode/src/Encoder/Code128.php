<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * Code 128 (subset B) encoder — ISO/IEC 15417 / ANSI/AIM BC4-1999.
 *
 * Encodes printable ASCII (codepoints 32–127) into the standard
 * Code 128 bar/space pattern with mandatory start, modulo-103
 * checksum, and stop characters. Subsets A and C aren't auto-switched
 * in v1; pass strings of printable ASCII only.
 */
final class Code128
{
    /**
     * Code 128 patterns, indexed by value 0–106. Each entry is a
     * sequence of six bar / space widths that sum to 11 modules; the
     * stop pattern (#106) is 13 modules. Bars and spaces alternate
     * starting with a bar.
     *
     * @var array<int, string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222',
        '122213', '122312', '132212', '221213', '221312', '231212',
        '112232', '122132', '122231', '113222', '123122', '123221',
        '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321',
        '112313', '132113', '132311', '211313', '231113', '231311',
        '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131',
        '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124',
        '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111',
        '241112', '134111', '111242', '121142', '121241', '114212',
        '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141',
        '411131', '211412', '211214', '211232',
        // Stop pattern — 13 modules.
        '2331112',
    ];

    private const START_B = 104;
    private const STOP = 106;

    /**
     * Encode `$data` as a {@see BarcodeBitmap}.
     */
    public static function encode(string $data, BarcodeOptions $options): BarcodeBitmap
    {
        if ($data === '') {
            throw new \InvalidArgumentException('Code 128 input must be non-empty.');
        }

        $values = [self::START_B];
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $code = ord($data[$i]);
            if ($code < 32 || $code > 127) {
                throw new \InvalidArgumentException(
                    "Code 128 subset B accepts ASCII 32-127 only; got byte {$code} at position {$i}.",
                );
            }
            $values[] = $code - 32;
        }

        // Modulo-103 checksum: start * 1 (well, weight) + sum(data_i * i)
        // Start gets weight 1; then each data char gets weight = position
        // starting at 1 for the first data character.
        $checksum = self::START_B;
        for ($i = 1; $i < count($values); $i++) {
            $checksum += $values[$i] * $i;
        }
        $values[] = $checksum % 103;
        $values[] = self::STOP;

        // Build the module row (a single row for 1D barcodes).
        $row = [];
        foreach ($values as $value) {
            $pattern = self::PATTERNS[$value];
            $isBar = true;
            $len = strlen($pattern);
            for ($i = 0; $i < $len; $i++) {
                $width = (int) $pattern[$i];
                for ($j = 0; $j < $width; $j++) {
                    $row[] = $isBar;
                }
                $isBar = !$isBar;
            }
        }

        return new BarcodeBitmap(
            modules: [$row],
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }
}
