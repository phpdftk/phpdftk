<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive;

use Phpdftk\Pdf\Core\Interactive\Signature\TsaClient;
use PHPUnit\Framework\TestCase;

class TsaClientTest extends TestCase
{
    public function testBuildTimeStampReqIsValidDer(): void
    {
        $client = new TsaClient('http://example.com/tsa');
        $hash = hash('sha256', 'test data', binary: true);
        $req = $client->buildTimeStampReq($hash);

        // Must start with SEQUENCE tag
        $this->assertSame(0x30, ord($req[0]));

        // Verify the total structure is parseable
        $pos = 1;
        $len = strlen($req);
        $seqLen = $this->readDerLength($req, $pos, $len);
        $this->assertSame($len - $pos, $seqLen, 'Outer SEQUENCE length should match remaining bytes');
    }

    public function testBuildTimeStampReqContainsVersion(): void
    {
        $client = new TsaClient('http://example.com/tsa');
        $hash = hash('sha256', 'test', binary: true);
        $req = $client->buildTimeStampReq($hash);

        // After outer SEQUENCE, first element should be INTEGER with value 1 (version)
        $pos = 1;
        $len = strlen($req);
        $this->readDerLength($req, $pos, $len); // skip outer length

        // INTEGER tag
        $this->assertSame(0x02, ord($req[$pos]), 'First element should be INTEGER (version)');
        $pos++;
        $intLen = $this->readDerLength($req, $pos, $len);
        $this->assertSame(1, $intLen);
        $this->assertSame(1, ord($req[$pos]), 'Version should be 1');
    }

    public function testBuildTimeStampReqContainsMessageImprint(): void
    {
        $client = new TsaClient('http://example.com/tsa');
        $hash = hash('sha256', 'test', binary: true);
        $req = $client->buildTimeStampReq($hash);

        // The request should contain the hash bytes
        $this->assertStringContainsString($hash, $req);
    }

    public function testBuildTimeStampReqSha384(): void
    {
        $client = new TsaClient('http://example.com/tsa', hashAlgorithm: 'sha384');
        $hash = hash('sha384', 'test', binary: true);
        $req = $client->buildTimeStampReq($hash);

        // SHA-384 OID bytes should be present
        $this->assertStringContainsString("\x60\x86\x48\x01\x65\x03\x04\x02\x02", $req);
    }

    public function testBuildTimeStampReqSha512(): void
    {
        $client = new TsaClient('http://example.com/tsa', hashAlgorithm: 'sha512');
        $hash = hash('sha512', 'test', binary: true);
        $req = $client->buildTimeStampReq($hash);

        // SHA-512 OID bytes should be present
        $this->assertStringContainsString("\x60\x86\x48\x01\x65\x03\x04\x02\x03", $req);
    }

    public function testBuildTimeStampReqUnsupportedAlgorithmThrows(): void
    {
        $client = new TsaClient('http://example.com/tsa', hashAlgorithm: 'md5');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported hash algorithm');
        $client->buildTimeStampReq(hash('md5', 'test', binary: true));
    }

    public function testBuildTimeStampReqWithCertRequest(): void
    {
        $client = new TsaClient('http://example.com/tsa', requestCert: true);
        $hash = hash('sha256', 'test', binary: true);
        $req = $client->buildTimeStampReq($hash);

        // Should contain BOOLEAN TRUE (0x01 0x01 0xFF)
        $this->assertStringContainsString("\x01\x01\xFF", $req);
    }

    public function testBuildTimeStampReqWithoutCertRequest(): void
    {
        $client = new TsaClient('http://example.com/tsa', requestCert: false);
        $hash = hash('sha256', 'test', binary: true);
        $req = $client->buildTimeStampReq($hash);

        // Should NOT contain BOOLEAN TRUE
        $this->assertStringNotContainsString("\x01\x01\xFF", $req);
    }

    public function testParseTimeStampRespGranted(): void
    {
        $client = new TsaClient('http://example.com/tsa');

        // Build a minimal valid TimeStampResp:
        // SEQUENCE { PKIStatusInfo { status=0 }, TimeStampToken }
        $fakeToken = $this->buildFakeTimeStampToken();
        $statusInfo = $this->derSequence($this->derInteger(0)); // granted
        $resp = $this->derSequence($statusInfo . $fakeToken);

        $token = $client->parseTimeStampResp($resp);
        $this->assertSame($fakeToken, $token);
    }

