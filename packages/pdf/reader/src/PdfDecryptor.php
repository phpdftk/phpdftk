<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader;

use ApprLabs\Crypt\AesCipher;
use ApprLabs\Crypt\PdfKeyDerivation;
use ApprLabs\Crypt\Rc4Cipher;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;

/**
 * Decrypts PDF objects using the Standard security handler.
 *
 * Supports:
 *   - V=1 R=2: RC4 40-bit
 *   - V=2 R=3: RC4 variable length (40-128 bit)
 *   - V=4 R=4: AES-128 or RC4-128 via crypt filters
 *   - V=5 R=6: AES-256 via crypt filters
 */
final class PdfDecryptor
{
    private readonly string $encryptionKey;
    private readonly bool $useAes;
    private readonly int $revision;
    private readonly int $aesKeyBits;

    private function __construct(
        string $encryptionKey,
        bool $useAes,
        int $revision,
        int $aesKeyBits = 128,
    ) {
        $this->encryptionKey = $encryptionKey;
        $this->useAes = $useAes;
        $this->revision = $revision;
        $this->aesKeyBits = $aesKeyBits;
    }

    /**
     * Build a decryptor from the trailer's /Encrypt dictionary and a password.
     *
     * Tries the password as the user password first, then as the owner password.
     *
     * @throws InvalidPdfException If the password is wrong or the encryption is unsupported
     */
    public static function fromEncryptDict(
        PdfDictionary $encryptDict,
        string $password,
        string $fileId,
    ): self {
        $v = self::intVal($encryptDict, 'V', 0);
        $r = self::intVal($encryptDict, 'R', 2);

        // V=5 R=6: AES-256
        if ($v === 5) {
            return self::fromEncryptDictR6($encryptDict, $password, $r);
        }

        $keyLengthBits = self::intVal($encryptDict, 'Length', $v === 1 ? 40 : 128);
        $p = self::intVal($encryptDict, 'P', 0);

        // Handle signed 32-bit P value
        if ($p > 0x7FFFFFFF) {
            $p = $p - 0x100000000;
        }

        $oValue = self::stringVal($encryptDict, 'O');
        $uValue = self::stringVal($encryptDict, 'U');

        if ($oValue === null || $uValue === null) {
            throw new InvalidPdfException('Encrypt dictionary missing /O or /U values');
        }

        $encryptMetadata = true;
        $emVal = $encryptDict->get('EncryptMetadata');
        if ($emVal instanceof \ApprLabs\Pdf\Core\PdfBoolean) {
            $encryptMetadata = $emVal->toPdf() === 'true';
        }

        // Determine cipher from crypt filters (V=4)
        $useAes = false;
        if ($v === 4) {
            $stmF = $encryptDict->get('StmF');
            $cfName = $stmF instanceof PdfName ? $stmF->value : 'StdCF';
            $cf = $encryptDict->get('CF');
            if ($cf instanceof PdfDictionary) {
                $filter = $cf->get($cfName);
                if ($filter instanceof PdfDictionary) {
                    $cfm = $filter->get('CFM');
                    if ($cfm instanceof PdfName && $cfm->value === 'AESV2') {
                        $useAes = true;
                    }
                }
            }
        }

        // Try user password
        $key = PdfKeyDerivation::authenticateUserPassword(
            $password, $oValue, $uValue, $p, $fileId,
            $keyLengthBits, $r, $encryptMetadata
        );

        // Try owner password
        if ($key === null) {
            $key = PdfKeyDerivation::authenticateOwnerPassword(
                $password, $oValue, $uValue, $p, $fileId,
                $keyLengthBits, $r, $encryptMetadata
            );
        }

        // Try empty password as fallback
        if ($key === null && $password !== '') {
            $key = PdfKeyDerivation::authenticateUserPassword(
                '', $oValue, $uValue, $p, $fileId,
                $keyLengthBits, $r, $encryptMetadata
            );
        }

        if ($key === null) {
            throw new InvalidPdfException('Invalid password for encrypted PDF');
        }

        return new self($key, $useAes, $r);
    }

    /**
     * Decrypt all string and stream values within a parsed object.
     *
     * The /Encrypt dictionary itself must NOT be decrypted.
     */
    public function decryptObject(Serializable $object, int $objNum, int $genNum): Serializable
    {
        if ($object instanceof PdfDictionary) {
            return $this->decryptDictionary($object, $objNum, $genNum);
        }
        if ($object instanceof \ApprLabs\Pdf\Core\PdfStream) {
            return $this->decryptStream($object, $objNum, $genNum);
        }
        if ($object instanceof PdfString) {
            return $this->decryptString($object, $objNum, $genNum);
        }
        if ($object instanceof PdfArray) {
            return $this->decryptArray($object, $objNum, $genNum);
        }
        return $object;
    }

