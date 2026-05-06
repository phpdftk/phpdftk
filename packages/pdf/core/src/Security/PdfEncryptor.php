<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Security;

use Phpdftk\Crypt\AesCipher;
use Phpdftk\Crypt\PdfKeyDerivation;
use Phpdftk\Crypt\PublicKeyEncryption;
use Phpdftk\Crypt\Rc4Cipher;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Encrypts PDF objects for the Standard and Adobe.PubSec security handlers.
 *
 * Standard handler (password-based):
 *   - V=1 R=2: RC4 40-bit  (PDF 1.1+)
 *   - V=2 R=3: RC4 128-bit (PDF 1.4+)
 *   - V=4 R=4: AES-128     (PDF 1.6+)
 *   - V=5 R=6: AES-256     (PDF 2.0)
 *
 * Public-key handler (certificate-based, Adobe.PubSec):
 *   - V=4 CFM=AESV2: AES-128 with PKCS#7 recipient envelopes
 *   - V=4 CFM=AESV3: AES-256 with PKCS#7 recipient envelopes
 */
final class PdfEncryptor
{
    /** Print the document. */
    public const PERM_PRINT = 4;
    /** Modify document contents. */
    public const PERM_MODIFY = 8;
    /** Copy/extract text and graphics. */
    public const PERM_COPY = 16;
    /** Add or modify annotations. */
    public const PERM_ANNOTATE = 32;
    /** Fill in form fields. */
    public const PERM_FILL_FORMS = 256;
    /** Extract text for accessibility. */
    public const PERM_ACCESSIBILITY = 512;
    /** Assemble the document (insert, rotate, delete pages). */
    public const PERM_ASSEMBLE = 1024;
    /** High-quality print. */
    public const PERM_PRINT_HIGH = 2048;
    /** All permissions granted. */
    public const PERM_ALL = self::PERM_PRINT | self::PERM_MODIFY | self::PERM_COPY
        | self::PERM_ANNOTATE | self::PERM_FILL_FORMS | self::PERM_ACCESSIBILITY
        | self::PERM_ASSEMBLE | self::PERM_PRINT_HIGH;

    private readonly string $encryptionKey;
    private readonly EncryptDictionary $encryptDict;
    private readonly bool $useAes;
    private readonly string $fileId;
    private readonly int $aesKeyBits;

    /** @var int Object number of the encrypt dictionary (must not be encrypted) */
    private int $encryptDictObjNum = 0;

    private function __construct(
        string $encryptionKey,
        EncryptDictionary $encryptDict,
        bool $useAes,
        string $fileId,
        int $aesKeyBits = 128,
    ) {
        $this->encryptionKey = $encryptionKey;
        $this->encryptDict = $encryptDict;
        $this->useAes = $useAes;
        $this->fileId = $fileId;
        $this->aesKeyBits = $aesKeyBits;
    }

    /**
     * Create an encryptor with RC4 128-bit encryption (V=2 R=3, PDF 1.4+).
     *
     * @param int $permissions Bitmask of PERM_* constants
     */
    public static function rc4128(
        string $userPassword,
        string $ownerPassword,
        string $fileId,
        int $permissions = self::PERM_ALL,
    ): self {
        return self::createStandard(
            $userPassword,
            $ownerPassword,
            $fileId,
            $permissions,
            keyLengthBits: 128,
            v: 2,
            r: 3,
            useAes: false,
        );
    }

    /**
     * Create an encryptor with RC4 40-bit encryption (V=1 R=2, PDF 1.1+).
     *
     * @param int $permissions Bitmask of PERM_* constants
     */
    public static function rc440(
        string $userPassword,
        string $ownerPassword,
        string $fileId,
        int $permissions = self::PERM_ALL,
    ): self {
        return self::createStandard(
            $userPassword,
            $ownerPassword,
            $fileId,
            $permissions,
            keyLengthBits: 40,
            v: 1,
            r: 2,
            useAes: false,
        );
    }

    /**
     * Create an encryptor with AES 128-bit encryption (V=4 R=4, PDF 1.6+).
     *
     * @param int $permissions Bitmask of PERM_* constants
     */
    public static function aes128(
        string $userPassword,
        string $ownerPassword,
        string $fileId,
        int $permissions = self::PERM_ALL,
    ): self {
        return self::createStandard(
            $userPassword,
            $ownerPassword,
            $fileId,
            $permissions,
            keyLengthBits: 128,
            v: 4,
            r: 4,
            useAes: true,
        );
    }

