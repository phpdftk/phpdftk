<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * Codabar (a.k.a. NW-7, USD-4, Code 2-of-7) encoder — AIM USS-Codabar.
 *
 * Encodes digits and a few symbols using 7 elements per character (4
 * bars + 3 spaces). Start / stop characters are `A`, `B`, `C`, or `D`
 * and must appear as the first / last input characters.
 */
final class Codabar
{
    /**
     * 7-element pattern per character: alternating bar/space starting
     * with bar; `n` = narrow, `w` = wide.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        '0' => 'nnnnnww', '1' => 'nnnnwwn', '2' => 'nnnwnnw',
        '3' => 'wwnnnnn', '4' => 'nnwnnwn', '5' => 'wnnnnwn',
        '6' => 'nwnnnnw', '7' => 'nwnnwnn', '8' => 'nwwnnnn',
        '9' => 'wnnwnnn',
        '-' => 'nnnwwnn', '$' => 'nnwwnnn',
        ':' => 'wnnnwnw', '/' => 'wnwnnnw',
        '.' => 'wnwnwnn', '+' => 'nnwnwnw',
        // Start / stop characters (also use the same widths but are
        // typically rendered with specific decorative styles in some
        // viewers — encoders just emit the bit pattern).
        'A' => 'nnwwnwn', 'B' => 'nwnwnnw',
        'C' => 'nnnwnww', 'D' => 'nnnwwwn',
    ];

    public static function encode(string $data, BarcodeOptions $options, int $wideRatio = 2): BarcodeBitmap
    {
        if (strlen($data) < 3) {
            throw new \InvalidArgumentException(
                'Codabar input must include start char, body, and stop char (at least 3 characters).',
            );
        }
        if ($wideRatio < 2 || $wideRatio > 3) {
            throw new \InvalidArgumentException("Codabar wide ratio must be 2 or 3, got {$wideRatio}.");
        }
        $upper = strtoupper($data);
        $start = $upper[0];
        $stop = $upper[strlen($upper) - 1];
        if (!in_array($start, ['A', 'B', 'C', 'D'], true)) {
            throw new \InvalidArgumentException("Codabar start char must be A/B/C/D; got '{$start}'.");
        }
        if (!in_array($stop, ['A', 'B', 'C', 'D'], true)) {
            throw new \InvalidArgumentException("Codabar stop char must be A/B/C/D; got '{$stop}'.");
        }

        $row = [];
        $length = strlen($upper);
        for ($i = 0; $i < $length; $i++) {
            $ch = $upper[$i];
            if (!isset(self::PATTERNS[$ch])) {
                throw new \InvalidArgumentException("Codabar cannot encode '{$ch}' (position {$i}).");
            }
            $pattern = self::PATTERNS[$ch];
            $isBar = true;
            $patternLen = strlen($pattern);
            for ($j = 0; $j < $patternLen; $j++) {
                $width = $pattern[$j] === 'w' ? $wideRatio : 1;
                for ($k = 0; $k < $width; $k++) {
                    $row[] = $isBar;
                }
                $isBar = !$isBar;
            }
            if ($i < $length - 1) {
                $row[] = false;
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
