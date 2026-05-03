<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive\Signature;

use Phpdftk\Pdf\Core\Interactive\Signature\CertificateUtils;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use PHPUnit\Framework\TestCase;

class CertificateUtilsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required');
        }
    }

    public function testPemToDerRoundTrip(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('cert-utils-test');
        $pem = $creds['cert'];

        $der = CertificateUtils::pemToDer($pem);
        self::assertNotEmpty($der);
        // DER certificate starts with SEQUENCE tag
        self::assertSame("\x30", $der[0]);

        $pemBack = CertificateUtils::derToPem($der);
        self::assertStringContainsString('-----BEGIN CERTIFICATE-----', $pemBack);
        self::assertStringContainsString('-----END CERTIFICATE-----', $pemBack);

        // Round-trip: DER should be identical
        $derBack = CertificateUtils::pemToDer($pemBack);
        self::assertSame($der, $derBack);
    }

    public function testPemToDerWithInvalidPemThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        CertificateUtils::pemToDer('not a pem string');
    }

    public function testExtractCertsFromPkcs7Der(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('extract-test');
        $signer = new Pkcs7Signer($creds['cert'], $creds['key']);
        $der = $signer->sign('test data for cert extraction');

        $certs = CertificateUtils::extractCertsFromPkcs7Der($der);

        self::assertNotEmpty($certs, 'Should extract at least one certificate');
        // Each cert should be a valid DER SEQUENCE
        foreach ($certs as $cert) {
            self::assertSame("\x30", $cert[0], 'Each cert should start with SEQUENCE tag');
        }
    }

    public function testExtractCertsFromInvalidDerThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        CertificateUtils::extractCertsFromPkcs7Der('garbage data not valid DER');
    }

    public function testExtractCertsFromTooShortDataThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        CertificateUtils::extractCertsFromPkcs7Der("\x30");
    }

    public function testGetOcspResponderUrlReturnsNullForSelfSigned(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('no-aia-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $url = CertificateUtils::getOcspResponderUrl($der);
        self::assertNull($url, 'Self-signed cert should have no OCSP URL');
    }

    public function testGetOcspResponderUrlAcceptsPem(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('pem-test');

        $url = CertificateUtils::getOcspResponderUrl($creds['cert']);
        self::assertNull($url, 'Self-signed cert should have no OCSP URL');
    }

    public function testGetCrlDistributionPointsReturnsEmptyForSelfSigned(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('no-cdp-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $urls = CertificateUtils::getCrlDistributionPointUrls($der);
        self::assertSame([], $urls, 'Self-signed cert should have no CDP URLs');
    }

    public function testBuildChainSingleCert(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('chain-single');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $chain = CertificateUtils::buildChain([$der]);
        self::assertCount(1, $chain);
        self::assertSame($der, $chain[0]);
    }

    public function testBuildChainOrdersLeafToRoot(): void
    {
        // Create a CA + leaf cert to test chain ordering
        $caKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $caCsr = openssl_csr_new(
            ['commonName' => 'Test CA', 'organizationName' => 'phpdftk'],
            $caKey,
            ['digest_alg' => 'sha256'],
        );
        $caCert = openssl_csr_sign($caCsr, null, $caKey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($caCert, $caPem);

        // Leaf cert signed by CA
        $leafKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $leafCsr = openssl_csr_new(
            ['commonName' => 'Leaf Signer', 'organizationName' => 'phpdftk'],
            $leafKey,
            ['digest_alg' => 'sha256'],
        );
        $leafCert = openssl_csr_sign($leafCsr, $caPem, $caKey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($leafCert, $leafPem);

        $caDer = CertificateUtils::pemToDer($caPem);
        $leafDer = CertificateUtils::pemToDer($leafPem);

        // Pass in reverse order (root first)
        $chain = CertificateUtils::buildChain([$caDer, $leafDer]);

        self::assertCount(2, $chain);
        // Leaf should be first
        self::assertSame($leafDer, $chain[0], 'Leaf cert should be first in chain');
        // CA should be second
        self::assertSame($caDer, $chain[1], 'CA cert should be second in chain');
    }

    public function testBuildChainEmptyArray(): void
    {
        $chain = CertificateUtils::buildChain([]);
        self::assertSame([], $chain);
    }

    public function testGetSerialNumberDer(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('serial-test');
        $serial = CertificateUtils::getSerialNumberDer($creds['cert']);

        self::assertNotEmpty($serial, 'Serial number should not be empty');
    }

    public function testGetIssuerNameHash(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('issuer-hash-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        // Self-signed: issuer = subject, so use same cert as "issuer"
        $hash = CertificateUtils::getIssuerNameHash($der, $der);
        self::assertSame(32, strlen($hash), 'SHA-256 hash should be 32 bytes');
    }

    public function testGetIssuerKeyHash(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('key-hash-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $hash = CertificateUtils::getIssuerKeyHash($der);
        self::assertSame(32, strlen($hash), 'SHA-256 hash should be 32 bytes');
    }

    public function testGetOcspResponderUrlWithAia(): void
    {
        // Create a cert with AIA extension using openssl config
        $configFile = tempnam(sys_get_temp_dir(), 'ossl_cnf_');
        file_put_contents($configFile, implode("\n", [
            '[req]',
            'distinguished_name = req_dn',
            'x509_extensions = v3_ca',
            '[req_dn]',
            'CN = Test OCSP',
            '[v3_ca]',
            'authorityInfoAccess = OCSP;URI:http://ocsp.test.example.com/check',
        ]));

        try {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new(['commonName' => 'Test OCSP'], $key, [
                'config' => $configFile,
                'digest_alg' => 'sha256',
            ]);
            $cert = openssl_csr_sign($csr, null, $key, 365, [
                'config' => $configFile,
                'x509_extensions' => 'v3_ca',
                'digest_alg' => 'sha256',
            ]);
            openssl_x509_export($cert, $pem);

            $url = CertificateUtils::getOcspResponderUrl($pem);
            self::assertSame('http://ocsp.test.example.com/check', $url);
        } finally {
            @unlink($configFile);
        }
    }

    public function testGetCrlDistributionPointsWithCdp(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'ossl_cnf_');
        file_put_contents($configFile, implode("\n", [
            '[req]',
            'distinguished_name = req_dn',
            'x509_extensions = v3_ca',
            '[req_dn]',
            'CN = Test CDP',
            '[v3_ca]',
            'crlDistributionPoints = URI:http://crl.test.example.com/ca.crl',
        ]));

        try {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new(['commonName' => 'Test CDP'], $key, [
                'config' => $configFile,
                'digest_alg' => 'sha256',
            ]);
            $cert = openssl_csr_sign($csr, null, $key, 365, [
                'config' => $configFile,
                'x509_extensions' => 'v3_ca',
                'digest_alg' => 'sha256',
            ]);
            openssl_x509_export($cert, $pem);

            $urls = CertificateUtils::getCrlDistributionPointUrls($pem);
            self::assertContains('http://crl.test.example.com/ca.crl', $urls);
        } finally {
            @unlink($configFile);
        }
    }

    public function testExtractedCertsAreValidX509(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('valid-x509');
        $signer = new Pkcs7Signer($creds['cert'], $creds['key']);
        $pkcs7 = $signer->sign('data to sign');

        $certs = CertificateUtils::extractCertsFromPkcs7Der($pkcs7);

        foreach ($certs as $certDer) {
            $pem = CertificateUtils::derToPem($certDer);
            $parsed = openssl_x509_parse($pem);
            self::assertIsArray($parsed, 'Extracted cert should be parseable');
            self::assertArrayHasKey('subject', $parsed);
        }
    }
}
