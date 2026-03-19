<?php declare(strict_types=1);
namespace ApprLabs\Filters;

final class Ascii85Filter implements FilterInterface {
    public function encode(string $data): string {
        $output = '';
        $len = strlen($data);
        $i = 0;
        while ($i < $len) {
            // Get up to 4 bytes
            $remaining = $len - $i;
            if ($remaining >= 4) {
                $b0 = ord($data[$i]);
                $b1 = ord($data[$i + 1]);
                $b2 = ord($data[$i + 2]);
                $b3 = ord($data[$i + 3]);
                $value = ($b0 << 24) | ($b1 << 16) | ($b2 << 8) | $b3;
                // Use unsigned 32-bit: PHP integers are 64-bit so handle sign
                $value = $value & 0xFFFFFFFF;
                if ($value === 0) {
                    $output .= 'z';
                } else {
                    $chars = '';
                    for ($j = 0; $j < 5; $j++) {
                        $chars = chr(($value % 85) + 33) . $chars;
                        $value = intdiv($value, 85);
                    }
                    $output .= $chars;
                }
                $i += 4;
            } else {
                // Partial last group: pad with zeros
                $bytes = [0, 0, 0, 0];
                for ($j = 0; $j < $remaining; $j++) {
                    $bytes[$j] = ord($data[$i + $j]);
                }
                $value = ($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3];
                $value = $value & 0xFFFFFFFF;
                $chars = '';
                for ($j = 0; $j < 5; $j++) {
                    $chars = chr(($value % 85) + 33) . $chars;
                    $value = intdiv($value, 85);
                }
                // Output only $remaining + 1 chars
                $output .= substr($chars, 0, $remaining + 1);
                $i += $remaining;
            }
        }
        $output .= '~>';
        return $output;
    }

    public function decode(string $data): string {
        $output = '';
        $len = strlen($data);
        $i = 0;
        $group = [];

        while ($i < $len) {
            $ch = $data[$i];

            // Skip whitespace
            if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === "\f") {
                $i++;
                continue;
            }

            // End of data marker
            if ($ch === '~') {
                if ($i + 1 < $len && $data[$i + 1] === '>') {
                    // Process remaining partial group
                    if (count($group) > 0) {
                        $n = count($group);
                        // Pad with 'u' (84) to make 5 chars
                        while (count($group) < 5) {
                            $group[] = 84; // 'u' - 33
                        }
                        $value = 0;
                        foreach ($group as $digit) {
                            $value = $value * 85 + $digit;
                        }
                        // Output only n-1 bytes
                        for ($j = 3; $j >= (4 - ($n - 1)); $j--) {
                            $output .= chr(($value >> ($j * 8)) & 0xFF);
                        }
                    }
                    break;
                }
                throw new \RuntimeException('Ascii85Filter: invalid ~ character');
            }

            // 'z' special case: all zeros
            if ($ch === 'z') {
                if (count($group) !== 0) {
                    throw new \RuntimeException('Ascii85Filter: z not at start of group');
                }
                $output .= "\x00\x00\x00\x00";
                $i++;
                continue;
            }

            // Regular character: must be in range '!' to 'u'
            $ord = ord($ch);
            if ($ord < 33 || $ord > 117) {
                throw new \RuntimeException("Ascii85Filter: invalid character '$ch' (ord $ord)");
            }
            $group[] = $ord - 33;
            if (count($group) === 5) {
                $value = 0;
                foreach ($group as $digit) {
                    $value = $value * 85 + $digit;
                }
                for ($j = 3; $j >= 0; $j--) {
                    $output .= chr(($value >> ($j * 8)) & 0xFF);
                }
                $group = [];
            }
            $i++;
        }

        return $output;
    }
}