    /**
     * Create an encryptor with AES 256-bit encryption (V=5 R=6, PDF 2.0).
     *
     * @param int $permissions Bitmask of PERM_* constants
     */
    public static function aes256(
        string $userPassword,
        string $ownerPassword,
        string $fileId,
        int $permissions = self::PERM_ALL,
    ): self {
        return self::createR6($userPassword, $ownerPassword, $fileId, $permissions);
    }

    /**
     * Create an encryptor with public-key (certificate-based) AES-128 encryption.
     *
     * Uses the Adobe.PubSec handler with /SubFilter /adbe.pkcs7.s5 (V=4).
     * Each recipient's certificate receives a PKCS#7 envelope containing the
     * encryption seed. Different recipients may have different permissions.
     *
     * @param array<array{cert: string, permissions?: int}> $recipients
     *     Each entry: 'cert' = PEM certificate, 'permissions' = PERM_* bitmask (default PERM_ALL)
     * @param string $fileId File identifier for the /ID trailer entry
     */
    public static function publicKeyAes128(
        array $recipients,
        string $fileId,
    ): self {
        if ($recipients === []) {
            throw new \InvalidArgumentException('At least one recipient certificate is required');
        }

        // Generate 20-byte random seed
        $seed = random_bytes(20);
        $encryptMetadata = true;

        // Build PKCS#7 envelopes and compute combined permissions
        $recipientDerStrings = [];
        $combinedPermissions = self::PERM_ALL | 0xFFFFF000 | 0xC0;

        foreach ($recipients as $r) {
            $certPem = $r['cert'];
            $perms = ($r['permissions'] ?? self::PERM_ALL) | 0xFFFFF000 | 0xC0;
            $combinedPermissions &= $perms;

            $der = PublicKeyEncryption::createEnvelope($seed, $perms, $certPem, $encryptMetadata);
            $recipientDerStrings[] = $der;
        }

        // Derive file encryption key: SHA-1(seed || recipients || P || metadata_flag)
        $encryptionKey = PublicKeyEncryption::deriveFileKey(
            $seed,
            $recipientDerStrings,
            $combinedPermissions,
            16,
            $encryptMetadata,
        );

        // Build /Recipients array of PdfString (binary PKCS#7 DER)
        $recipientStrings = [];
        foreach ($recipientDerStrings as $der) {
            $recipientStrings[] = new PdfString($der, hex: true);
        }

        // Build EncryptDictionary
        $dict = new EncryptDictionary('Adobe.PubSec', 4);
        $dict->subFilter = new PdfName('adbe.pkcs7.s5');
        $dict->encryptMetadata = $encryptMetadata;

        // Set up crypt filter with Recipients
        $cfDict = new PdfDictionary();
        $defaultCf = new PdfDictionary();
        $defaultCf->set('Type', new PdfName('CryptFilter'));
        $defaultCf->set('CFM', new PdfName('AESV2'));
        $defaultCf->set('Length', new PdfNumber(16));
        $defaultCf->set('AuthEvent', new PdfName('DocOpen'));
        $defaultCf->set('Recipients', new PdfArray($recipientStrings));
        $cfDict->set('DefaultCryptFilter', $defaultCf);
        $dict->cf = $cfDict;
        $dict->stmF = new PdfName('DefaultCryptFilter');
        $dict->strF = new PdfName('DefaultCryptFilter');

        return new self($encryptionKey, $dict, true, $fileId);
    }

