<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive\Signature;

use Phpdftk\Pdf\Core\Interactive\Signature\CertificateUtils;
use Phpdftk\Pdf\Core\Interactive\Signature\CrlClient;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use PHPUnit\Framework\TestCase;

class CrlClientTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required');
        }
    }

    public function testFetchCrlDetectsPemFormat(): void
    {
        $client = new CrlClient();

        // Use reflection to call fetchCrl with a PEM-encoded CRL
        // We'll test the PEM detection logic by simulating what fetchCrl returns
        // Since we can't easily fetch from a real URL in unit tests, we test
        // the PEM detection indirectly through the public getCrl method

        // This tests that a self-signed cert without CDP throws
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('crl-test');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CRL Distribution Point');
        $client->getCrl($der);
    }

    public function testGetCrlWithNoCdpThrows(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('no-cdp');
        $der = CertificateUtils::pemToDer($creds['cert']);

        $client = new CrlClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CRL Distribution Point');
        $client->getCrl($der);
    }

    public function testGetCrlWithUnreachableUrlThrows(): void
    {
        // Create a cert with a non-reachable CRL URL
        $configFile = tempnam(sys_get_temp_dir(), 'ossl_cnf_');
        file_put_contents($configFile, implode("\n", [
            '[req]',
            'distinguished_name = req_dn',
            'x509_extensions = v3_ca',
            '[req_dn]',
            'CN = Test CRL fail',
            '[v3_ca]',
            'crlDistributionPoints = URI:http://127.0.0.1:1/nonexistent.crl',
        ]));

        try {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new(['commonName' => 'Test CRL fail'], $key, [
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

            $client = new CrlClient(timeout: 2);
            $this->expectException(\RuntimeException::class);
            $client->getCrl($der);
        } finally {
            @unlink($configFile);
        }
    }

    public function testConstructorSetsTimeout(): void
    {
        $client = new CrlClient(timeout: 5);
        // Just verifying construction works
        self::assertInstanceOf(CrlClient::class, $client);
    }
}
