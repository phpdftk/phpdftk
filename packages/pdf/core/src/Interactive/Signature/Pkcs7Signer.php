<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Signature;

/**
 * PKCS#7 / CMS detached signer — ISO 32000-2 §12.8.3.3.
 *
 * Thin wrapper over PHP's `openssl_pkcs7_sign()` that produces the raw
 * DER-encoded PKCS#7 SignedData bytes used as the /Contents value of a
 * PDF {@see SignatureValue}. The signature is detached: the signed data
 * is the concatenation of the two byte ranges around the /Contents
 * placeholder in the serialized PDF.
 *
 * PHP's extension writes an SMIME multipart envelope to a file; we parse
 * it back out, extract the base64-encoded signature attachment, and
 * decode it to raw DER. This is how `TCPDF`, `setasign/SetaPDF-Signer`,
 * and similar libraries interoperate with openssl.
 *
 * Extra certificates (chain) and flags are passthroughs.
 */
final class Pkcs7Signer
{
    /** @var \OpenSSLCertificate|string */
    private $certificate;

    /** @var \OpenSSLAsymmetricKey|array{0: \OpenSSLCertificate|string, 1: string}|string */
    private $privateKey;

    /** @var list<\OpenSSLCertificate|string> */
    private array $extraCerts;

    /**
     * @param \OpenSSLCertificate|string                                                        $certificate  PEM cert or resource
     * @param \OpenSSLAsymmetricKey|array{0: \OpenSSLCertificate|string, 1: string}|string      $privateKey   PEM key, key+pass pair, or resource
     * @param list<\OpenSSLCertificate|string>                                                  $extraCerts   additional certs to include in the chain
     */
    public function __construct(
        $certificate,
        $privateKey,
        array $extraCerts = []
    ) {
        $this->certificate = $certificate;
        $this->privateKey = $privateKey;
        $this->extraCerts = $extraCerts;
    }

    /**
     * Sign `$data` and return raw DER PKCS#7 bytes suitable for the
     * /Contents entry of a signature value dictionary.
     */
    public function sign(string $data): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_sig_');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temp file for signing');
        }
        $in = $tmp . '.in';
        $out = $tmp . '.out';

        try {
            file_put_contents($in, $data);

            $extraCertsFile = null;
            if ($this->extraCerts !== []) {
                $extraCertsFile = $tmp . '.chain';
                $chain = '';
                foreach ($this->extraCerts as $cert) {
                    $chain .= is_string($cert)
                        ? $cert
                        : self::certificateToPem($cert);
                }
                file_put_contents($extraCertsFile, $chain);
            }

            $ok = openssl_pkcs7_sign(
                $in,
                $out,
                $this->certificate,
                $this->privateKey,
                [],
                PKCS7_BINARY | PKCS7_DETACHED,
                $extraCertsFile
            );
            if ($ok !== true) {
                throw new \RuntimeException(
                    'openssl_pkcs7_sign failed: ' . (openssl_error_string() ?: 'unknown error')
                );
            }

            $smime = file_get_contents($out);
            if ($smime === false) {
                throw new \RuntimeException('Failed to read signed output');
            }

            return self::extractDerFromSmime($smime);
        } finally {
            @unlink($tmp);
            @unlink($in);
            @unlink($out);
            if (isset($extraCertsFile)) {
                @unlink($extraCertsFile);
            }
        }
    }

    /**
     * Parse the base64 PKCS#7 attachment out of an SMIME multipart body
     * and return the decoded DER bytes.
     */
    public static function extractDerFromSmime(string $smime): string
    {
        // Find the signature attachment part.
        if (!preg_match(
            '/Content-Type:\s*application\/(?:x-)?pkcs7-signature[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n(.+?)\r?\n--/s',
            $smime,
            $m
        )) {
            throw new \RuntimeException('Could not locate pkcs7-signature part in SMIME output');
        }

        $base64 = preg_replace('/\s+/', '', $m[1]) ?? '';
        $der = base64_decode($base64, true);
        if ($der === false || $der === '') {
            throw new \RuntimeException('Failed to base64-decode pkcs7-signature');
        }
        return $der;
    }

    /**
     * Convenience: generate a throwaway self-signed cert + key pair for
     * tests and local demos. Not for production use.
     *
     * @return array{cert: string, key: string}
     */
    public static function createSelfSignedTestCredentials(
        string $commonName = 'phpdftk test',
        int $days = 365
    ): array {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $keyRes = openssl_pkey_new($config);
        if ($keyRes === false) {
            throw new \RuntimeException('openssl_pkey_new failed');
        }

        $csr = openssl_csr_new(
            ['commonName' => $commonName, 'organizationName' => 'phpdftk'],
            $keyRes,
            $config
        );
        if ($csr === false) {
            throw new \RuntimeException('openssl_csr_new failed');
        }

        $certRes = openssl_csr_sign($csr, null, $keyRes, $days, $config);
        if ($certRes === false) {
            throw new \RuntimeException('openssl_csr_sign failed');
        }

        openssl_x509_export($certRes, $certPem);
        openssl_pkey_export($keyRes, $keyPem);

        return ['cert' => $certPem, 'key' => $keyPem];
    }

    /** @param \OpenSSLCertificate|string $cert */
    private static function certificateToPem($cert): string
    {
        if (is_string($cert)) {
            return $cert;
        }
        openssl_x509_export($cert, $pem);
        return $pem;
    }
}