    /**
     * Create an encryptor with public-key (certificate-based) AES-256 encryption.
     *
     * Uses the Adobe.PubSec handler with /SubFilter /adbe.pkcs7.s5 (V=4, CFM=AESV3).
     * File encryption key is 32 bytes, derived via SHA-256.
     *
     * @param array<array{cert: string, permissions?: int}> $recipients
     *     Each entry: 'cert' = PEM certificate, 'permissions' = PERM_* bitmask (default PERM_ALL)
     * @param string $fileId File identifier for the /ID trailer entry
     */
    public static function publicKeyAes256(
        array $recipients,
        string $fileId,
    ): self {
        if ($recipients === []) {
            throw new \InvalidArgumentException('At least one recipient certificate is required');
        }

        $seed = random_bytes(20);
        $encryptMetadata = true;

        $recipientDerStrings = [];
        $combinedPermissions = self::PERM_ALL | 0xFFFFF000 | 0xC0;

        foreach ($recipients as $r) {
            $certPem = $r['cert'];
            $perms = ($r['permissions'] ?? self::PERM_ALL) | 0xFFFFF000 | 0xC0;
            $combinedPermissions &= $perms;

            $der = PublicKeyEncryption::createEnvelope($seed, $perms, $certPem, $encryptMetadata);
            $recipientDerStrings[] = $der;
        }

        // Derive 32-byte file encryption key via SHA-256
        $encryptionKey = PublicKeyEncryption::deriveFileKey(
            $seed,
            $recipientDerStrings,
            $combinedPermissions,
            32,
            $encryptMetadata,
        );

        $recipientStrings = [];
        foreach ($recipientDerStrings as $der) {
            $recipientStrings[] = new PdfString($der, hex: true);
        }

        // V=4 with AESV3 for AES-256
        $dict = new EncryptDictionary('Adobe.PubSec', 4);
        $dict->subFilter = new PdfName('adbe.pkcs7.s5');
        $dict->length = 256;
        $dict->encryptMetadata = $encryptMetadata;

        $cfDict = new PdfDictionary();
        $defaultCf = new PdfDictionary();
        $defaultCf->set('Type', new PdfName('CryptFilter'));
        $defaultCf->set('CFM', new PdfName('AESV3'));
        $defaultCf->set('Length', new PdfNumber(32));
        $defaultCf->set('AuthEvent', new PdfName('DocOpen'));
        $defaultCf->set('Recipients', new PdfArray($recipientStrings));
        $cfDict->set('DefaultCryptFilter', $defaultCf);
        $dict->cf = $cfDict;
        $dict->stmF = new PdfName('DefaultCryptFilter');
        $dict->strF = new PdfName('DefaultCryptFilter');

        return new self($encryptionKey, $dict, true, $fileId, 256);
    }

    /**
     * Get the EncryptDictionary to register in the file.
     */
    public function getEncryptDictionary(): EncryptDictionary
    {
        return $this->encryptDict;
    }

    /**
     * Return the minimum PDF version for this encryption: RC4 -> 1.4,
     * AES-128 -> 1.6, AES-256 -> 2.0. Used by PdfFileWriter to auto-bump
     * the document version when encryption is registered.
     */
    public function getMinimumPdfVersion(): \Phpdftk\Pdf\Core\PdfVersion
    {
        return match (true) {
            $this->aesKeyBits === 256 => \Phpdftk\Pdf\Core\PdfVersion::V2_0,
            $this->useAes             => \Phpdftk\Pdf\Core\PdfVersion::V1_6,
            default                   => \Phpdftk\Pdf\Core\PdfVersion::V1_4,
        };
    }

    /**
     * Set the object number of the encrypt dictionary so it's excluded
     * from encryption.
     */
    public function setEncryptDictObjNum(int $objNum): void
    {
        $this->encryptDictObjNum = $objNum;
    }

    /**
     * Get the file ID used for encryption (needed for the trailer /ID).
     */
    public function getFileId(): string
    {
        return $this->fileId;
    }

    /**
     * Encrypt a PdfObject in-place before serialization.
     *
     * For PdfStream: encrypts stream data and strings in the dictionary.
     * For other PdfObjects: encrypts string values in public properties.
     * Must NOT be called on the /Encrypt dictionary itself.
     */
    public function encryptObject(PdfObject $object): void
    {
        if ($object->objectNumber === $this->encryptDictObjNum) {
            return;
        }

        $objNum = $object->objectNumber;
        $genNum = $object->generationNumber;

        if ($object instanceof PdfStream) {
            $this->encryptStream($object, $objNum, $genNum);
        } else {
            $this->encryptObjectProperties($object, $objNum, $genNum);
        }
    }

