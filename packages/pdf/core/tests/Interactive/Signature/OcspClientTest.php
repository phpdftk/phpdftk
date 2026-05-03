<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive\Signature;

use Phpdftk\Pdf\Core\Interactive\Signature\CertificateUtils;
use Phpdftk\Pdf\Core\Interactive\Signature\OcspClient;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use PHPUnit\Framework\TestCase;

class OcspClientTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required');
        }
    }

    public function testBuildOcspRequestIsValidDer(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('ocsp-req-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $client = new OcspClient();
        $request = $client->buildOcspRequest($der, $der); // self-signed: issuer = self

        self::assertNotEmpty($request);
        // OCSPRequest starts with SEQUENCE tag
        self::assertSame("\x30", $request[0], 'OCSP request should start with SEQUENCE');
    }

    public function testBuildOcspRequestContainsSha256Oid(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('ocsp-oid-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $client = new OcspClient();
        $request = $client->buildOcspRequest($der, $der);

        // SHA-256 OID bytes: 2.16.840.1.101.3.4.2.1
        $sha256Oid = "\x60\x86\x48\x01\x65\x03\x04\x02\x01";
        self::assertStringContainsString($sha256Oid, $request, 'Request should contain SHA-256 OID');
    }

    public function testBuildOcspRequestContainsCertId(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('ocsp-certid-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $client = new OcspClient();
        $request = $client->buildOcspRequest($der, $der);

        // The request should contain the issuer name hash (32 bytes for SHA-256)
        $issuerNameHash = CertificateUtils::getIssuerNameHash($der, $der);
        self::assertStringContainsString($issuerNameHash, $request, 'Request should contain issuer name hash');

        // The request should contain the issuer key hash
        $issuerKeyHash = CertificateUtils::getIssuerKeyHash($der);
        self::assertStringContainsString($issuerKeyHash, $request, 'Request should contain issuer key hash');
    }

    public function testParseOcspResponseSuccessful(): void
    {
        // Build a minimal valid OCSPResponse with status = successful (0)
        // OCSPResponse ::= SEQUENCE { responseStatus ENUMERATED }
        $status = "\x0A\x01\x00"; // ENUMERATED, length 1, value 0 (successful)
        // responseBytes [0] EXPLICIT — include a dummy
        $responseBytes = "\xA0\x02\x30\x00"; // [0] EXPLICIT SEQUENCE {}
        $response = "\x30" . chr(strlen($status . $responseBytes)) . $status . $responseBytes;

        $client = new OcspClient();
        // Should not throw
        $client->parseOcspResponse($response);
        self::assertTrue(true, 'Successful OCSP response should not throw');
    }

    public function testParseOcspResponseMalformedThrows(): void
    {
        $client = new OcspClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OCSP response too short');
        $client->parseOcspResponse('x');
    }

    public function testParseOcspResponseNotSequenceThrows(): void
    {
        $client = new OcspClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected SEQUENCE');
        $client->parseOcspResponse("\x02\x01\x00"); // INTEGER instead of SEQUENCE
    }

    public function testParseOcspResponseUnauthorizedThrows(): void
    {
        // OCSPResponse with status = unauthorized (6)
        $status = "\x0A\x01\x06"; // ENUMERATED value 6
        $response = "\x30" . chr(strlen($status)) . $status;

        $client = new OcspClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthorized');
        $client->parseOcspResponse($response);
    }

    public function testParseOcspResponseInternalErrorThrows(): void
    {
        // OCSPResponse with status = internalError (2)
        $status = "\x0A\x01\x02";
        $response = "\x30" . chr(strlen($status)) . $status;

        $client = new OcspClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('internalError');
        $client->parseOcspResponse($response);
    }

    public function testParseOcspResponseTryLaterThrows(): void
    {
        $status = "\x0A\x01\x03";
        $response = "\x30" . chr(strlen($status)) . $status;

        $client = new OcspClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tryLater');
        $client->parseOcspResponse($response);
    }

    public function testGetOcspResponseWithInvalidUrlThrows(): void
    {
        // Create a cert with a non-reachable OCSP URL
        $configFile = tempnam(sys_get_temp_dir(), 'ossl_cnf_');
        file_put_contents($configFile, implode("\n", [
            '[req]',
            'distinguished_name = req_dn',
            'x509_extensions = v3_ca',
            '[req_dn]',
            'CN = Test OCSP fail',
            '[v3_ca]',
            'authorityInfoAccess = OCSP;URI:http://127.0.0.1:1/ocsp-nonexistent',
        ]));

        try {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new(['commonName' => 'Test OCSP fail'], $key, [
                'config' => $configFile,
                'digest_alg' => 'sha256',
            ]);
            $cert = openssl_csr_sign($csr, null, $key, 365, [
                'config' => $configFile,
                'x509_extensions' => 'v3_ca',
                'digest_alg' => 'sha256',
            ]);
            openssl_x509_export($cert, $pem);
            $der = CertificateUtils::pemToDer($pem);

            $client = new OcspClient(timeout: 2);
            $this->expectException(\RuntimeException::class);
            $client->getOcspResponse($der, $der);
        } finally {
            @unlink($configFile);
        }
    }

    public function testGetOcspResponseWithNoAiaThrows(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('no-aia');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $client = new OcspClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OCSP responder URL');
        $client->getOcspResponse($der, $der);
    }
}
