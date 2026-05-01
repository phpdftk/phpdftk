<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Signature;

/**
 * OCSP (Online Certificate Status Protocol) client — RFC 6960.
 *
 * Builds OCSP requests, sends them to the responder specified in the
 * certificate's Authority Information Access extension, and returns
 * the raw DER-encoded OCSP response suitable for embedding in a
 * {@see \ApprLabs\Pdf\Core\Document\DSS}.
 *
 * Uses inline ASN.1 DER encoding (same pattern as {@see TsaClient}).
 */
final class OcspClient
{
    /** SHA-256 OID: 2.16.840.1.101.3.4.2.1 */
    private const OID_SHA256 = "\x60\x86\x48\x01\x65\x03\x04\x02\x01";

    private int $timeout;

    /**
     * @param int $timeout HTTP request timeout in seconds
     */
    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    /**
     * Fetch an OCSP response for a certificate from its designated responder.
     *
     * @param string $derCert        DER-encoded certificate to check
     * @param string $derIssuerCert  DER-encoded issuer certificate
     * @return string Raw DER-encoded OCSPResponse
     * @throws \RuntimeException on network error, missing OCSP URL, or responder error
     */
    public function getOcspResponse(string $derCert, string $derIssuerCert): string
    {
        $url = CertificateUtils::getOcspResponderUrl($derCert);
        if ($url === null) {
            throw new \RuntimeException('Certificate does not contain an OCSP responder URL (no AIA extension)');
        }

        $request = $this->buildOcspRequest($derCert, $derIssuerCert);
        $response = $this->sendRequest($url, $request);
        $this->parseOcspResponse($response);

        return $response;
    }

    /**
     * Build an OCSPRequest in ASN.1 DER format.
     *
     * OCSPRequest ::= SEQUENCE {
     *   tbsRequest TBSRequest
     * }
     * TBSRequest ::= SEQUENCE {
     *   version [0] EXPLICIT INTEGER DEFAULT v1, -- omit for v1
     *   requestList SEQUENCE OF Request
     * }
     * Request ::= SEQUENCE {
     *   reqCert CertID
     * }
     * CertID ::= SEQUENCE {
     *   hashAlgorithm AlgorithmIdentifier,
     *   issuerNameHash OCTET STRING,
     *   issuerKeyHash OCTET STRING,
     *   serialNumber CertificateSerialNumber (INTEGER)
     * }
     */
    public function buildOcspRequest(string $derCert, string $derIssuerCert): string
    {
        $issuerNameHash = CertificateUtils::getIssuerNameHash($derCert, $derIssuerCert);
        $issuerKeyHash = CertificateUtils::getIssuerKeyHash($derIssuerCert);
        $serialNumber = CertificateUtils::getSerialNumberDer($derCert);

        // AlgorithmIdentifier for SHA-256
        $algId = self::derSequence(
            self::derOid(self::OID_SHA256) . self::derNull()
        );

        // CertID
        $certId = self::derSequence(
            $algId
            . self::derOctetString($issuerNameHash)
            . self::derOctetString($issuerKeyHash)
            . self::derInteger($serialNumber)
        );

        // Request
        $request = self::derSequence($certId);

        // requestList (SEQUENCE OF Request)
        $requestList = self::derSequence($request);

        // TBSRequest (version omitted = v1 default)
        $tbsRequest = self::derSequence($requestList);

        // OCSPRequest
        return self::derSequence($tbsRequest);
    }

    /**
     * Parse an OCSPResponse and validate the response status.
     *
     * OCSPResponse ::= SEQUENCE {
     *   responseStatus OCSPResponseStatus (ENUMERATED),
     *   responseBytes  [0] EXPLICIT ResponseBytes OPTIONAL
     * }
     *
     * OCSPResponseStatus ::= ENUMERATED {
     *   successful(0), malformedRequest(1), internalError(2),
     *   tryLater(3), sigRequired(5), unauthorized(6)
     * }
     *
     * @throws \RuntimeException if status is not successful(0)
     */
    public function parseOcspResponse(string $derResponse): void
    {
        $len = strlen($derResponse);
        if ($len < 2) {
            throw new \RuntimeException('OCSP response too short');
        }

        $pos = 0;

        // Outer SEQUENCE
        if (ord($derResponse[$pos]) !== 0x30) {
            throw new \RuntimeException(sprintf(
                'OCSP response: expected SEQUENCE (0x30), got 0x%02X',
                ord($derResponse[$pos]),
            ));
        }
        $pos++;
        self::readDerLength($derResponse, $pos, $len);

        // responseStatus ENUMERATED
        if ($pos >= $len || ord($derResponse[$pos]) !== 0x0A) {
            throw new \RuntimeException('OCSP response: expected ENUMERATED for responseStatus');
        }
        $pos++;
        $statusLen = self::readDerLength($derResponse, $pos, $len);

        $statusValue = 0;
        for ($i = 0; $i < $statusLen; $i++) {
            $statusValue = ($statusValue << 8) | ord($derResponse[$pos + $i]);
        }

        if ($statusValue !== 0) {
            $statusNames = [
                1 => 'malformedRequest',
                2 => 'internalError',
                3 => 'tryLater',
                5 => 'sigRequired',
                6 => 'unauthorized',
            ];
            $name = $statusNames[$statusValue] ?? "unknown($statusValue)";
            throw new \RuntimeException("OCSP responder returned status: $name");
        }
    }

    /**
     * Send an OCSP request via HTTP POST.
     */
    private function sendRequest(string $url, string $requestBody): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL for OCSP request');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/ocsp-request',
                'Accept: application/ocsp-response',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("OCSP request failed: $error");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("OCSP responder returned HTTP $httpCode");
        }

        return (string) $response;
    }

    // ------------------------------------------------------------------
    // ASN.1 DER encoding helpers (same pattern as TsaClient)
    // ------------------------------------------------------------------

    private static function derTlv(int $tag, string $value): string
    {
        return chr($tag) . self::derLength(strlen($value)) . $value;
    }

    private static function derLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        if ($len < 0x100) {
            return "\x81" . chr($len);
        }
        if ($len < 0x10000) {
            return "\x82" . pack('n', $len);
        }
        return "\x83" . chr(($len >> 16) & 0xFF) . pack('n', $len & 0xFFFF);
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

    private static function derSequence(string $content): string
    {
        return self::derTlv(0x30, $content);
    }

    private static function derOid(string $oidBytes): string
    {
        return self::derTlv(0x06, $oidBytes);
    }

    private static function derNull(): string
    {
        return "\x05\x00";
    }

    private static function derOctetString(string $data): string
    {
        return self::derTlv(0x04, $data);
    }

    /**
     * Encode raw bytes as a DER INTEGER.
     *
     * @param string $bytes Raw big-endian integer bytes (already properly encoded)
     */
    private static function derInteger(string $bytes): string
    {
        return self::derTlv(0x02, $bytes);
    }
}
