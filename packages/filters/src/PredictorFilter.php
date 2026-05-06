<?php

declare(strict_types=1);

namespace Phpdftk\Filters;

/**
 * PDF predictor filter — applies/removes PNG and TIFF prediction
 * as specified in ISO 32000-2 §7.4.4.4.
 *
 * Predictors reduce redundancy in row-based data (e.g., image scanlines)
 * before compression. They are used as a pre-processing step with
 * FlateDecode and LZWDecode.
 *
 * Predictor values:
 *   1  = No prediction (default)
 *   2  = TIFF Predictor 2 (horizontal differencing)
 *  10  = PNG None
 *  11  = PNG Sub
 *  12  = PNG Up
 *  13  = PNG Average
 *  14  = PNG Paeth
 *  15  = PNG Optimum (per-row selector via tag byte)
 */
final class PredictorFilter
{
    public function __construct(
        private readonly int $predictor = 1,
        private readonly int $columns = 1,
        private readonly int $colors = 1,
        private readonly int $bitsPerComponent = 8,
    ) {}

    public function decode(string $data): string
    {
        if ($this->predictor === 1) {
            return $data;
        }

        if ($this->predictor === 2) {
            return $this->decodeTiff($data);
        }

        if ($this->predictor >= 10 && $this->predictor <= 15) {
            return $this->decodePng($data);
        }

        return $data;
    }

    public function encode(string $data): string
    {
        if ($this->predictor === 1) {
            return $data;
        }

        if ($this->predictor === 2) {
            return $this->encodeTiff($data);
        }

        if ($this->predictor >= 10 && $this->predictor <= 15) {
            return $this->encodePng($data);
        }

        return $data;
    }

    // -----------------------------------------------------------------------
    // TIFF Predictor 2 — horizontal differencing per row
    // -----------------------------------------------------------------------

    private function decodeTiff(string $data): string
    {
        $bytesPerPixel = (int) ceil($this->colors * $this->bitsPerComponent / 8);
        $rowBytes = (int) ceil($this->columns * $this->colors * $this->bitsPerComponent / 8);
        if ($rowBytes <= 0) {
            return $data;
        }
        $len = strlen($data);
        $result = '';

        for ($offset = 0; $offset < $len; $offset += $rowBytes) {
            $row = substr($data, $offset, $rowBytes);
            // Pad partial final row with zeros
            if (strlen($row) < $rowBytes) {
                $row = str_pad($row, $rowBytes, "\x00");
            }
            for ($i = $bytesPerPixel; $i < $rowBytes; $i++) {
                $row[$i] = chr((ord($row[$i]) + ord($row[$i - $bytesPerPixel])) & 0xFF);
            }
            $result .= $row;
        }

        return $result;
    }

