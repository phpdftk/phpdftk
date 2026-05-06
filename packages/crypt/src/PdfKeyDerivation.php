<?php

declare(strict_types=1);

namespace Phpdftk\Crypt;

/**
 * PDF encryption key derivation — ISO 32000-2 §7.6.
 *
 * Covers the Standard security handler (R=2/3/4 with RC4/AES-128
 * and R=6 with AES-256).
 */
final class PdfKeyDerivation
{
    /** Standard 32-byte padding string per PDF spec §7.6.3.3. */
    public const PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
        . "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    /**
     * Derive an object encryption key per PDF spec §7.6.3.3.
     */
    public static function deriveObjectKey(
        string $encryptionKey,
        int $objectNumber,
        int $generationNumber,
        bool $aes = false,
    ): string {
        $input = $encryptionKey
            . chr($objectNumber & 0xFF)
            . chr(($objectNumber >> 8) & 0xFF)
            . chr(($objectNumber >> 16) & 0xFF)
            . chr($generationNumber & 0xFF)
            . chr(($generationNumber >> 8) & 0xFF);

        if ($aes) {
            $input .= "\x73\x41\x6C\x54"; // "sAlT"
        }

        $hash = md5($input, true);
        $keyLen = min(strlen($encryptionKey) + 5, 16);
        $keyLen = max(5, $keyLen);

        return substr($hash, 0, $keyLen);
    }

    /**
     * Compute the owner key (/O) — §7.6.3.4 (R=2/3/4).
     */
    public static function computeOwnerKey(
        string $ownerPassword,
        string $userPassword,
        int $keyLength,
    ): string {
        $padOwner = self::pad($ownerPassword);
        $padUser = self::pad($userPassword);

        $hash = md5($padOwner, true);
        if ($keyLength > 40) {
            for ($i = 0; $i < 50; $i++) {
                $hash = md5($hash, true);
            }
        }

        $rc4KeyLen = $keyLength / 8;
        $rc4Key = substr($hash, 0, $rc4KeyLen);

        $rc4 = new Rc4Cipher();
        $ownerKey = $rc4->encrypt($padUser, $rc4Key);

        if ($keyLength > 40) {
            for ($i = 1; $i <= 19; $i++) {
                $iterKey = '';
                for ($j = 0; $j < $rc4KeyLen; $j++) {
                    $iterKey .= chr(ord($rc4Key[$j]) ^ $i);
                }
                $ownerKey = $rc4->encrypt($ownerKey, $iterKey);
            }
        }

        return substr($ownerKey, 0, 32);
    }

    /**
     * Compute the file encryption key from the user password — §7.6.3.3.
     *
     * @param string $userPassword  The user password
     * @param string $oValue        The /O value from the encrypt dictionary (32 bytes)
     * @param int    $pValue        The /P permissions value (signed 32-bit)
     * @param string $fileId        The first element of the /ID array
     * @param int    $keyLengthBits Key length in bits (40, 56, 64, 80, 96, 128)
     * @param int    $revision      Revision (R=2..4)
     * @param bool   $encryptMetadata Whether metadata is encrypted (R=4 only)
     */
    public static function computeFileEncryptionKey(
        string $userPassword,
        string $oValue,
        int $pValue,
        string $fileId,
        int $keyLengthBits = 128,
        int $revision = 3,
        bool $encryptMetadata = true,
    ): string {
        $padded = self::pad($userPassword);

        $input = $padded . $oValue;
        // /P as a 32-bit signed LE value
        $input .= pack('V', $pValue);
        $input .= $fileId;

        if ($revision >= 4 && !$encryptMetadata) {
            $input .= "\xFF\xFF\xFF\xFF";
        }

        $hash = md5($input, true);

        $keyLen = $keyLengthBits / 8;

        if ($revision >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $hash = md5(substr($hash, 0, $keyLen), true);
            }
        }

