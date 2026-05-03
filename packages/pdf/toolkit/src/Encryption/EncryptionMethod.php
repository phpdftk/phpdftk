<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Encryption;

/**
 * PDF encryption algorithms mapped to their encryption dictionary revisions.
 *
 * RC4-40 uses V1/R2 (40-bit key, PDF 1.1+). RC4-128 uses V2/R3 (128-bit key,
 * PDF 1.4+). AES-128 uses V4/R4 with a crypt filter (PDF 1.6+). AES-256 uses
 * V5/R6 with the extension-level key derivation (PDF 2.0). The PublicKey
 * variants use the same V/R but derive the file encryption key from a PKCS#7
 * envelope instead of a user/owner password.
 */
enum EncryptionMethod
{
    case Rc440;
    case Rc4128;
    case Aes128;
    case Aes256;
    case PublicKeyAes128;
    case PublicKeyAes256;
}
