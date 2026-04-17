<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Interactive;

use ApprLabs\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use PHPUnit\Framework\TestCase;

class Pkcs7SignerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required');
        }
    }

    public function testCreatesSelfSignedCredentials(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('unit-test');
        self::assertStringContainsString('BEGIN CERTIFICATE', $creds['cert']);
        self::assertStringContainsString('PRIVATE KEY', $creds['key']);
    }

    public function testSignReturnsDerBytes(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials();
        $signer = new Pkcs7Signer($creds['cert'], $creds['key']);

        $data = 'the quick brown fox jumps over the lazy dog';
        $der = $signer->sign($data);

        self::assertNotEmpty($der);
        self::assertSame("\x30", $der[0], 'DER must start with an ASN.1 SEQUENCE tag');
    }

    public function testSignedBytesVerifyWithOpensslCli(): void
    {
        $openssl = $this->findOpensslBinary();
        if ($openssl === null) {
            self::markTestSkipped('openssl CLI not found on PATH');
        }

        $creds = Pkcs7Signer::createSelfSignedTestCredentials();
        $signer = new Pkcs7Signer($creds['cert'], $creds['key']);

        $data = 'arbitrary content to sign and verify';
        $der = $signer->sign($data);

        $dataFile = tempnam(sys_get_temp_dir(), 'sig_data_');
        $sigFile = tempnam(sys_get_temp_dir(), 'sig_blob_');
        $certFile = tempnam(sys_get_temp_dir(), 'sig_cert_') . '.pem';
        file_put_contents($dataFile, $data);
        file_put_contents($sigFile, $der);
        file_put_contents($certFile, $creds['cert']);

        try {
            $cmd = sprintf(
                '%s cms -verify -inform DER -in %s -content %s -certfile %s -noverify -binary -out /dev/null 2>&1',
                escapeshellarg($openssl),
                escapeshellarg($sigFile),
                escapeshellarg($dataFile),
                escapeshellarg($certFile)
            );
            $output = [];
            $ret = 0;
            exec($cmd, $output, $ret);
            self::assertSame(0, $ret, 'openssl cms -verify failed: ' . implode("\n", $output));
        } finally {
            @unlink($dataFile);
            @unlink($sigFile);
            @unlink($certFile);
        }
    }

    public function testExtractDerFromSmimeMatchesAttachment(): void
    {
        // Round-trip: produce an SMIME envelope via openssl_pkcs7_sign, extract
        // the attachment, and verify the extracted DER matches the base64 blob.
        $creds = Pkcs7Signer::createSelfSignedTestCredentials();

        $in = tempnam(sys_get_temp_dir(), 'in_');
        $out = tempnam(sys_get_temp_dir(), 'out_');
        file_put_contents($in, 'hello');
        try {
            $ok = openssl_pkcs7_sign(
                $in,
                $out,
                $creds['cert'],
                $creds['key'],
                [],
                PKCS7_BINARY | PKCS7_DETACHED
            );
            self::assertTrue($ok);

            $smime = (string) file_get_contents($out);
            $der = Pkcs7Signer::extractDerFromSmime($smime);

            self::assertNotEmpty($der);
            self::assertSame("\x30", $der[0]);

            preg_match(
                '/Content-Type:\s*application\/(?:x-)?pkcs7-signature[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n(.+?)\r?\n--/s',
                $smime,
                $m
            );
            $expected = (string) base64_decode((string) preg_replace('/\s+/', '', $m[1]), true);
            self::assertSame($expected, $der);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    public function testSignatureFitsInDefaultPlaceholder(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials();
        $signer = new Pkcs7Signer($creds['cert'], $creds['key']);
        $der = $signer->sign('x');
        self::assertLessThan(8192, strlen($der));
    }

    private function findOpensslBinary(): ?string
    {
        foreach (['/usr/bin/openssl', '/usr/local/bin/openssl', '/opt/homebrew/bin/openssl'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        $which = trim((string) @shell_exec('command -v openssl 2>/dev/null'));
        return $which !== '' ? $which : null;
    }
}