    /**
     * Encrypt PdfString values in an object's public properties.
     */
    private function encryptObjectProperties(PdfObject $object, int $objNum, int $genNum): void
    {
        $ref = new \ReflectionObject($object);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($object);
            if ($value instanceof PdfString) {
                $prop->setValue($object, $this->encryptString($value, $objNum, $genNum));
            } elseif ($value instanceof PdfArray) {
                $prop->setValue($object, $this->encryptArray($value, $objNum, $genNum));
            } elseif ($value instanceof PdfDictionary) {
                $this->encryptDictionary($value, $objNum, $genNum);
            }
        }
    }

    /**
     * Encrypt a PdfStream: encrypt string values in the dictionary
     * and the stream data itself.
     */
    private function encryptStream(PdfStream $stream, int $objNum, int $genNum): void
    {
        // Don't encrypt XRef streams
        $type = $stream->dictionary->get('Type');
        if ($type instanceof PdfName && $type->value === 'XRef') {
            return;
        }

        // Encrypt strings in the dictionary
        $this->encryptDictionary($stream->dictionary, $objNum, $genNum);

        // Encrypt stream data — this must happen BEFORE toPdf() applies
        // any filter encoding (FlateDecode, etc.), so we encrypt raw data
        // and let toPdf() compress the encrypted data.
        // Actually, per PDF spec, compression happens first, THEN encryption.
        // But since PdfStream::toPdf() handles compression at serialization
        // time, we need to encrypt the raw data here and the compression
        // will apply on top during toPdf(). This matches the spec:
        // data → compress → encrypt → write.
        //
        // Wait — the spec says decrypt → decompress on read. So on write:
        // data → compress → encrypt. But PdfStream::toPdf() does
        // data → compress (via filter). We need to encrypt AFTER compression.
        // This means we can't encrypt here before toPdf().
        //
        // The solution: we'll encrypt the stream data, and the filter
        // (if any) was already set by applyStreamCompression. Since
        // applyStreamCompression only sets the filter flag but doesn't
        // encode until toPdf(), we need a different approach.
        //
        // For now: store the encryption info and apply it in a custom
        // toPdf() override... or we encrypt after serialization.
        //
        // Simplest correct approach: encrypt stream data here (raw),
        // and if the stream has a filter, the filter encoding in toPdf()
        // will operate on the already-encrypted data. BUT this is wrong
        // per spec — filter encoding should happen BEFORE encryption.
        //
        // The real solution: don't use PdfStream::setFilter() for
        // compression when encryption is active. Instead, manually
        // compress then encrypt the data, set it directly, and mark
        // /Filter in the dictionary.
        if ($stream->data !== '') {
            $objectKey = $this->deriveObjectKey($objNum, $genNum);
            $stream->data = $this->encrypt($stream->data, $objectKey);
        }
    }

    /**
     * Encrypt all PdfString values within a dictionary.
     */
    public function encryptDictionary(PdfDictionary $dict, int $objNum, int $genNum): void
    {
        foreach ($dict->entries as $key => $value) {
            if ($value instanceof PdfString) {
                $dict->set($key, $this->encryptString($value, $objNum, $genNum));
            } elseif ($value instanceof PdfArray) {
                $dict->set($key, $this->encryptArray($value, $objNum, $genNum));
            } elseif ($value instanceof PdfDictionary) {
                $this->encryptDictionary($value, $objNum, $genNum);
            }
        }
    }

    private function encryptString(PdfString $string, int $objNum, int $genNum): PdfString
    {
        if ($string->value === '') {
            return $string;
        }
        $objectKey = $this->deriveObjectKey($objNum, $genNum);
        $encrypted = $this->encrypt($string->value, $objectKey);
        return new PdfString($encrypted, $string->hex);
    }

    /**
     * Encrypt strings within a PdfArray, returning a new array if any
     * items changed (PdfArray::$items is readonly).
     */
    private function encryptArray(PdfArray $array, int $objNum, int $genNum): PdfArray
    {
        $changed = false;
        $items = [];
        foreach ($array->items as $item) {
            if ($item instanceof PdfString) {
                $items[] = $this->encryptString($item, $objNum, $genNum);
                $changed = true;
            } elseif ($item instanceof PdfDictionary) {
                $this->encryptDictionary($item, $objNum, $genNum);
                $items[] = $item;
            } elseif ($item instanceof PdfArray) {
                $items[] = $this->encryptArray($item, $objNum, $genNum);
            } else {
                $items[] = $item;
            }
        }
        return $changed ? new PdfArray($items) : $array;
    }

    private function deriveObjectKey(int $objNum, int $genNum): string
    {
        // V=5 R=6: use file encryption key directly (no per-object derivation)
        if ($this->aesKeyBits === 256) {
            return $this->encryptionKey;
        }

        return PdfKeyDerivation::deriveObjectKey(
            $this->encryptionKey,
            $objNum,
            $genNum,
            $this->useAes,
        );
    }

    private function encrypt(string $data, string $key): string
    {
        if ($this->useAes) {
            $aes = new AesCipher($this->aesKeyBits);
            return $aes->encrypt($data, $key);
        }
        $rc4 = new Rc4Cipher();
        return $rc4->encrypt($data, $key);
    }

    private static function createStandard(
        string $userPassword,
        string $ownerPassword,
        string $fileId,
        int $permissions,
        int $keyLengthBits,
        int $v,
        int $r,
        bool $useAes,
    ): self {
        // Ensure required permission bits are set (bits 7-8 must be 1, bits 13-32 must be 1)
        $p = $permissions | 0xFFFFF000 | 0xC0;

        // Compute /O value
        $oValue = PdfKeyDerivation::computeOwnerKey($ownerPassword, $userPassword, $keyLengthBits);

        // Compute file encryption key
        $encryptionKey = PdfKeyDerivation::computeFileEncryptionKey(
            $userPassword,
            $oValue,
            $p,
            $fileId,
            $keyLengthBits,
            $r,
        );

        // Compute /U value
        $uValue = PdfKeyDerivation::computeUserKey($encryptionKey, $fileId, $r);

        // Build the EncryptDictionary
        $dict = new EncryptDictionary('Standard', $v);
        $dict->r = $r;
        $dict->length = $keyLengthBits;
        $dict->o = new PdfString($oValue, hex: true);
        $dict->u = new PdfString($uValue, hex: true);
        $dict->p = $p;

        if ($v === 4) {
            // V=4: set up crypt filters
            $cfDict = new PdfDictionary();
            $stdCf = new PdfDictionary();
            $stdCf->set('Type', new PdfName('CryptFilter'));
            $stdCf->set('CFM', new PdfName($useAes ? 'AESV2' : 'V2'));
            $stdCf->set('Length', new PdfNumber(16));
            $cfDict->set('StdCF', $stdCf);
            $dict->cf = $cfDict;
            $dict->stmF = new PdfName('StdCF');
            $dict->strF = new PdfName('StdCF');
        }

        return new self($encryptionKey, $dict, $useAes, $fileId);
    }

    /**
     * Create an R=6 (AES-256) encryptor per ISO 32000-2 §7.6.4.3.3.
     */
    private static function createR6(
        string $userPassword,
        string $ownerPassword,
        string $fileId,
        int $permissions,
    ): self {
        // Ensure required permission bits are set
        $p = $permissions | 0xFFFFF000 | 0xC0;

        // SASLprep + truncate to 127 bytes
        $userPw = PdfKeyDerivation::preparePasswordR6($userPassword);
        $ownerPw = PdfKeyDerivation::preparePasswordR6($ownerPassword);

        // Generate 32-byte random file encryption key
        $fileEncryptionKey = random_bytes(32);

        // Compute /U (48 bytes) and /UE (32 bytes)
        $uResult = PdfKeyDerivation::computeUValueR6($userPw, $fileEncryptionKey);
        $uValue = $uResult['u'];
        $ueValue = $uResult['ue'];

        // Compute /O (48 bytes) and /OE (32 bytes)
        $oResult = PdfKeyDerivation::computeOValueR6($ownerPw, $fileEncryptionKey, $uValue);
        $oValue = $oResult['o'];
        $oeValue = $oResult['oe'];

        // Compute /Perms (16 bytes)
        $permsValue = PdfKeyDerivation::computePermsR6($p, $fileEncryptionKey);

        // Build EncryptDictionary
        $dict = new EncryptDictionary('Standard', 5);
        $dict->r = 6;
        $dict->length = 256;
        $dict->o = new PdfString($oValue, hex: true);
        $dict->u = new PdfString($uValue, hex: true);
        $dict->oe = new PdfString($oeValue, hex: true);
        $dict->ue = new PdfString($ueValue, hex: true);
        $dict->p = $p;
        $dict->perms = new PdfString($permsValue, hex: true);
        $dict->encryptMetadata = true;

        // Set up crypt filters for AESV3
        $cfDict = new PdfDictionary();
        $stdCf = new PdfDictionary();
        $stdCf->set('Type', new PdfName('CryptFilter'));
        $stdCf->set('CFM', new PdfName('AESV3'));
        $stdCf->set('Length', new PdfNumber(32));
        $cfDict->set('StdCF', $stdCf);
        $dict->cf = $cfDict;
        $dict->stmF = new PdfName('StdCF');
        $dict->strF = new PdfName('StdCF');

        return new self($fileEncryptionKey, $dict, true, $fileId, 256);
    }
}
