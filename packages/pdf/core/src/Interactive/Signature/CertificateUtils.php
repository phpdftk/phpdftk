<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Signature;

/**
 * X.509 certificate utilities for LTV signature support.
 *
 * Provides certificate chain extraction from PKCS#7 blobs, PEM/DER
 * conversion, OCSP responder URL extraction, CRL distribution point
 * parsing, and certificate chain ordering.
 *
 * Uses PHP's OpenSSL extension and inline ASN.1 DER parsing (same
 * pattern as {@see TsaClient}).
 */
final class CertificateUtils
{
    /**
     * Extract DER-encoded X.509 certificates from a PKCS#7 SignedData blob.
     *
     * Parses the ContentInfo > SignedData > certificates [0] IMPLICIT SET
     * structure to extract all embedded certificates.
     *
     * @param string $derPkcs7 Raw DER-encoded PKCS#7 SignedData (hex-decoded /Contents value)
     * @return list<string> Array of DER-encoded X.509 certificates
     * @throws \RuntimeException if the structure cannot be parsed
     */
    public static function extractCertsFromPkcs7Der(string $derPkcs7): array
    {
        $len = strlen($derPkcs7);
        if ($len < 2) {
            throw new \RuntimeException('PKCS#7 data too short');
        }

        $pos = 0;

        // ContentInfo SEQUENCE
        self::expectTag($derPkcs7, $pos, 0x30, 'ContentInfo SEQUENCE');
        $pos++;
        self::readDerLength($derPkcs7, $pos, $len);

        // contentType OID (1.2.840.113549.1.7.2 = signedData)
        self::expectTag($derPkcs7, $pos, 0x06, 'contentType OID');
        $pos++;
        $oidLen = self::readDerLength($derPkcs7, $pos, $len);
        $pos += $oidLen; // skip OID bytes

        // content [0] EXPLICIT
        if ($pos >= $len) {
            throw new \RuntimeException('PKCS#7: unexpected end after contentType');
        }
        $tag = ord($derPkcs7[$pos]);
        if ($tag !== 0xA0) {
            throw new \RuntimeException(sprintf('PKCS#7: expected [0] EXPLICIT (0xA0), got 0x%02X', $tag));
        }
        $pos++;
        self::readDerLength($derPkcs7, $pos, $len);

        // SignedData SEQUENCE
        self::expectTag($derPkcs7, $pos, 0x30, 'SignedData SEQUENCE');
        $pos++;
        $signedDataLen = self::readDerLength($derPkcs7, $pos, $len);
        $signedDataEnd = $pos + $signedDataLen;

        // version INTEGER
        self::expectTag($derPkcs7, $pos, 0x02, 'version INTEGER');
        $pos++;
        $vLen = self::readDerLength($derPkcs7, $pos, $len);
        $pos += $vLen;

        // digestAlgorithms SET
        self::expectTag($derPkcs7, $pos, 0x31, 'digestAlgorithms SET');
        $pos++;
        $daLen = self::readDerLength($derPkcs7, $pos, $len);
        $pos += $daLen;

        // encapContentInfo SEQUENCE
        self::expectTag($derPkcs7, $pos, 0x30, 'encapContentInfo SEQUENCE');
        $pos++;
        $eciLen = self::readDerLength($derPkcs7, $pos, $len);
        $pos += $eciLen;

        // Now we should be at certificates [0] IMPLICIT or crls [1] or signerInfos SET
        $certs = [];
        while ($pos < $signedDataEnd) {
            $tag = ord($derPkcs7[$pos]);

            if ($tag === 0xA0) {
                // certificates [0] IMPLICIT SET OF Certificate
                $pos++;
                $certsLen = self::readDerLength($derPkcs7, $pos, $len);
                $certsEnd = $pos + $certsLen;

                while ($pos < $certsEnd) {
                    // Each certificate is a SEQUENCE
                    if (ord($derPkcs7[$pos]) !== 0x30) {
                        break;
                    }
                    $certStart = $pos;
                    $pos++;
                    $certBodyLen = self::readDerLength($derPkcs7, $pos, $len);
                    $pos += $certBodyLen;
                    $certs[] = substr($derPkcs7, $certStart, $pos - $certStart);
                }
                $pos = $certsEnd;
            } elseif ($tag === 0xA1) {
                // crls [1] IMPLICIT — skip
                $pos++;
                $crlsLen = self::readDerLength($derPkcs7, $pos, $len);
                $pos += $crlsLen;
            } elseif ($tag === 0x31) {
                // signerInfos SET — we're done with certificates
                break;
            } else {
                // Unknown tag — skip
                $pos++;
                $skipLen = self::readDerLength($derPkcs7, $pos, $len);
                $pos += $skipLen;
            }
        }

        if (empty($certs)) {
            throw new \RuntimeException('No certificates found in PKCS#7 SignedData');
        }

        return $certs;
    }

