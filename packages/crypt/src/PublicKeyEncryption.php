<?php

declare(strict_types=1);

namespace ApprLabs\Crypt;

/**
 * Public-key (certificate-based) PDF encryption primitives — ISO 32000-2 §7.6.5.
 *
 * Creates and opens PKCS#7 CMS EnvelopedData objects that wrap the
 * encryption seed for each recipient, and derives the file encryption
 * key per the public-key security handler specification.
 *
 * Uses PHP 8.1+ `openssl_cms_encrypt()`/`openssl_cms_decrypt()` for
 * reliable CMS envelope operations.
 */
final class PublicKeyEncryption
{
    /**
     * Create a PKCS#7 CMS EnvelopedData wrapping the seed + permissions
     * for a single recipient. Returns raw DER-encoded bytes.
     *
     * Per ISO 32000-2 §7.6.5.3, the enveloped content is:
     *   20-byte seed || 4-byte permissions (LE) || optional 4×0xFF
     *
     * @param string $seed           20-byte random seed
     * @param int    $permissions    Permission bitfield for this recipient
     * @param string $certPem        Recipient's X.509 certificate in PEM format
     * @param bool   $encryptMetadata Whether document metadata is encrypted
     */
    public static function createEnvelope(
        string $seed,
        int $permissions,
        string $certPem,
        bool $encryptMetadata = true,
    ): string {
        $content = $seed . pack('V', $permissions);
        if (!$encryptMetadata) {
            $content .= "\xFF\xFF\xFF\xFF";
        }

        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_pke_');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temp file for CMS encryption');
        }
        $inFile = $tmp . '.in';
        $outFile = $tmp . '.out';

        try {
            file_put_contents($inFile, $content);

            // Clear stale OpenSSL errors
            while (openssl_error_string() !== false) {
            }

            $result = openssl_cms_encrypt(
                $inFile,
                $outFile,
                $certPem,
                [],
                OPENSSL_CMS_BINARY | OPENSSL_CMS_NOINTERN,
                OPENSSL_ENCODING_DER,
                OPENSSL_CIPHER_AES_256_CBC,
            );

            if ($result !== true) {
                throw new \RuntimeException('openssl_cms_encrypt failed');
            }

            $der = file_get_contents($outFile);
            if ($der === false || $der === '') {
                throw new \RuntimeException('Failed to read CMS encrypted output');
            }

            return $der;
        } finally {
            @unlink($tmp);
            @unlink($inFile);
            @unlink($outFile);
        }
    }

    /**
     * Open a PKCS#7 CMS EnvelopedData to extract the 20-byte seed.
     *
     * @param string $pkcs7Der     DER-encoded PKCS#7 EnvelopedData
     * @param string $certPem      Recipient's X.509 certificate in PEM format
     * @param string $privateKeyPem Recipient's private key in PEM format
     * @return string|null         The 20-byte seed, or null if decryption fails
     */
    public static function openEnvelope(
        string $pkcs7Der,
        string $certPem,
        string $privateKeyPem,
    ): ?string {
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_pkd_');
        if ($tmp === false) {
            return null;
        }
        $inFile = $tmp . '.in';
        $outFile = $tmp . '.out';

        try {
            file_put_contents($inFile, $pkcs7Der);

            // Clear stale OpenSSL errors
            while (openssl_error_string() !== false) {
            }

            $result = openssl_cms_decrypt(
                $inFile,
                $outFile,
                $certPem,
                $privateKeyPem,
                OPENSSL_ENCODING_DER,
            );

            if ($result !== true) {
                return null;
            }

            $content = file_get_contents($outFile);
            if ($content === false || strlen($content) < 20) {
                return null;
            }

            // First 20 bytes = seed
            return substr($content, 0, 20);
        } finally {
            @unlink($tmp);
            @unlink($inFile);
            @unlink($outFile);
        }
    }

    /**
     * Derive the file encryption key per ISO 32000-2 §7.6.5.2.
     *
     * key = SHA-1(seed || recipient[0] || ... || recipient[n] || P_4bytes_LE || optional_0xFF×4)
     *
     * @param string   $seed               20-byte seed
     * @param string[] $recipientDerStrings Raw DER bytes of each PKCS#7 recipient object
     * @param int      $permissions         Combined permissions (AND of all recipients)
     * @param int      $keyLengthBytes      Desired key length in bytes (e.g. 16 for AES-128)
     * @param bool     $encryptMetadata     Whether metadata is encrypted
     */
    /**
     * Derive the file encryption key per ISO 32000-2 §7.6.5.2.
     *
     * Uses SHA-1 for key lengths up to 20 bytes (AES-128),
     * SHA-256 for longer keys (AES-256).
     *
     * @param string   $seed               20-byte seed
     * @param string[] $recipientDerStrings Raw DER bytes of each PKCS#7 recipient object
     * @param int      $permissions         Combined permissions (AND of all recipients)
     * @param int      $keyLengthBytes      Desired key length in bytes (16 for AES-128, 32 for AES-256)
     * @param bool     $encryptMetadata     Whether metadata is encrypted
     */
    public static function deriveFileKey(
        string $seed,
        array $recipientDerStrings,
        int $permissions,
        int $keyLengthBytes,
        bool $encryptMetadata = true,
    ): string {
        $input = $seed;
        foreach ($recipientDerStrings as $der) {
            $input .= $der;
        }
        $input .= pack('V', $permissions);
        if (!$encryptMetadata) {
            $input .= "\xFF\xFF\xFF\xFF";
        }

        // SHA-1 (20 bytes max) for AES-128; SHA-256 (32 bytes) for AES-256
        if ($keyLengthBytes > 20) {
            return substr(hash('sha256', $input, true), 0, $keyLengthBytes);
        }

        return substr(sha1($input, true), 0, $keyLengthBytes);
    }
}
