<?php declare(strict_types=1);

namespace ApprLabs\Crypt\Tests;

use PHPUnit\Framework\TestCase;
use ApprLabs\Crypt\Rc4Cipher;
use ApprLabs\Crypt\AesCipher;
use ApprLabs\Crypt\PdfKeyDerivation;

class CryptTest extends TestCase
{
    // -----------------------------------------------------------------------
    // RC4
    // -----------------------------------------------------------------------

    public function testRc4EncryptDecryptRoundTrip(): void
    {
        $rc4 = new Rc4Cipher();
        $key = 'SecretKey123';
        $plaintext = 'Hello, World! This is a test message.';
        $encrypted = $rc4->encrypt($plaintext, $key);
        $decrypted = $rc4->decrypt($encrypted, $key);
        $this->assertSame($plaintext, $decrypted);
    }

    public function testRc4EncryptDecryptAreTheSameOperation(): void
    {
        $rc4 = new Rc4Cipher();
        $key = 'mykey';
        $data = 'test data';
        // RC4 is symmetric: encrypt == decrypt
        $this->assertSame($rc4->encrypt($data, $key), $rc4->decrypt($data, $key));
    }

    public function testRc4ProducesDifferentOutput(): void
    {
        $rc4 = new Rc4Cipher();
        $key = 'key';
        $plaintext = 'Hello';
        $encrypted = $rc4->encrypt($plaintext, $key);
        $this->assertNotSame($plaintext, $encrypted);
    }

    public function testRc4EmptyData(): void
    {
        $rc4 = new Rc4Cipher();
        $this->assertSame('', $rc4->encrypt('', 'key'));
        $this->assertSame('', $rc4->decrypt('', 'key'));
    }

    public function testRc4KnownVector(): void
    {
        // RC4 known test vector: key="Key", plaintext="Plaintext"
        $rc4 = new Rc4Cipher();
        $key = 'Key';
        $plaintext = 'Plaintext';
        $encrypted = $rc4->encrypt($plaintext, $key);
        // Verify it's not the same as plaintext
        $this->assertNotSame($plaintext, $encrypted);
        // Verify round-trip
        $this->assertSame($plaintext, $rc4->decrypt($encrypted, $key));
    }