    private function decryptDictionary(PdfDictionary $dict, int $objNum, int $genNum): PdfDictionary
    {
        // Don't decrypt the /Encrypt dictionary or XRef streams
        $type = $dict->get('Type');
        if ($type instanceof PdfName && ($type->value === 'Encrypt' || $type->value === 'XRef')) {
            return $dict;
        }

        $result = new PdfDictionary();
        foreach ($dict->entries as $key => $value) {
            if ($value instanceof PdfString) {
                $result->set($key, $this->decryptString($value, $objNum, $genNum));
            } elseif ($value instanceof PdfArray) {
                $result->set($key, $this->decryptArray($value, $objNum, $genNum));
            } elseif ($value instanceof PdfDictionary) {
                $result->set($key, $this->decryptDictionary($value, $objNum, $genNum));
            } else {
                $result->set($key, $value);
            }
        }
        return $result;
    }

    private function decryptStream(\ApprLabs\Pdf\Core\PdfStream $stream, int $objNum, int $genNum): \ApprLabs\Pdf\Core\PdfStream
    {
        // Don't decrypt XRef streams or metadata streams (when EncryptMetadata=false)
        $type = $stream->dictionary->get('Type');
        if ($type instanceof PdfName && $type->value === 'XRef') {
            return $stream;
        }

        // Decrypt the stream data
        $objectKey = $this->deriveObjectKey($objNum, $genNum);
        $decryptedData = $this->decrypt($stream->data, $objectKey);

        // Decrypt strings in the dictionary
        $decryptedDict = $this->decryptDictionary($stream->dictionary, $objNum, $genNum);

        return new \ApprLabs\Pdf\Core\PdfStream($decryptedDict, $decryptedData);
    }

    private function decryptString(PdfString $string, int $objNum, int $genNum): PdfString
    {
        if ($string->value === '') {
            return $string;
        }

        $objectKey = $this->deriveObjectKey($objNum, $genNum);
        $decrypted = $this->decrypt($string->value, $objectKey);

        return new PdfString($decrypted, $string->hex);
    }

    private function decryptArray(PdfArray $array, int $objNum, int $genNum): PdfArray
    {
        $items = [];
        foreach ($array->items as $item) {
            if ($item instanceof PdfString) {
                $items[] = $this->decryptString($item, $objNum, $genNum);
            } elseif ($item instanceof PdfDictionary) {
                $items[] = $this->decryptDictionary($item, $objNum, $genNum);
            } elseif ($item instanceof PdfArray) {
                $items[] = $this->decryptArray($item, $objNum, $genNum);
            } else {
                $items[] = $item;
            }
        }
        return new PdfArray($items);
    }

    /**
     * Build a decryptor for V=5 R=6 (AES-256).
     */
    private static function fromEncryptDictR6(
        PdfDictionary $encryptDict,
        string $password,
        int $r,
    ): self {
        $uValue = self::stringVal($encryptDict, 'U');
        $ueValue = self::stringVal($encryptDict, 'UE');
        $oValue = self::stringVal($encryptDict, 'O');
        $oeValue = self::stringVal($encryptDict, 'OE');

        if ($uValue === null || $ueValue === null || $oValue === null || $oeValue === null) {
            throw new InvalidPdfException('Encrypt dictionary missing /U, /UE, /O, or /OE values for R=6');
        }

        // SASLprep + truncate to 127 bytes
        $pw = PdfKeyDerivation::preparePasswordR6($password);

        // Try user password
        $key = PdfKeyDerivation::authenticateUserPasswordR6($pw, $uValue, $ueValue);

        // Try owner password
        if ($key === null) {
            $key = PdfKeyDerivation::authenticateOwnerPasswordR6($pw, $oValue, $oeValue, $uValue);
        }

        // Try empty password as fallback
        if ($key === null && $password !== '') {
            $emptyPw = PdfKeyDerivation::preparePasswordR6('');
            $key = PdfKeyDerivation::authenticateUserPasswordR6($emptyPw, $uValue, $ueValue);
        }

        if ($key === null) {
            throw new InvalidPdfException('Invalid password for encrypted PDF');
        }

        return new self($key, true, $r, 256);
    }

    private function deriveObjectKey(int $objNum, int $genNum): string
    {
        // V=5 R=6: use file encryption key directly (no per-object derivation)
        if ($this->aesKeyBits === 256) {
            return $this->encryptionKey;
        }

        return PdfKeyDerivation::deriveObjectKey(
            $this->encryptionKey, $objNum, $genNum, $this->useAes
        );
    }

    private function decrypt(string $data, string $key): string
    {
        if ($data === '') {
            return '';
        }

        if ($this->useAes) {
            if (strlen($data) < 16) {
                return $data; // Too short for AES (no IV)
            }
            $aes = new AesCipher($this->aesKeyBits);
            try {
                return $aes->decrypt($data, $key);
            } catch (\RuntimeException) {
                return $data; // Decryption failed — return raw
            }
        }

        $rc4 = new Rc4Cipher();
        return $rc4->decrypt($data, $key);
    }

    private static function intVal(PdfDictionary $dict, string $key, int $default): int
    {
        $val = $dict->get($key);
        if ($val instanceof PdfNumber) {
            return (int) $val->toPdf();
        }
        if (is_int($val)) {
            return $val;
        }
        return $default;
    }

    private static function stringVal(PdfDictionary $dict, string $key): ?string
    {
        $val = $dict->get($key);
        if ($val instanceof PdfString) {
            return $val->value;
        }
        return null;
    }
}
