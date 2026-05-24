<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * EAN-13 / EAN-8 / UPC-A encoder family — ISO/IEC 15420.
 *
 * Shared infrastructure: L (odd parity), G (even parity, mirror of R),
 * and R (right) digit patterns; guard bars; modulo-10 checksum
 * (alternating ×1 / ×3 weighting). The first digit of an EAN-13 is
 * encoded implicitly via the parity sequence of the next six digits.
 *
 * Input may be supplied with or without the trailing checksum digit:
 *   - 12 digits → EAN-13 (checksum computed)
 *   - 13 digits → EAN-13 (checksum verified)
 *   - 7 digits  → EAN-8 (checksum computed)
 *   - 8 digits  → EAN-8 (checksum verified)
 *   - 11 digits → UPC-A (checksum computed)
 *   - 12 digits with leading 0 → UPC-A (also valid as EAN-13)
 */
final class Ean
{
    /** L-patterns (left odd parity), one per digit 0-9. */
    private const L = [
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011',
    ];

    /** G-patterns (left even parity, mirror of R). */
    private const G = [
        '0100111', '0110011', '0011011', '0100001', '0011101',
        '0111001', '0000101', '0010001', '0001001', '0010111',
    ];

    /** R-patterns (right, complement of L). */
    private const R = [
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100',
    ];

    /**
     * EAN-13 first-digit parity sequence. Determines whether each of
     * the 6 left-side digits is encoded with L or G.
     */
    private const FIRST_DIGIT_PARITY = [
        '0' => 'LLLLLL', '1' => 'LLGLGG', '2' => 'LLGGLG',
        '3' => 'LLGGGL', '4' => 'LGLLGG', '5' => 'LGGLLG',
        '6' => 'LGGGLL', '7' => 'LGLGLG', '8' => 'LGLGGL',
        '9' => 'LGGLGL',
    ];

    /**
     * Compute the EAN/UPC mod-10 checksum digit for the given digit
     * string. Weights alternate 1 and 3 from the rightmost digit.
     */
    public static function checksum(string $digits): int
    {
        $sum = 0;
        $len = strlen($digits);
        for ($i = 0; $i < $len; $i++) {
            $weight = ($len - $i) % 2 === 0 ? 1 : 3;
            $sum += (int) $digits[$i] * $weight;
        }
        return (10 - $sum % 10) % 10;
    }

    public static function encodeEan13(string $data, BarcodeOptions $options): BarcodeBitmap
    {
        $digits = self::normaliseFixedLength($data, 12, 'EAN-13');
        return self::renderEan13($digits, $options);
    }

    public static function encodeEan8(string $data, BarcodeOptions $options): BarcodeBitmap
    {
        $digits = self::normaliseFixedLength($data, 7, 'EAN-8');

        $row = [];
        self::appendBits($row, '101'); // start guard
        for ($i = 0; $i < 4; $i++) {
            self::appendBits($row, self::L[(int) $digits[$i]]);
        }
        self::appendBits($row, '01010'); // middle guard
        for ($i = 4; $i < 8; $i++) {
            self::appendBits($row, self::R[(int) $digits[$i]]);
        }
        self::appendBits($row, '101'); // end guard

        return new BarcodeBitmap(
            modules: [$row],
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }

    public static function encodeUpcA(string $data, BarcodeOptions $options): BarcodeBitmap
    {
        // UPC-A is EAN-13 with a leading 0. Accept either 11 (no
        // checksum) or 12 (with checksum) digits.
        $clean = preg_replace('/\s+/', '', $data) ?? $data;
        if ($clean === '' || !ctype_digit($clean)) {
            throw new \InvalidArgumentException("UPC-A input must be digits only; got '{$data}'.");
        }
        if (strlen($clean) === 11) {
            $clean .= (string) self::checksum($clean);
        } elseif (strlen($clean) !== 12) {
            throw new \InvalidArgumentException(
                'UPC-A input must be 11 or 12 digits, got ' . strlen($clean) . '.',
            );
        }
        return self::renderEan13('0' . $clean, $options);
    }

    /** @param list<bool> $row */
    private static function appendBits(array &$row, string $bits): void
    {
        $len = strlen($bits);
        for ($i = 0; $i < $len; $i++) {
            $row[] = $bits[$i] === '1';
        }
    }

    private static function normaliseFixedLength(string $data, int $payloadLen, string $label): string
    {
        $clean = preg_replace('/\s+/', '', $data) ?? $data;
        if ($clean === '' || !ctype_digit($clean)) {
            throw new \InvalidArgumentException("{$label} input must be digits only; got '{$data}'.");
        }
        $expected = $payloadLen + 1;
        if (strlen($clean) === $payloadLen) {
            return $clean . (string) self::checksum($clean);
        }
        if (strlen($clean) === $expected) {
            $body = substr($clean, 0, $payloadLen);
            $provided = (int) $clean[$payloadLen];
            $computed = self::checksum($body);
            if ($provided !== $computed) {
                throw new \InvalidArgumentException(
                    "{$label} checksum mismatch: expected {$computed}, got {$provided}.",
                );
            }
            return $clean;
        }
        throw new \InvalidArgumentException(
            "{$label} input must be {$payloadLen} or {$expected} digits, got " . strlen($clean) . '.',
        );
    }

    private static function renderEan13(string $digits, BarcodeOptions $options): BarcodeBitmap
    {
        $firstDigit = $digits[0];
        $parity = self::FIRST_DIGIT_PARITY[$firstDigit];

        $row = [];
        self::appendBits($row, '101'); // start guard
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $digits[$i + 1];
            $pattern = $parity[$i] === 'L' ? self::L[$digit] : self::G[$digit];
            self::appendBits($row, $pattern);
        }
        self::appendBits($row, '01010'); // middle guard
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $digits[$i + 7];
            self::appendBits($row, self::R[$digit]);
        }
        self::appendBits($row, '101'); // end guard

        return new BarcodeBitmap(
            modules: [$row],
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }
}
