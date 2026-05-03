<?php declare(strict_types=1);
namespace Phpdftk\Crypt;

/**
 * AES-CBC cipher for PDF encryption (ISO 32000-2 §7.6.3).
 *
 * Supports 128-bit and 256-bit keys. The 16-byte IV is prepended to
 * the ciphertext on encrypt and stripped on decrypt, per the PDF spec
 * requirement that each encrypted string/stream carries its own IV.
 */
final class AesCipher implements CryptInterface {
    public function __construct(private int $keyBits = 128) {
        if ($keyBits !== 128 && $keyBits !== 256) {
            throw new \InvalidArgumentException("keyBits must be 128 or 256, got $keyBits");
        }
    }

    public function encrypt(string $data, string $key): string {
        $iv = random_bytes(16);
        $cipherMethod = "AES-{$this->keyBits}-CBC";
        // Pad or truncate key to required length
        $keyBytes = $this->keyBits / 8;
        $key = str_pad(substr($key, 0, $keyBytes), $keyBytes, "\x00");

        $encrypted = openssl_encrypt($data, $cipherMethod, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('AES encryption failed: ' . openssl_error_string());
        }
        return $iv . $encrypted;
    }

    public function decrypt(string $data, string $key): string {
        if (strlen($data) < 16) {
            throw new \RuntimeException('AES decrypt: data too short (missing IV)');
        }
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $cipherMethod = "AES-{$this->keyBits}-CBC";
        $keyBytes = $this->keyBits / 8;
        $key = str_pad(substr($key, 0, $keyBytes), $keyBytes, "\x00");

        $decrypted = openssl_decrypt($ciphertext, $cipherMethod, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('AES decryption failed: ' . openssl_error_string());
        }
        return $decrypted;
    }
}
