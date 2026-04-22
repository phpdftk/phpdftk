# apprlabs/crypt

AES-128/256-CBC and RC4 ciphers with PDF key derivation (ISO 32000-2). No PDF object dependency — usable standalone for any encryption need.

Requires `ext-openssl`.

## Installation

```bash
composer require apprlabs/crypt
```

## Usage

```php
use ApprLabs\Crypt\AesCipher;
use ApprLabs\Crypt\Rc4Cipher;
use ApprLabs\Crypt\PdfKeyDerivation;

// AES-128 or AES-256 (random IV prepended to ciphertext)
$aes = new AesCipher(keyBits: 128);
$ciphertext = $aes->encrypt('secret data', $key);
$plaintext  = $aes->decrypt($ciphertext, $key);

// RC4 (pure PHP KSA + PRGA — for legacy PDF compatibility)
$rc4 = new Rc4Cipher();
$encrypted = $rc4->encrypt('data', $key);
$decrypted = $rc4->decrypt($encrypted, $key); // symmetric

// PDF key derivation (per-object key from document encryption key)
$objectKey = PdfKeyDerivation::deriveObjectKey(
    encryptionKey: $documentKey,
    objectNumber: 5,
    generation: 0
);

$ownerKey = PdfKeyDerivation::computeOwnerKey(
    ownerPassword: 'owner',
    userPassword: 'user',
    keyLength: 128
);
```

## Classes

| Class | Description |
|---|---|
| `AesCipher` | AES-128/256-CBC encrypt/decrypt; random IV prepended to output |
| `Rc4Cipher` | Pure-PHP RC4; symmetric — same method for encrypt and decrypt |
| `PdfKeyDerivation` | `deriveObjectKey()` and `computeOwnerKey()` per ISO 32000-2 §7.6 |
| `CryptInterface` | Common `encrypt(string, string): string` / `decrypt(string, string): string` interface |
