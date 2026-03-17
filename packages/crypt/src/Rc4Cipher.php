<?php declare(strict_types=1);
namespace Phpdftk\Crypt;

final class Rc4Cipher implements CryptInterface {
    public function encrypt(string $data, string $key): string {
        return self::rc4($data, $key);
    }

    public function decrypt(string $data, string $key): string {
        return self::rc4($data, $key);
    }

    private static function rc4(string $data, string $key): string {
        $keyLen = strlen($key);
        if ($keyLen === 0) return $data;

        // Key Scheduling Algorithm (KSA)
        $s = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLen])) & 0xFF;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }

        // Pseudo-Random Generation Algorithm (PRGA)
        $output = '';
        $i = 0;
        $j = 0;
        $dataLen = strlen($data);
        for ($k = 0; $k < $dataLen; $k++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $s[$i]) & 0xFF;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $output .= chr(ord($data[$k]) ^ $s[($s[$i] + $s[$j]) & 0xFF]);
        }

        return $output;
    }
}