        return substr($hash, 0, $keyLen);
    }

    /**
     * Compute the user key (/U) — §7.6.3.4.
     *
     * @param string $encryptionKey The file encryption key
     * @param string $fileId        The first element of /ID
     * @param int    $revision      Revision (R=2..4)
     */
    public static function computeUserKey(
        string $encryptionKey,
        string $fileId,
        int $revision = 3,
    ): string {
        $rc4 = new Rc4Cipher();

        if ($revision === 2) {
            return $rc4->encrypt(self::PADDING, $encryptionKey);
        }

        // R >= 3: MD5 hash of padding + file ID, then RC4 with 20 iterations
        $hash = md5(self::PADDING . $fileId, true);
        $result = $rc4->encrypt($hash, $encryptionKey);

        $keyLen = strlen($encryptionKey);
        for ($i = 1; $i <= 19; $i++) {
            $iterKey = '';
            for ($j = 0; $j < $keyLen; $j++) {
                $iterKey .= chr(ord($encryptionKey[$j]) ^ $i);
            }
            $result = $rc4->encrypt($result, $iterKey);
        }

        // Pad to 32 bytes with arbitrary data
        return str_pad($result, 32, "\x00");
    }

    /**
     * Authenticate a user password — returns the file encryption key
     * if the password is valid, null otherwise.
     */
    public static function authenticateUserPassword(
        string $password,
        string $oValue,
        string $uValue,
        int $pValue,
        string $fileId,
        int $keyLengthBits = 128,
        int $revision = 3,
        bool $encryptMetadata = true,
    ): ?string {
        $key = self::computeFileEncryptionKey(
            $password,
            $oValue,
            $pValue,
            $fileId,
            $keyLengthBits,
            $revision,
            $encryptMetadata,
        );

        $computedU = self::computeUserKey($key, $fileId, $revision);

        if ($revision === 2) {
            if ($computedU === $uValue) {
                return $key;
            }
        } else {
            // R >= 3: compare first 16 bytes only
            if (substr($computedU, 0, 16) === substr($uValue, 0, 16)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Authenticate an owner password — returns the file encryption key
     * if the password is valid, null otherwise.
     */
    public static function authenticateOwnerPassword(
        string $ownerPassword,
        string $oValue,
        string $uValue,
        int $pValue,
        string $fileId,
        int $keyLengthBits = 128,
        int $revision = 3,
        bool $encryptMetadata = true,
    ): ?string {
        // Derive the RC4 key from the owner password
        $padOwner = self::pad($ownerPassword);
        $hash = md5($padOwner, true);
        if ($keyLengthBits > 40) {
            for ($i = 0; $i < 50; $i++) {
                $hash = md5($hash, true);
            }
        }
        $rc4KeyLen = $keyLengthBits / 8;
        $rc4Key = substr($hash, 0, $rc4KeyLen);

        // Decrypt /O to recover the user password
        $rc4 = new Rc4Cipher();
        if ($revision === 2) {
            $userPassword = $rc4->decrypt($oValue, $rc4Key);
        } else {
            $userPassword = $oValue;
            for ($i = 19; $i >= 0; $i--) {
                $iterKey = '';
                for ($j = 0; $j < $rc4KeyLen; $j++) {
                    $iterKey .= chr(ord($rc4Key[$j]) ^ $i);
                }
                $userPassword = $rc4->decrypt($userPassword, $iterKey);
            }
        }

        // Now authenticate using the recovered user password
        return self::authenticateUserPassword(
            $userPassword,
            $oValue,
            $uValue,
            $pValue,
            $fileId,
            $keyLengthBits,
            $revision,
            $encryptMetadata,
        );
    }

    // -----------------------------------------------------------------------
    // R=6 (AES-256) — ISO 32000-2 §7.6.4.3.3 / §7.6.4.3.4
    // -----------------------------------------------------------------------

    /**
     * R=6 iterative hash algorithm — ISO 32000-2 §7.6.4.3.4.
     *
     * @param string $password  UTF-8 password (already SASLprep'd, truncated to 127 bytes)
     * @param string $salt      8-byte salt
     * @param string $userKey   First 48 bytes of /U value (empty for user password validation)
     */
    public static function computeHashR6(string $password, string $salt, string $userKey = ''): string
    {
        $k = hash('sha256', $password . $salt . $userKey, true);

        $round = 0;
        while (true) {
            // Build K1 = (password + K + userKey) repeated 64 times
            $k1Single = $password . $k . $userKey;
            $k1 = str_repeat($k1Single, 64);

            // AES-128-CBC encrypt K1 with key=K[0:16], IV=K[16:32]
            $aesKey = substr($k, 0, 16);
            $aesIv = substr($k, 16, 16);
            $e = openssl_encrypt($k1, 'AES-128-CBC', $aesKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $aesIv);

            // Pick hash based on first byte of E mod 3
            $remainder = ord($e[0]) % 3;
            $k = match ($remainder) {
                0 => hash('sha256', $e, true),
                1 => hash('sha384', $e, true),
                2 => hash('sha512', $e, true),
            };

            // Continue while round < 64, or while last byte of E > (round - 32)
            if ($round >= 63 && ord($e[strlen($e) - 1]) <= $round - 32) {
                break;
            }
            $round++;
        }

        return substr($k, 0, 32);
    }

    /**
     * Compute /U and /UE values for R=6 — ISO 32000-2 §7.6.4.3.3 (Algorithm 2.A step a).
     *
     * @return array{u: string, ue: string} U is 48 bytes, UE is 32 bytes
     */
    public static function computeUValueR6(string $password, string $fileEncryptionKey): array
    {
        $validationSalt = random_bytes(8);
        $keySalt = random_bytes(8);

        $hash = self::computeHashR6($password, $validationSalt);
        $u = $hash . $validationSalt . $keySalt; // 48 bytes

        // UE = AES-256-CBC encrypt the file encryption key
        $ueKey = self::computeHashR6($password, $keySalt);
        $iv = str_repeat("\x00", 16);
        $ue = openssl_encrypt($fileEncryptionKey, 'AES-256-CBC', $ueKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);

        return ['u' => $u, 'ue' => $ue];
    }

    /**
     * Compute /O and /OE values for R=6 — ISO 32000-2 §7.6.4.3.3 (Algorithm 2.A step b).
     *
     * @param string $uValue First 48 bytes of the /U value
     * @return array{o: string, oe: string} O is 48 bytes, OE is 32 bytes
     */
    public static function computeOValueR6(string $password, string $fileEncryptionKey, string $uValue): array
    {
        $validationSalt = random_bytes(8);
        $keySalt = random_bytes(8);

        $u48 = substr($uValue, 0, 48);
        $hash = self::computeHashR6($password, $validationSalt, $u48);
        $o = $hash . $validationSalt . $keySalt; // 48 bytes

        // OE = AES-256-CBC encrypt the file encryption key
        $oeKey = self::computeHashR6($password, $keySalt, $u48);
        $iv = str_repeat("\x00", 16);
        $oe = openssl_encrypt($fileEncryptionKey, 'AES-256-CBC', $oeKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);

        return ['o' => $o, 'oe' => $oe];
    }

    /**
     * Compute /Perms value for R=6 — ISO 32000-2 §7.6.4.3.3 (Algorithm 2.A step c).
     */
    public static function computePermsR6(int $permissions, string $fileEncryptionKey, bool $encryptMetadata = true): string
    {
        // Build 16-byte buffer
        $buf = pack('V', $permissions);       // P as 4 bytes LE
        $buf .= "\xFF\xFF\xFF\xFF";           // 4 bytes 0xFF
        $buf .= $encryptMetadata ? 'T' : 'F'; // 1 byte
        $buf .= 'adb';                        // 3 bytes
        $buf .= random_bytes(4);              // 4 random bytes

        // AES-256-ECB encrypt
        $result = openssl_encrypt($buf, 'AES-256-ECB', $fileEncryptionKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);

        return $result;
    }

    /**
     * Authenticate a user password for R=6 — returns file encryption key or null.
     *
     * @param string $password  UTF-8 password (already SASLprep'd, truncated to 127 bytes)
     * @param string $uValue    48-byte /U value
     * @param string $ueValue   32-byte /UE value
     */
    public static function authenticateUserPasswordR6(string $password, string $uValue, string $ueValue): ?string
    {
        $validationSalt = substr($uValue, 32, 8);
        $keySalt = substr($uValue, 40, 8);

        $hash = self::computeHashR6($password, $validationSalt);

        // Compare hash with U[0:32]
        if (!hash_equals(substr($uValue, 0, 32), $hash)) {
            return null;
        }

        // Decrypt file encryption key from UE
        $decryptKey = self::computeHashR6($password, $keySalt);
        $iv = str_repeat("\x00", 16);
        $fileKey = openssl_decrypt($ueValue, 'AES-256-CBC', $decryptKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);

        return $fileKey !== false ? $fileKey : null;
    }

    /**
     * Authenticate an owner password for R=6 — returns file encryption key or null.
     *
     * @param string $password  UTF-8 password (already SASLprep'd, truncated to 127 bytes)
     * @param string $oValue    48-byte /O value
     * @param string $oeValue   32-byte /OE value
     * @param string $uValue    48-byte /U value
     */
    public static function authenticateOwnerPasswordR6(string $password, string $oValue, string $oeValue, string $uValue): ?string
    {
        $validationSalt = substr($oValue, 32, 8);
        $keySalt = substr($oValue, 40, 8);
        $u48 = substr($uValue, 0, 48);

        $hash = self::computeHashR6($password, $validationSalt, $u48);

        // Compare hash with O[0:32]
        if (!hash_equals(substr($oValue, 0, 32), $hash)) {
            return null;
        }

        // Decrypt file encryption key from OE
        $decryptKey = self::computeHashR6($password, $keySalt, $u48);
        $iv = str_repeat("\x00", 16);
        $fileKey = openssl_decrypt($oeValue, 'AES-256-CBC', $decryptKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);

        return $fileKey !== false ? $fileKey : null;
    }

    /**
     * Normalize a password via SASLprep (RFC 4013).
     *
     * Required for PDF 2.0 encryption (R=6, AES-256) per ISO 32000-2 §7.6.4.3.2.
     */
    public static function saslPrep(string $password): string
    {
        return SaslPrep::prepare($password);
    }

    /**
     * Pad or truncate a password to 32 bytes using the standard padding.
     */
    public static function pad(string $password): string
    {
        return substr($password . self::PADDING, 0, 32);
    }

    /**
     * Prepare a password for R=6: SASLprep + truncate to 127 bytes.
     */
    public static function preparePasswordR6(string $password): string
    {
        $prepared = self::saslPrep($password);
        return substr($prepared, 0, 127);
    }
}
