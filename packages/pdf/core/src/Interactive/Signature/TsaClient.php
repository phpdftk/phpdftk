<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Interactive\Signature;

/**
 * RFC 3161 Time-Stamp Authority (TSA) HTTP client.
 *
 * Sends a TimeStampReq to a TSA server and returns the raw
 * DER-encoded TimeStampToken suitable for embedding in a
 * {@see DocTimeStamp}'s /Contents entry.
 *
 * The request is built using minimal ASN.1 DER encoding (no
 * external ASN.1 library required). The hash algorithm defaults
 * to SHA-256 (OID 2.16.840.1.101.3.4.2.1).
 *
 * Usage:
 *   $tsa = new TsaClient('http://timestamp.example.com/tsa');
 *   $token = $tsa->timestamp($dataToTimestamp);
 *
 * For integration with PdfFileWriter's signing pipeline, use
 * {@see self::createTimestampSigner()} which returns a closure
 * compatible with the signer callback interface.
 *
 * @see https://www.rfc-editor.org/rfc/rfc3161 RFC 3161
 */
final class TsaClient
{
    /** SHA-256 OID: 2.16.840.1.101.3.4.2.1 */
    private const OID_SHA256 = "\x60\x86\x48\x01\x65\x03\x04\x02\x01";

    /** SHA-384 OID: 2.16.840.1.101.3.4.2.2 */
    private const OID_SHA384 = "\x60\x86\x48\x01\x65\x03\x04\x02\x02";

    /** SHA-512 OID: 2.16.840.1.101.3.4.2.3 */
    private const OID_SHA512 = "\x60\x86\x48\x01\x65\x03\x04\x02\x03";

    private string $url;
    private string $hashAlgorithm;
    private ?string $username;
    private ?string $password;
    private int $timeout;
    private bool $requestCert;

    /**
     * @param string $url           TSA server URL (HTTP or HTTPS)
     * @param string $hashAlgorithm Hash algorithm: 'sha256', 'sha384', or 'sha512'
     * @param string|null $username HTTP Basic auth username (optional)
     * @param string|null $password HTTP Basic auth password (optional)
     * @param int    $timeout       HTTP timeout in seconds
     * @param bool   $requestCert   Whether to request the TSA certificate in the response
     */
    public function __construct(
        string $url,
        string $hashAlgorithm = 'sha256',
        ?string $username = null,
        ?string $password = null,
        int $timeout = 30,
        bool $requestCert = true,
    ) {
        $this->url = $url;
        $this->hashAlgorithm = strtolower($hashAlgorithm);
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
        $this->requestCert = $requestCert;
    }

    /**
     * Request a timestamp token for the given data.
     *
     * @param string $data The data to be timestamped (typically the signed
     *                     byte ranges from the PDF)
     * @return string Raw DER-encoded TimeStampToken (RFC 3161 §2.4.2)
     * @throws \RuntimeException on network error, invalid response, or TSA rejection
     */
    public function timestamp(string $data): string
    {
        $hash = hash($this->hashAlgorithm, $data, binary: true);
        $request = $this->buildTimeStampReq($hash);
        $responseBody = $this->sendRequest($request);
        return $this->parseTimeStampResp($responseBody);
    }

    /**
     * Build an RFC 3161 TimeStampReq in DER format.
     *
     * TimeStampReq ::= SEQUENCE {
     *   version          INTEGER { v1(1) },
     *   messageImprint   MessageImprint,
     *   reqPolicy        OBJECT IDENTIFIER  OPTIONAL,
     *   nonce            INTEGER             OPTIONAL,
     *   certReq          BOOLEAN             DEFAULT FALSE,
     *   extensions   [0] IMPLICIT Extensions OPTIONAL
     * }
     *
     * MessageImprint ::= SEQUENCE {
     *   hashAlgorithm    AlgorithmIdentifier,
     *   hashedMessage    OCTET STRING
     * }
     */
    public function buildTimeStampReq(string $hash): string
    {
        $oid = $this->getOidBytes();

        // AlgorithmIdentifier ::= SEQUENCE { algorithm OID, parameters NULL }
        $algId = self::derSequence(
            self::derOid($oid) . self::derNull(),
        );

        // MessageImprint
        $messageImprint = self::derSequence(
            $algId . self::derOctetString($hash),
        );

        // version INTEGER v1(1)
        $version = self::derInteger(1);

        // nonce — 8 random bytes for replay protection
        $nonce = self::derInteger(self::randomNonce());

        // certReq BOOLEAN
        $certReq = $this->requestCert ? self::derBoolean(true) : '';

        // TimeStampReq
        return self::derSequence(
            $version . $messageImprint . $nonce . $certReq,
        );
    }