    public function testParseTimeStampRespGrantedWithMods(): void
    {
        $client = new TsaClient('http://example.com/tsa');

        $fakeToken = $this->buildFakeTimeStampToken();
        $statusInfo = $this->derSequence($this->derInteger(1)); // grantedWithMods
        $resp = $this->derSequence($statusInfo . $fakeToken);

        $token = $client->parseTimeStampResp($resp);
        $this->assertSame($fakeToken, $token);
    }

    public function testParseTimeStampRespRejectionThrows(): void
    {
        $client = new TsaClient('http://example.com/tsa');

        $statusInfo = $this->derSequence($this->derInteger(2)); // rejection
        $resp = $this->derSequence($statusInfo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rejection');
        $client->parseTimeStampResp($resp);
    }

    public function testParseTimeStampRespWaitingThrows(): void
    {
        $client = new TsaClient('http://example.com/tsa');

        $statusInfo = $this->derSequence($this->derInteger(3)); // waiting
        $resp = $this->derSequence($statusInfo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('waiting');
        $client->parseTimeStampResp($resp);
    }

    public function testParseTimeStampRespTooShortThrows(): void
    {
        $client = new TsaClient('http://example.com/tsa');

        $this->expectException(\RuntimeException::class);
        $client->parseTimeStampResp("\x30");
    }

    public function testParseTimeStampRespNoTokenThrows(): void
    {
        $client = new TsaClient('http://example.com/tsa');

        // Status granted but no token following
        $statusInfo = $this->derSequence($this->derInteger(0));
        $resp = $this->derSequence($statusInfo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no TimeStampToken');
        $client->parseTimeStampResp($resp);
    }

    public function testTimestampWithInvalidUrlThrows(): void
    {
        $client = new TsaClient('http://192.0.2.1:1/nonexistent', timeout: 2);

        $this->expectException(\RuntimeException::class);
        $client->timestamp('test data');
    }

    // ------------------------------------------------------------------
    // DER helpers for building test data
    // ------------------------------------------------------------------

    private function readDerLength(string $data, int &$pos, int $dataLen): int
    {
        $byte = ord($data[$pos]);
        $pos++;
        if ($byte < 0x80) {
            return $byte;
        }
        $numBytes = $byte & 0x7F;
        $len = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $len = ($len << 8) | ord($data[$pos]);
            $pos++;
        }
        return $len;
    }

    private function derTlv(int $tag, string $value): string
    {
        $len = strlen($value);
        if ($len < 0x80) {
            return chr($tag) . chr($len) . $value;
        }
        if ($len < 0x100) {
            return chr($tag) . "\x81" . chr($len) . $value;
        }
        return chr($tag) . "\x82" . pack('n', $len) . $value;
    }

    private function derSequence(string $content): string
    {
        return $this->derTlv(0x30, $content);
    }

    private function derInteger(int $value): string
    {
        if ($value >= 0 && $value <= 127) {
            return $this->derTlv(0x02, chr($value));
        }
        $bytes = '';
        $tmp = $value;
        while ($tmp > 0) {
            $bytes = chr($tmp & 0xFF) . $bytes;
            $tmp >>= 8;
        }
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }
        return $this->derTlv(0x02, $bytes);
    }

    /**
     * Build a fake TimeStampToken (ContentInfo SEQUENCE) for testing.
     * Just enough structure to be extractable from a TimeStampResp.
     */
    private function buildFakeTimeStampToken(): string
    {
        // ContentInfo ::= SEQUENCE { contentType OID, content [0] EXPLICIT ANY }
        // Use id-signedData OID: 1.2.840.113549.1.7.2
        $oid = $this->derTlv(0x06, "\x2A\x86\x48\x86\xF7\x0D\x01\x07\x02");
        $content = $this->derTlv(0xA0, $this->derSequence("\x02\x01\x03")); // version 3
        return $this->derSequence($oid . $content);
    }
}
