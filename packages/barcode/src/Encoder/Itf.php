<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * ITF (Interleaved 2 of 5) encoder — ISO/IEC 16390.
 *
 * Digit-only symbology that encodes pairs of digits together: the
 * first digit's pattern goes into 5 bars, the second's into 5 spaces.
 * Input length must be even; a leading 0 is conventionally added when
 * an odd-length number is needed.
 *
 * Each digit pattern has exactly 2 wide and 3 narrow elements
 * ("2 of 5"), hence the symbology name.
 */
final class Itf
{
    /**
     * 5-element pattern per digit (n/w only). When the digit is on
     * "bar" positions, the elements are bars; when on "space"
     * positions, they're spaces.
     */
    private const PATTERNS = [
        '0' => 'nnwwn', '1' => 'wnnnw', '2' => 'nwnnw',
        '3' => 'wwnnn', '4' => 'nnwnw', '5' => 'wnwnn',
        '6' => 'nwwnn', '7' => 'nnnww', '8' => 'wnnwn',
        '9' => 'nwnwn',
    ];

    public static function encode(string $data, BarcodeOptions $options, int $wideRatio = 2): BarcodeBitmap
    {
        if ($data === '' || !ctype_digit($data)) {
            throw new \InvalidArgumentException('ITF input must be non-empty digits only.');
        }
        if (strlen($data) % 2 !== 0) {
            throw new \InvalidArgumentException(
                'ITF input length must be even; pad with a leading zero for odd-length numbers.',
            );
        }
        if ($wideRatio < 2 || $wideRatio > 3) {
            throw new \InvalidArgumentException("ITF wide ratio must be 2 or 3, got {$wideRatio}.");
        }

        $row = [];
        // Start guard: narrow bar, narrow space, narrow bar, narrow space.
        $row[] = true;
        $row[] = false;
        $row[] = true;
        $row[] = false;

        $length = strlen($data);
        for ($i = 0; $i < $length; $i += 2) {
            $barPattern = self::PATTERNS[$data[$i]];
            $spacePattern = self::PATTERNS[$data[$i + 1]];
            for ($j = 0; $j < 5; $j++) {
                $barWidth = $barPattern[$j] === 'w' ? $wideRatio : 1;
                for ($k = 0; $k < $barWidth; $k++) {
                    $row[] = true;
                }
                $spaceWidth = $spacePattern[$j] === 'w' ? $wideRatio : 1;
                for ($k = 0; $k < $spaceWidth; $k++) {
                    $row[] = false;
                }
            }
        }

        // Stop guard: wide bar, narrow space, narrow bar.
        for ($k = 0; $k < $wideRatio; $k++) {
            $row[] = true;
        }
        $row[] = false;
        $row[] = true;

        return new BarcodeBitmap(
            modules: [$row],
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }
}