    private function encodeTiff(string $data): string
    {
        $bytesPerPixel = (int) ceil($this->colors * $this->bitsPerComponent / 8);
        $rowBytes = (int) ceil($this->columns * $this->colors * $this->bitsPerComponent / 8);
        $len = strlen($data);
        $result = '';

        for ($offset = 0; $offset < $len; $offset += $rowBytes) {
            $row = substr($data, $offset, $rowBytes);
            // Process right-to-left to avoid overwriting values we still need
            for ($i = strlen($row) - 1; $i >= $bytesPerPixel; $i--) {
                $row[$i] = chr((ord($row[$i]) - ord($row[$i - $bytesPerPixel])) & 0xFF);
            }
            $result .= $row;
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // PNG predictors — each row has a tag byte followed by filtered scanline
    // -----------------------------------------------------------------------

    private function decodePng(string $data): string
    {
        $bytesPerPixel = max(1, (int) floor($this->colors * $this->bitsPerComponent / 8));
        $rowBytes = (int) ceil($this->columns * $this->colors * $this->bitsPerComponent / 8);
        $stride = $rowBytes + 1; // +1 for the tag byte
        $len = strlen($data);
        $result = '';
        $prevRow = str_repeat("\x00", $rowBytes);

        for ($offset = 0; $offset < $len; $offset += $stride) {
            if ($offset >= $len) {
                break;
            }
            $tag = ord($data[$offset]);
            $row = substr($data, $offset + 1, $rowBytes);

            if (strlen($row) < $rowBytes) {
                // Partial last row — pass through
                $result .= $row;
                break;
            }

            $decoded = match ($tag) {
                0 => $row, // None
                1 => $this->pngDecodeSub($row, $bytesPerPixel),
                2 => $this->pngDecodeUp($row, $prevRow),
                3 => $this->pngDecodeAverage($row, $prevRow, $bytesPerPixel),
                4 => $this->pngDecodePaeth($row, $prevRow, $bytesPerPixel),
                default => $row,
            };

            $result .= $decoded;
            $prevRow = $decoded;
        }

        return $result;
    }

    private function encodePng(string $data): string
    {
        $bytesPerPixel = max(1, (int) floor($this->colors * $this->bitsPerComponent / 8));
        $rowBytes = (int) ceil($this->columns * $this->colors * $this->bitsPerComponent / 8);
        $len = strlen($data);
        $result = '';
        $prevRow = str_repeat("\x00", $rowBytes);

        // Use the predictor tag: Sub(1) is simple and effective
        /** @var int<0,4> $tag */
        $tag = match ($this->predictor) {
            10 => 0,
            11 => 1,
            13 => 3,
            14 => 4,
            default => 2, // Optimum(15) and Up(12) — use Up as default
        };

        for ($offset = 0; $offset < $len; $offset += $rowBytes) {
            $row = substr($data, $offset, $rowBytes);

            $encoded = match ($tag) {
                0 => $row,
                1 => $this->pngEncodeSub($row, $bytesPerPixel),
                2 => $this->pngEncodeUp($row, $prevRow),
                3 => $this->pngEncodeAverage($row, $prevRow, $bytesPerPixel),
                4 => $this->pngEncodePaeth($row, $prevRow, $bytesPerPixel),
                default => $row,
            };

            $result .= chr($tag) . $encoded;
            $prevRow = $row;
        }

        return $result;
    }

    // --- PNG Sub ---

    private function pngDecodeSub(string $row, int $bpp): string
    {
        $len = strlen($row);
        for ($i = $bpp; $i < $len; $i++) {
            $row[$i] = chr((ord($row[$i]) + ord($row[$i - $bpp])) & 0xFF);
        }
        return $row;
    }

    private function pngEncodeSub(string $row, int $bpp): string
    {
        $len = strlen($row);
        $result = $row;
        for ($i = $len - 1; $i >= $bpp; $i--) {
            $result[$i] = chr((ord($row[$i]) - ord($row[$i - $bpp])) & 0xFF);
        }
        return $result;
    }

    // --- PNG Up ---

    private function pngDecodeUp(string $row, string $prevRow): string
    {
        $len = strlen($row);
        for ($i = 0; $i < $len; $i++) {
            $row[$i] = chr((ord($row[$i]) + ord($prevRow[$i])) & 0xFF);
        }
        return $row;
    }

    private function pngEncodeUp(string $row, string $prevRow): string
    {
        $len = strlen($row);
        $result = $row;
        for ($i = 0; $i < $len; $i++) {
            $result[$i] = chr((ord($row[$i]) - ord($prevRow[$i])) & 0xFF);
        }
        return $result;
    }

    // --- PNG Average ---

    private function pngDecodeAverage(string $row, string $prevRow, int $bpp): string
    {
        $len = strlen($row);
        for ($i = 0; $i < $len; $i++) {
            $a = $i >= $bpp ? ord($row[$i - $bpp]) : 0;
            $b = ord($prevRow[$i]);
            $row[$i] = chr((ord($row[$i]) + (int) floor(($a + $b) / 2)) & 0xFF);
        }
        return $row;
    }

    private function pngEncodeAverage(string $row, string $prevRow, int $bpp): string
    {
        $len = strlen($row);
        $result = $row;
        for ($i = 0; $i < $len; $i++) {
            $a = $i >= $bpp ? ord($row[$i - $bpp]) : 0;
            $b = ord($prevRow[$i]);
            $result[$i] = chr((ord($row[$i]) - (int) floor(($a + $b) / 2)) & 0xFF);
        }
        return $result;
    }

    // --- PNG Paeth ---

    private function pngDecodePaeth(string $row, string $prevRow, int $bpp): string
    {
        $len = strlen($row);
        for ($i = 0; $i < $len; $i++) {
            $a = $i >= $bpp ? ord($row[$i - $bpp]) : 0;
            $b = ord($prevRow[$i]);
            $c = $i >= $bpp ? ord($prevRow[$i - $bpp]) : 0;
            $row[$i] = chr((ord($row[$i]) + self::paethPredictor($a, $b, $c)) & 0xFF);
        }
        return $row;
    }

    private function pngEncodePaeth(string $row, string $prevRow, int $bpp): string
    {
        $len = strlen($row);
        $result = $row;
        for ($i = 0; $i < $len; $i++) {
            $a = $i >= $bpp ? ord($row[$i - $bpp]) : 0;
            $b = ord($prevRow[$i]);
            $c = $i >= $bpp ? ord($prevRow[$i - $bpp]) : 0;
            $result[$i] = chr((ord($row[$i]) - self::paethPredictor($a, $b, $c)) & 0xFF);
        }
        return $result;
    }

    private static function paethPredictor(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }
        if ($pb <= $pc) {
            return $b;
        }
        return $c;
    }
}
