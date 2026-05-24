<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * Code 39 (a.k.a. Code 3-of-9) encoder — ANSI/AIM BC1.
 *
 * Each character renders as 9 elements (5 bars + 4 spaces alternating)
 * with exactly 3 elements wide. The wide-to-narrow ratio is
 * configurable but defaults to 2:1 (the standard tight encoding).
 * Adjacent characters are separated by one narrow module of space.
 *
 * The encoder automatically prepends and appends the `*` start/stop
 * sentinel; do not include it in the input.
 */
final class Code39
{
    /**
     * Pattern map: 9 elements per character, alternating bar / space
     * starting with bar. `n` = narrow, `w` = wide.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn',
        'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn',
        'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn',
        'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn',
        'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn', '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn', // start / stop sentinel
    ];

    /**
     * Encode `$data` (no `*` sentinels) into a 1D barcode bitmap.
     *
     * `$wideRatio` controls the wide-bar width in modules; common
     * choices are 2 (tight) or 3 (loose, more readable).
     */
    public static function encode(string $data, BarcodeOptions $options, int $wideRatio = 2): BarcodeBitmap
    {
        if ($data === '') {
            throw new \InvalidArgumentException('Code 39 input must be non-empty.');
        }
        if ($wideRatio < 2 || $wideRatio > 3) {
            throw new \InvalidArgumentException("Code 39 wide ratio must be 2 or 3, got {$wideRatio}.");
        }

        // Prepend / append the * sentinel and validate every character.
        $sequence = '*' . strtoupper($data) . '*';
        $row = [];
        $length = strlen($sequence);
        for ($i = 0; $i < $length; $i++) {
            $ch = $sequence[$i];
            if (!isset(self::PATTERNS[$ch])) {
                throw new \InvalidArgumentException(
                    "Code 39 cannot encode '{$ch}' (position {$i}).",
                );
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
            // One narrow space between characters; skip after the last char.
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
