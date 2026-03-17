<?php declare(strict_types=1);
namespace Phpdftk\Crypt;

final class PdfKeyDerivation {
    /**
     * Derive an object encryption key per PDF spec section 7.6.
     *
     * @param string $encryptionKey  The document encryption key (binary string)
     * @param int    $objectNumber   The object number
     * @param int    $generationNumber The generation number
     * @param bool   $aes           Whether to include AES salt bytes
     */
    public static function deriveObjectKey(
        string $encryptionKey,
        int $objectNumber,
        int $generationNumber,
        bool $aes = false,
    ): string {
        // Append object number (3 bytes LE) and generation number (2 bytes LE)
        $input = $encryptionKey
            . chr($objectNumber & 0xFF)
            . chr(($objectNumber >> 8) & 0xFF)
            . chr(($objectNumber >> 16) & 0xFF)
            . chr($generationNumber & 0xFF)
            . chr(($generationNumber >> 8) & 0xFF);

        if ($aes) {
            // Append AES salt
            $input .= "\x73\x41\x6C\x54";
        }

        $hash = md5($input, true);

        // Key length: min(floor((len(encryptionKey) + 5) / 1) * 1, 16) actually:
        // Truncate to max(5, len(encryptionKey) + 5) but capped at 16 (MD5 output length)
        $keyLen = min(strlen($encryptionKey) + 5, 16);
        $keyLen = max(5, $keyLen);

        return substr($hash, 0, $keyLen);
    }

    /**
     * Compute the owner key hash for PDF encryption.
     *
     * @param string $ownerPassword The owner password (up to 32 bytes)
     * @param string $userPassword  The user password (up to 32 bytes)
     * @param int    $keyLength     Key length in bits (40, 128, or 256)
     */
    public static function computeOwnerKey(
        string $ownerPassword,
        string $userPassword,
        int $keyLength,
    ): string {
        // Pad string to 32 bytes per PDF spec
        $padding = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
                 . "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

        $padOwner = substr($ownerPassword . $padding, 0, 32);
        $padUser  = substr($userPassword . $padding, 0, 32);

        // Step 1: MD5 hash of padded owner password
        $hash = md5($padOwner, true);

        // Step 2: For key lengths > 40, iterate MD5 50 times
        if ($keyLength > 40) {
            for ($i = 0; $i < 50; $i++) {
                $hash = md5($hash, true);
            }
        }

        // Step 3: Create RC4 key from first keyLength/8 bytes of hash
        $rc4KeyLen = $keyLength / 8;
        $rc4Key = substr($hash, 0, $rc4KeyLen);

        // Step 4: RC4-encrypt the padded user password
        $rc4 = new Rc4Cipher();
        $ownerKey = $rc4->encrypt($padUser, $rc4Key);

        // Step 5: For key lengths > 40, iterate RC4 encryption 19 more times
        if ($keyLength > 40) {
            for ($i = 1; $i <= 19; $i++) {
                $iterKey = '';
                for ($j = 0; $j < $rc4KeyLen; $j++) {
                    $iterKey .= chr(ord($rc4Key[$j]) ^ $i);
                }
                $ownerKey = $rc4->encrypt($ownerKey, $iterKey);
            }
        }

        // Truncate to key length bytes
        return substr($ownerKey, 0, $rc4KeyLen);
    }
}