    /**
     * Convert a PEM-encoded certificate to DER.
     */
    public static function pemToDer(string $pem): string
    {
        $pem = preg_replace('/-----[A-Z ]+-----/', '', $pem) ?? '';
        $pem = preg_replace('/\s+/', '', $pem) ?? '';
        $der = base64_decode($pem, true);
        if ($der === false || $der === '') {
            throw new \RuntimeException('Failed to decode PEM to DER');
        }
        return $der;
    }

    /**
     * Convert a DER-encoded certificate to PEM.
     */
    public static function derToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    /**
     * Extract the OCSP responder URL from a certificate's Authority
     * Information Access (AIA) extension.
     *
     * @param string $derOrPemCert DER or PEM certificate
     * @return string|null OCSP responder URL, or null if not present
     */
    public static function getOcspResponderUrl(string $derOrPemCert): ?string
    {
        $pem = self::ensurePem($derOrPemCert);
        $parsed = openssl_x509_parse($pem);
        if ($parsed === false) {
            return null;
        }

        // AIA is in extensions.authorityInfoAccess
        $aia = $parsed['extensions']['authorityInfoAccess'] ?? null;
        if ($aia === null) {
            return null;
        }

        // Format: "OCSP - URI:http://ocsp.example.com\nCA Issuers - URI:http://..."
        if (preg_match('/OCSP\s*-\s*URI:(\S+)/i', $aia, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extract CRL Distribution Point URLs from a certificate.
     *
     * @param string $derOrPemCert DER or PEM certificate
     * @return list<string> HTTP/HTTPS URLs
     */
    public static function getCrlDistributionPointUrls(string $derOrPemCert): array
    {
        $pem = self::ensurePem($derOrPemCert);
        $parsed = openssl_x509_parse($pem);
        if ($parsed === false) {
            return [];
        }

        $cdp = $parsed['extensions']['crlDistributionPoints'] ?? null;
        if ($cdp === null) {
            return [];
        }

        // Format: "\nFull Name:\n  URI:http://crl.example.com/ca.crl\n..."
        $urls = [];
        if (preg_match_all('/URI:(\S+)/i', $cdp, $matches)) {
            foreach ($matches[1] as $url) {
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * Compute SHA-256 hash of the issuer's Distinguished Name (DER-encoded).
     *
     * Used in OCSP CertID.issuerNameHash.
     */
    public static function getIssuerNameHash(string $derCert, string $derIssuerCert): string
    {
        // The issuerNameHash is SHA-256 of the issuer's subject DN in DER form.
        // We extract it by DER-walking the issuer certificate to find the
        // subject field in the TBSCertificate.
        $subjectDer = self::extractSubjectDer($derIssuerCert);
        return hash('sha256', $subjectDer, binary: true);
    }

    /**
     * Compute SHA-256 hash of the issuer's public key (DER-encoded, without tag/length).
     *
     * Used in OCSP CertID.issuerKeyHash.
     */
    public static function getIssuerKeyHash(string $derIssuerCert): string
    {
        $keyBits = self::extractPublicKeyBits($derIssuerCert);
        return hash('sha256', $keyBits, binary: true);
    }

    /**
     * Extract the serial number from a certificate as raw DER INTEGER content bytes.
     */
    public static function getSerialNumberDer(string $derOrPemCert): string
    {
        $pem = self::ensurePem($derOrPemCert);
        $parsed = openssl_x509_parse($pem);
        if ($parsed === false) {
            throw new \RuntimeException('Cannot parse certificate');
        }

        $serial = $parsed['serialNumberHex'] ?? null;
        if ($serial === null) {
            throw new \RuntimeException('Certificate has no serial number');
        }

        // Pad to even length for hex2bin
        if (strlen($serial) % 2 !== 0) {
            $serial = '0' . $serial;
        }

        $bytes = hex2bin($serial);
        if ($bytes === false) {
            throw new \RuntimeException('Invalid serial number hex');
        }

        // Ensure positive integer encoding (prepend 0x00 if high bit set)
        if (strlen($bytes) > 0 && (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        }

        return $bytes;
    }

    /**
     * Order certificates from leaf (signer) to root, by matching
     * issuer/subject Distinguished Names.
     *
     * @param list<string> $derCerts Unordered DER-encoded certificates
     * @return list<string> Ordered leaf→root
     */
    public static function buildChain(array $derCerts): array
    {
        if (count($derCerts) <= 1) {
            return $derCerts;
        }

        // Parse subject and issuer for each cert
        $certInfo = [];
        foreach ($derCerts as $i => $der) {
            $pem = self::derToPem($der);
            $parsed = openssl_x509_parse($pem);
            if ($parsed === false) {
                continue;
            }
            $certInfo[$i] = [
                'der' => $der,
                'subject' => self::dnToString($parsed['subject'] ?? []),
                'issuer' => self::dnToString($parsed['issuer'] ?? []),
            ];
        }

        // Find the leaf: a cert whose subject is not the issuer of any other cert
        $allIssuers = array_column($certInfo, 'issuer');
        $leaf = null;
        foreach ($certInfo as $i => $info) {
            $isIssuerOfOther = false;
            foreach ($certInfo as $j => $other) {
                if ($i !== $j && $other['issuer'] === $info['subject']) {
                    $isIssuerOfOther = true;
                    break;
                }
            }
            // Also skip self-signed (those are roots)
            $isSelfSigned = $info['subject'] === $info['issuer'];
            if (!$isIssuerOfOther && !$isSelfSigned) {
                $leaf = $i;
                break;
            }
        }

        // If no clear leaf found (e.g., all self-signed), return as-is
        if ($leaf === null) {
            // Try again without the self-signed check
            foreach ($certInfo as $i => $info) {
                $isIssuerOfOther = false;
                foreach ($certInfo as $j => $other) {
                    if ($i !== $j && $other['issuer'] === $info['subject']) {
                        $isIssuerOfOther = true;
                        break;
                    }
                }
                if (!$isIssuerOfOther) {
                    $leaf = $i;
                    break;
                }
            }
        }

        if ($leaf === null) {
            return $derCerts;
        }

        // Build chain from leaf
        $chain = [$certInfo[$leaf]['der']];
        $used = [$leaf => true];
        $currentIssuer = $certInfo[$leaf]['issuer'];

        while (count($chain) < count($certInfo)) {
            $found = false;
            foreach ($certInfo as $i => $info) {
                if (isset($used[$i])) {
                    continue;
                }
                if ($info['subject'] === $currentIssuer) {
                    $chain[] = $info['der'];
                    $used[$i] = true;
                    $currentIssuer = $info['issuer'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                break;
            }
        }

        // Append any remaining certs not in the chain
        foreach ($certInfo as $i => $info) {
            if (!isset($used[$i])) {
                $chain[] = $info['der'];
            }
        }

        return $chain;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Ensure input is PEM-encoded. If it looks like DER, convert.
     */
    private static function ensurePem(string $data): string
    {
        if (str_contains($data, '-----BEGIN')) {
            return $data;
        }
        return self::derToPem($data);
    }

    /**
     * Normalize a DN array to a comparable string.
     *
     * @param array<string, string|list<string>> $dn
     */
    private static function dnToString(array $dn): string
    {
        $parts = [];
        foreach ($dn as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = "$key=$v";
                }
            } else {
                $parts[] = "$key=$value";
            }
        }
        sort($parts);
        return implode(',', $parts);
    }

    /**
     * Extract the subject Distinguished Name DER bytes from a certificate.
     *
     * Certificate ::= SEQUENCE {
     *   tbsCertificate TBSCertificate,
     *   ...
     * }
     * TBSCertificate ::= SEQUENCE {
     *   version [0] EXPLICIT INTEGER OPTIONAL,
     *   serialNumber INTEGER,
     *   signature AlgorithmIdentifier,
     *   issuer Name,
     *   validity Validity,
     *   subject Name,   <-- we want this
     *   ...
     * }
     */
    private static function extractSubjectDer(string $derCert): string
    {
        $len = strlen($derCert);
        $pos = 0;

        // Certificate SEQUENCE
        self::expectTag($derCert, $pos, 0x30, 'Certificate');
        $pos++;
        self::readDerLength($derCert, $pos, $len);

        // TBSCertificate SEQUENCE
        self::expectTag($derCert, $pos, 0x30, 'TBSCertificate');
        $pos++;
        self::readDerLength($derCert, $pos, $len);

        // version [0] EXPLICIT (optional)
        if ($pos < $len && ord($derCert[$pos]) === 0xA0) {
            $pos++;
            $vLen = self::readDerLength($derCert, $pos, $len);
            $pos += $vLen;
        }

        // serialNumber INTEGER
        self::skipTlv($derCert, $pos, $len);

        // signature AlgorithmIdentifier SEQUENCE
        self::skipTlv($derCert, $pos, $len);

        // issuer Name SEQUENCE
        self::skipTlv($derCert, $pos, $len);

        // validity SEQUENCE
        self::skipTlv($derCert, $pos, $len);

        // subject Name SEQUENCE — extract this
        $subjectStart = $pos;
        self::skipTlv($derCert, $pos, $len);
        return substr($derCert, $subjectStart, $pos - $subjectStart);
    }

    /**
     * Extract the raw public key bit string content from a certificate.
     *
     * After the subject in TBSCertificate comes subjectPublicKeyInfo:
     * SubjectPublicKeyInfo ::= SEQUENCE {
     *   algorithm AlgorithmIdentifier,
     *   subjectPublicKey BIT STRING
     * }
     *
     * We return the content of the BIT STRING (minus the unused-bits byte).
     */
    private static function extractPublicKeyBits(string $derCert): string
    {
        $len = strlen($derCert);
        $pos = 0;

        // Certificate SEQUENCE
        self::expectTag($derCert, $pos, 0x30, 'Certificate');
        $pos++;
        self::readDerLength($derCert, $pos, $len);

        // TBSCertificate SEQUENCE
        self::expectTag($derCert, $pos, 0x30, 'TBSCertificate');
        $pos++;
        self::readDerLength($derCert, $pos, $len);

        // version [0] EXPLICIT (optional)
        if ($pos < $len && ord($derCert[$pos]) === 0xA0) {
            $pos++;
            $vLen = self::readDerLength($derCert, $pos, $len);
            $pos += $vLen;
        }

        // serialNumber INTEGER
        self::skipTlv($derCert, $pos, $len);
        // signature AlgorithmIdentifier
        self::skipTlv($derCert, $pos, $len);
        // issuer Name
        self::skipTlv($derCert, $pos, $len);
        // validity
        self::skipTlv($derCert, $pos, $len);
        // subject Name
        self::skipTlv($derCert, $pos, $len);

        // subjectPublicKeyInfo SEQUENCE
        self::expectTag($derCert, $pos, 0x30, 'SubjectPublicKeyInfo');
        $pos++;
        self::readDerLength($derCert, $pos, $len);

        // algorithm AlgorithmIdentifier
        self::skipTlv($derCert, $pos, $len);

        // subjectPublicKey BIT STRING
        self::expectTag($derCert, $pos, 0x03, 'subjectPublicKey BIT STRING');
        $pos++;
        $bsLen = self::readDerLength($derCert, $pos, $len);
        // First byte is the unused-bits count (always 0 for keys)
        return substr($derCert, $pos + 1, $bsLen - 1);
    }

    // ------------------------------------------------------------------
    // ASN.1 DER parsing helpers (same pattern as TsaClient)
    // ------------------------------------------------------------------

    private static function expectTag(string $data, int $pos, int $expected, string $context): void
    {
        $len = strlen($data);
        if ($pos >= $len) {
            throw new \RuntimeException("DER: unexpected end of data at $context");
        }
        $tag = ord($data[$pos]);
        if ($tag !== $expected) {
            throw new \RuntimeException(sprintf(
                'DER: expected 0x%02X at %s, got 0x%02X at offset %d',
                $expected, $context, $tag, $pos,
            ));
        }
    }

    private static function readDerLength(string $data, int &$pos, int $dataLen): int
    {
        if ($pos >= $dataLen) {
            throw new \RuntimeException('DER: unexpected end of data reading length');
        }
        $byte = ord($data[$pos]);
        $pos++;

        if ($byte < 0x80) {
            return $byte;
        }

        $numBytes = $byte & 0x7F;
        if ($numBytes === 0 || $pos + $numBytes > $dataLen) {
            throw new \RuntimeException('DER: invalid length encoding');
        }

        $len = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $len = ($len << 8) | ord($data[$pos]);
            $pos++;
        }
        return $len;
    }

    /**
     * Skip a complete TLV (tag + length + value) at the current position.
     */
    private static function skipTlv(string $data, int &$pos, int $dataLen): void
    {
        if ($pos >= $dataLen) {
            throw new \RuntimeException('DER: unexpected end of data skipping TLV');
        }
        $pos++; // skip tag
        $len = self::readDerLength($data, $pos, $dataLen);
        $pos += $len;
    }
}
