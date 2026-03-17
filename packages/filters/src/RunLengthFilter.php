<?php declare(strict_types=1);
namespace Phpdftk\Filters;

final class RunLengthFilter implements FilterInterface {
    public function encode(string $data): string {
        $output = '';
        $len = strlen($data);
        $i = 0;

        while ($i < $len) {
            // Check for run of repeated bytes
            $runByte = $data[$i];
            $runLen = 1;
            while ($runLen < 128 && ($i + $runLen) < $len && $data[$i + $runLen] === $runByte) {
                $runLen++;
            }

            if ($runLen >= 2) {
                // Repeated run: length byte 257 - runLen, then the byte
                $output .= chr(257 - $runLen) . $runByte;
                $i += $runLen;
            } else {
                // Literal run: scan forward for non-repeated bytes
                $litStart = $i;
                $litLen = 0;
                while ($litLen < 128 && ($i + $litLen) < $len) {
                    // Look ahead: if the next two bytes are the same, stop literal run
                    if ($litLen > 0 && ($i + $litLen + 1) < $len && $data[$i + $litLen] === $data[$i + $litLen + 1]) {
                        break;
                    }
                    // Also check if a run of 3+ identical bytes follows (worth encoding as run)
                    if (($i + $litLen + 2) < $len
                        && $data[$i + $litLen] === $data[$i + $litLen + 1]
                        && $data[$i + $litLen] === $data[$i + $litLen + 2]) {
                        break;
                    }
                    $litLen++;
                }
                if ($litLen === 0) {
                    $litLen = 1;
                }
                $output .= chr($litLen - 1) . substr($data, $litStart, $litLen);
                $i += $litLen;
            }
        }
        // EOD byte
        $output .= chr(128);
        return $output;
    }

    public function decode(string $data): string {
        $output = '';
        $len = strlen($data);
        $i = 0;

        while ($i < $len) {
            $length = ord($data[$i]);
            $i++;

            if ($length === 128) {
                // EOD
                break;
            } elseif ($length <= 127) {
                // Copy next length+1 bytes literally
                $count = $length + 1;
                $output .= substr($data, $i, $count);
                $i += $count;
            } else {
                // Repeat next byte 257-length times
                $count = 257 - $length;
                if ($i < $len) {
                    $output .= str_repeat($data[$i], $count);
                    $i++;
                }
            }
        }

        return $output;
    }
}
