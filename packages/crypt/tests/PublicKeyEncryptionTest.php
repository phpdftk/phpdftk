<?php

declare(strict_types=1);

namespace Phpdftk\Crypt\Tests;

use Phpdftk\Crypt\PublicKeyEncryption;
use PHPUnit\Framework\TestCase;

class PublicKeyEncryptionTest extends TestCase
{
    private static ?array $credentials = null;

    public static function setUpBeforeClass(): void
    {
        // Generate a self-signed cert for testing
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $key = openssl_pkey_new($config);
        $csr = openssl_csr_new(
            ['commonName' => 'phpdftk-test', 'organizationName' => 'test'],
            $key,
            $config
        );
        $cert = openssl_csr_sign($csr, null, $key, 365, $config);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem);

        self::$credentials = ['cert' => $certPem, 'key' => $keyPem];
    }

    public function testCreateEnvelopeReturnsDerBytes(): void
    {
        $seed = random_bytes(20);
        $der = PublicKeyEncryption::createEnvelope(
            $seed, -1, self::$credentials['cert']
        );

        // DER-encoded PKCS#7 starts with SEQUENCE tag (0x30)
        $this->assertNotEmpty($der);
        $this->assertSame(0x30, ord($der[0]));
    }

    public function testOpenEnvelopeRecoversSeed(): void
    {
        $seed = random_bytes(20);
        $der = PublicKeyEncryption::createEnvelope(
            $seed, -1, self::$credentials['cert']
        );

        $recovered = PublicKeyEncryption::openEnvelope(
            $der, self::$credentials['cert'], self::$credentials['key']
        );

        $this->assertNotNull($recovered);
        $this->assertSame($seed, $recovered);
    }

    public function testOpenEnvelopeReturnsNullWithWrongKey(): void
    {
        $seed = random_bytes(20);
        $der = PublicKeyEncryption::createEnvelope(
            $seed, -1, self::$credentials['cert']
        );

        // Generate a different key pair
        $config = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $otherKey = openssl_pkey_new($config);
        $otherCsr = openssl_csr_new(['commonName' => 'other'], $otherKey, $config);
        $otherCert = openssl_csr_sign($otherCsr, null, $otherKey, 365, $config);
        openssl_x509_export($otherCert, $otherCertPem);
        openssl_pkey_export($otherKey, $otherKeyPem);

        $recovered = PublicKeyEncryption::openEnvelope(
            $der, $otherCertPem, $otherKeyPem
        );

        $this->assertNull($recovered);
    }

    public function testDeriveFileKeyIsDeterministic(): void
    {
        $seed = str_repeat("\xAB", 20);
        $recipients = [str_repeat("\x01", 100), str_repeat("\x02", 100)];

        $key1 = PublicKeyEncryption::deriveFileKey($seed, $recipients, -1, 16);
        $key2 = PublicKeyEncryption::deriveFileKey($seed, $recipients, -1, 16);

        $this->assertSame(16, strlen($key1));
        $this->assertSame($key1, $key2);
    }

    public function testDeriveFileKeyDiffersWithDifferentPermissions(): void
    {
        $seed = random_bytes(20);
        $recipients = [random_bytes(100)];

        $key1 = PublicKeyEncryption::deriveFileKey($seed, $recipients, -1, 16);
        $key2 = PublicKeyEncryption::deriveFileKey($seed, $recipients, -4, 16);

        $this->assertNotSame($key1, $key2);
    }

    public function testDeriveFileKeyDiffersWithMetadataFlag(): void
    {
        $seed = random_bytes(20);
        $recipients = [random_bytes(100)];

        $key1 = PublicKeyEncryption::deriveFileKey($seed, $recipients, -1, 16, true);
        $key2 = PublicKeyEncryption::deriveFileKey($seed, $recipients, -1, 16, false);

        $this->assertNotSame($key1, $key2);
    }

    public function testCreateAndOpenEnvelopeWithCustomPermissions(): void
    {
        $seed = random_bytes(20);
        // Use a realistic signed 32-bit permissions value (print+copy)
        $permissions = -3900;

        $der = PublicKeyEncryption::createEnvelope(
            $seed, $permissions, self::$credentials['cert']
        );

        $recovered = PublicKeyEncryption::openEnvelope(
            $der, self::$credentials['cert'], self::$credentials['key']
        );

        $this->assertNotNull($recovered);
        $this->assertSame($seed, $recovered);
    }
}