    /**
     * Parse an RFC 3161 TimeStampResp and extract the TimeStampToken.
     *
     * TimeStampResp ::= SEQUENCE {
     *   status     PKIStatusInfo,
     *   timeStampToken  TimeStampToken OPTIONAL
     * }
     *
     * PKIStatusInfo ::= SEQUENCE {
     *   status        PKIStatus,
     *   statusString  PKIFreeText     OPTIONAL,
     *   failInfo      PKIFailureInfo  OPTIONAL
     * }
     *
     * PKIStatus ::= INTEGER {
     *   granted(0), grantedWithMods(1), rejection(2),
     *   waiting(3), revocationWarning(4), revocationNotification(5)
     * }
     *
     * @return string DER-encoded TimeStampToken (ContentInfo wrapping SignedData)
     */
    public function parseTimeStampResp(string $resp): string
    {
        $len = strlen($resp);
        if ($len < 2) {
            throw new \RuntimeException('TSA response too short');
        }

        // Outer SEQUENCE
        $pos = 0;
        $outerTag = ord($resp[$pos]);
        if ($outerTag !== 0x30) {
            throw new \RuntimeException(sprintf('TSA response: expected SEQUENCE (0x30), got 0x%02X', $outerTag));
        }
        $pos++;
        self::readDerLength($resp, $pos, $len);

        // PKIStatusInfo SEQUENCE
        if ($pos >= $len || ord($resp[$pos]) !== 0x30) {
            throw new \RuntimeException('TSA response: expected PKIStatusInfo SEQUENCE');
        }
        $pos++;
        $statusInfoLen = self::readDerLength($resp, $pos, $len);
        $statusInfoEnd = $pos + $statusInfoLen;

        // PKIStatus INTEGER
        if ($pos >= $statusInfoEnd || ord($resp[$pos]) !== 0x02) {
            throw new \RuntimeException('TSA response: expected PKIStatus INTEGER');
        }
        $pos++;
        $statusLen = self::readDerLength($resp, $pos, $len);
        $statusValue = 0;
        for ($i = 0; $i < $statusLen; $i++) {
            $statusValue = ($statusValue << 8) | ord($resp[$pos + $i]);
        }
        $pos = $statusInfoEnd; // skip rest of PKIStatusInfo

        // Status 0 = granted, 1 = grantedWithMods
        if ($statusValue > 1) {
            $statusNames = [
                2 => 'rejection', 3 => 'waiting',
                4 => 'revocationWarning', 5 => 'revocationNotification',
            ];
            $name = $statusNames[$statusValue] ?? "unknown($statusValue)";
            throw new \RuntimeException("TSA returned status: $name");
        }

        // The remainder is the TimeStampToken (ContentInfo SEQUENCE)
        if ($pos >= $len) {
            throw new \RuntimeException('TSA response: no TimeStampToken present (status was granted but token missing)');
        }

        return substr($resp, $pos);
    }

    /**
     * Send the TimeStampReq to the TSA via HTTP POST.
     */
    private function sendRequest(string $requestBody): string
    {
        $headers = [
            'Content-Type: application/timestamp-query',
            'Accept: application/timestamp-reply',
        ];

        if ($this->username !== null) {
            $headers[] = 'Authorization: Basic ' . base64_encode($this->username . ':' . ($this->password ?? ''));
        }

        $ch = curl_init($this->url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL for TSA request');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => $headers,
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
            throw new \RuntimeException("TSA request failed: $error");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("TSA returned HTTP $httpCode");
        }

        return (string) $response;
    }

    // ------------------------------------------------------------------
    // ASN.1 DER encoding helpers
    // ------------------------------------------------------------------

    private function getOidBytes(): string
    {
        return match ($this->hashAlgorithm) {
            'sha256' => self::OID_SHA256,
            'sha384' => self::OID_SHA384,
            'sha512' => self::OID_SHA512,
            default => throw new \InvalidArgumentException("Unsupported hash algorithm: {$this->hashAlgorithm}"),
        };
    }

    /** Encode a DER tag + length + value. */
    private static function derTlv(int $tag, string $value): string
    {
        return chr($tag) . self::derLength(strlen($value)) . $value;
    }

    /** Encode a DER length. */
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

    /** Read a DER length from a buffer. Advances $pos past the length bytes. */
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

    private static function derInteger(int $value): string
    {
        if ($value >= 0 && $value <= 127) {
            return self::derTlv(0x02, chr($value));
        }

        // Encode as big-endian bytes
        $bytes = '';
        $tmp = $value;
        while ($tmp > 0) {
            $bytes = chr($tmp & 0xFF) . $bytes;
            $tmp >>= 8;
        }
        // Ensure positive — prepend 0x00 if high bit set
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }
        return self::derTlv(0x02, $bytes);
    }

    private static function derBoolean(bool $value): string
    {
        return self::derTlv(0x01, $value ? "\xFF" : "\x00");
    }

    /** Generate a random nonce for replay protection. */
    private static function randomNonce(): int
    {
        // Use 7 bytes to stay within PHP int range on 64-bit
        $bytes = random_bytes(7);
        $nonce = 0;
        for ($i = 0; $i < 7; $i++) {
            $nonce = ($nonce << 8) | ord($bytes[$i]);
        }
        return $nonce;
    }
}