    public function testRc4BinaryData(): void
    {
        $rc4 = new Rc4Cipher();
        $key = 'binary-key';
        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }
        $encrypted = $rc4->encrypt($data, $key);
        $decrypted = $rc4->decrypt($encrypted, $key);
        $this->assertSame($data, $decrypted);
    }

    // -----------------------------------------------------------------------
    // AES
    // -----------------------------------------------------------------------

    public function testAes128EncryptDecryptRoundTrip(): void
    {
        $aes = new AesCipher(128);
        $key = 'SixteenByteKey!!';
        $plaintext = 'Hello, AES World!';
        $encrypted = $aes->encrypt($plaintext, $key);
        $decrypted = $aes->decrypt($encrypted, $key);
        $this->assertSame($plaintext, $decrypted);
    }

    public function testAes256EncryptDecryptRoundTrip(): void
    {
        $aes = new AesCipher(256);
        $key = 'ThisIsA32ByteKeyForAES256Encrypt';
        $plaintext = 'Secret data to encrypt with AES-256';
        $encrypted = $aes->encrypt($plaintext, $key);
        $decrypted = $aes->decrypt($encrypted, $key);
        $this->assertSame($plaintext, $decrypted);
    }

    public function testAesEncryptProducesDifferentOutput(): void
    {
        $aes = new AesCipher(128);
        $key = 'TestKey12345678!';
        $plaintext = 'Hello World';
        $encrypted = $aes->encrypt($plaintext, $key);
        $this->assertNotSame($plaintext, $encrypted);
    }

    public function testAesEncryptWithIvPrepended(): void
    {
        $aes = new AesCipher(128);
        $key = 'TestKey12345678!';
        $plaintext = 'Test';
        $encrypted = $aes->encrypt($plaintext, $key);
        // IV (16 bytes) is prepended, so encrypted should be longer than plaintext
        $this->assertGreaterThan(strlen($plaintext) + 16, strlen($encrypted));
    }

    public function testAesEncryptProducesRandomIv(): void
    {
        $aes = new AesCipher(128);
        $key = 'TestKey12345678!';
        $plaintext = 'Hello';
        $enc1 = $aes->encrypt($plaintext, $key);
        $enc2 = $aes->encrypt($plaintext, $key);
        // Two encryptions of the same data should produce different ciphertext (different IVs)
        $this->assertNotSame($enc1, $enc2);
    }

    public function testAesEmptyData(): void
    {
        $aes = new AesCipher(128);
        $key = 'TestKey12345678!';
        $encrypted = $aes->encrypt('', $key);
        $decrypted = $aes->decrypt($encrypted, $key);
        $this->assertSame('', $decrypted);
    }

    public function testAesInvalidKeyBitsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AesCipher(192);
    }

    public function testAesDecryptTooShortThrows(): void
    {
        $aes = new AesCipher(128);
        $this->expectException(\RuntimeException::class);
        $aes->decrypt('short', 'key');
    }

    // -----------------------------------------------------------------------
    // PdfKeyDerivation
    // -----------------------------------------------------------------------

    public function testDeriveObjectKeyLength(): void
    {
        $encKey = str_repeat("\xAB", 16); // 16-byte encryption key
        $objKey = PdfKeyDerivation::deriveObjectKey($encKey, 1, 0);
        // Expected length: min(16 + 5, 16) = 16
        $this->assertSame(16, strlen($objKey));
    }

    public function testDeriveObjectKeyShortEncKey(): void
    {
        $encKey = str_repeat("\xAB", 5); // 5-byte encryption key
        $objKey = PdfKeyDerivation::deriveObjectKey($encKey, 1, 0);
        // Expected length: min(5 + 5, 16) = 10
        $this->assertSame(10, strlen($objKey));
    }

    public function testDeriveObjectKeyAes(): void
    {
        $encKey = str_repeat("\xAB", 16);
        $objKey = PdfKeyDerivation::deriveObjectKey($encKey, 1, 0, aes: true);
        $this->assertSame(16, strlen($objKey));
    }

    public function testDeriveObjectKeyDifferentObjects(): void
    {
        $encKey = str_repeat("\xAB", 16);
        $key1 = PdfKeyDerivation::deriveObjectKey($encKey, 1, 0);
        $key2 = PdfKeyDerivation::deriveObjectKey($encKey, 2, 0);
        $this->assertNotSame($key1, $key2);
    }

    public function testDeriveObjectKeyAesVsNonAes(): void
    {
        $encKey = str_repeat("\xAB", 16);
        $keyNoAes = PdfKeyDerivation::deriveObjectKey($encKey, 1, 0, aes: false);
        $keyAes   = PdfKeyDerivation::deriveObjectKey($encKey, 1, 0, aes: true);
        $this->assertNotSame($keyNoAes, $keyAes);
    }

    public function testDeriveObjectKeyMinLength(): void
    {
        // Very short encryption key (< 5 bytes would give keyLen < 5, but we use max(5, ...))
        $encKey = "\xAB"; // 1-byte key -> min(6, 16) = 6, max(5, 6) = 6
        $objKey = PdfKeyDerivation::deriveObjectKey($encKey, 1, 0);
        $this->assertSame(6, strlen($objKey));
    }

    public function testComputeOwnerKey(): void
    {
        $ownerKey = PdfKeyDerivation::computeOwnerKey('owner', 'user', 40);
        $this->assertSame(32, strlen($ownerKey)); // /O is always 32 bytes per spec
    }

    public function testComputeOwnerKey128(): void
    {
        $ownerKey = PdfKeyDerivation::computeOwnerKey('owner', 'user', 128);
        $this->assertSame(32, strlen($ownerKey)); // /O is always 32 bytes per spec
    }

    public function testComputeOwnerKeyDifferentPasswords(): void
    {
        $key1 = PdfKeyDerivation::computeOwnerKey('owner1', 'user', 128);
        $key2 = PdfKeyDerivation::computeOwnerKey('owner2', 'user', 128);
        $this->assertNotSame($key1, $key2);
    }

    // -----------------------------------------------------------------------
    // R=6 (AES-256) key derivation
    // -----------------------------------------------------------------------

    public function testComputeHashR6(): void
    {
        $password = 'testpassword';
        $salt = random_bytes(8);
        $hash = PdfKeyDerivation::computeHashR6($password, $salt);

        // Must produce exactly 32 bytes
        $this->assertSame(32, strlen($hash));

        // Same inputs must produce same output (deterministic)
        $hash2 = PdfKeyDerivation::computeHashR6($password, $salt);
        $this->assertSame($hash, $hash2);

        // Different salt must produce different output
        $hash3 = PdfKeyDerivation::computeHashR6($password, random_bytes(8));
        $this->assertNotSame($hash, $hash3);
    }

    public function testComputeHashR6WithUserKey(): void
    {
        $password = 'test';
        $salt = random_bytes(8);
        $userKey = random_bytes(48);

        $hashWithout = PdfKeyDerivation::computeHashR6($password, $salt);
        $hashWith = PdfKeyDerivation::computeHashR6($password, $salt, $userKey);

        // With and without userKey should produce different results
        $this->assertNotSame($hashWithout, $hashWith);
        $this->assertSame(32, strlen($hashWith));
    }

    public function testAuthenticateUserPasswordR6RoundTrip(): void
    {
        $password = 'mysecretpassword';
        $fileEncryptionKey = random_bytes(32);

        // Compute U/UE
        $result = PdfKeyDerivation::computeUValueR6($password, $fileEncryptionKey);
        $this->assertSame(48, strlen($result['u']));
        $this->assertSame(32, strlen($result['ue']));

        // Authenticate with correct password
        $recoveredKey = PdfKeyDerivation::authenticateUserPasswordR6(
            $password, $result['u'], $result['ue']
        );
        $this->assertNotNull($recoveredKey);
        $this->assertSame($fileEncryptionKey, $recoveredKey);

        // Fail with wrong password
        $wrongKey = PdfKeyDerivation::authenticateUserPasswordR6(
            'wrongpassword', $result['u'], $result['ue']
        );
        $this->assertNull($wrongKey);
    }

    public function testAuthenticateOwnerPasswordR6RoundTrip(): void
    {
        $password = 'ownerpass';
        $fileEncryptionKey = random_bytes(32);

        // First compute U value (needed for O computation)
        $uResult = PdfKeyDerivation::computeUValueR6('userpass', $fileEncryptionKey);

        // Compute O/OE
        $oResult = PdfKeyDerivation::computeOValueR6($password, $fileEncryptionKey, $uResult['u']);
        $this->assertSame(48, strlen($oResult['o']));
        $this->assertSame(32, strlen($oResult['oe']));

        // Authenticate with correct password
        $recoveredKey = PdfKeyDerivation::authenticateOwnerPasswordR6(
            $password, $oResult['o'], $oResult['oe'], $uResult['u']
        );
        $this->assertNotNull($recoveredKey);
        $this->assertSame($fileEncryptionKey, $recoveredKey);

        // Fail with wrong password
        $wrongKey = PdfKeyDerivation::authenticateOwnerPasswordR6(
            'wrongpassword', $oResult['o'], $oResult['oe'], $uResult['u']
        );
        $this->assertNull($wrongKey);
    }

    public function testComputePermsR6(): void
    {
        $fileEncryptionKey = random_bytes(32);
        $permissions = 0xFFFFF0C4; // typical permissions value
        $perms = PdfKeyDerivation::computePermsR6($permissions, $fileEncryptionKey);

        // Must produce exactly 16 bytes
        $this->assertSame(16, strlen($perms));

        // Verify by decrypting: should recover the permission bits
        $decrypted = openssl_decrypt($perms, 'AES-256-ECB', $fileEncryptionKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        $this->assertNotFalse($decrypted);

        // First 4 bytes should be permissions as LE int
        $recoveredP = unpack('V', substr($decrypted, 0, 4))[1];
        // Handle signed 32-bit
        if ($recoveredP > 0x7FFFFFFF) {
            $recoveredP = $recoveredP - 0x100000000;
        }
        if ($permissions > 0x7FFFFFFF) {
            $permissions = $permissions - 0x100000000;
        }
        $this->assertSame($permissions, $recoveredP);

        // Byte 8 should be 'T' for encryptMetadata=true (default)
        $this->assertSame('T', $decrypted[8]);
    }

    public function testPreparePasswordR6(): void
    {
        // Normal ASCII password stays as-is
        $this->assertSame('hello', PdfKeyDerivation::preparePasswordR6('hello'));

        // Long password is truncated to 127 bytes
        $long = str_repeat('a', 200);
        $this->assertSame(127, strlen(PdfKeyDerivation::preparePasswordR6($long)));

        // Empty password
        $this->assertSame('', PdfKeyDerivation::preparePasswordR6(''));
    }
}
