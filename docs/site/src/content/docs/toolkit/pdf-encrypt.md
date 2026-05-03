---
title: PDF Encrypt
description: Encrypt, decrypt, and change passwords on PDF documents.
---

`PdfEncrypt` applies or removes PDF encryption. It performs a full document rewrite (not incremental), rebuilding the page tree with the new encryption settings.

## Opening a PDF

```php
use Phpdftk\Pdf\Toolkit\PdfEncrypt;

// Unencrypted PDF
$enc = PdfEncrypt::open('doc.pdf');

// Already encrypted -- supply the password to open
$enc = PdfEncrypt::open('encrypted.pdf', password: 'secret');

// From string
$enc = PdfEncrypt::openString($pdfBytes);
```

## Encrypting a PDF

```php
use Phpdftk\Pdf\Toolkit\Encryption\EncryptionMethod;

$enc->encrypt('userpass', 'ownerpass', EncryptionMethod::Aes256)
    ->save('encrypted.pdf');
```

### Encryption methods

| Enum case | Algorithm |
|---|---|
| `EncryptionMethod::Rc440` | RC4 40-bit |
| `EncryptionMethod::Rc4128` | RC4 128-bit |
| `EncryptionMethod::Aes128` | AES 128-bit |
| `EncryptionMethod::Aes256` | AES 256-bit (default) |

The default when no method is specified is `Aes256`.

## Decrypting a PDF

Remove all encryption, producing an unprotected document:

```php
PdfEncrypt::open('encrypted.pdf', password: 'secret')
    ->decrypt()
    ->save('decrypted.pdf');
```

## Changing passwords

```php
PdfEncrypt::open('encrypted.pdf', password: 'oldpass')
    ->changePasswords('newuser', 'newowner')
    ->save('rekeyed.pdf');
```

If no encryption method has been explicitly set, `changePasswords` defaults to AES-256.

## Setting permissions

Use the `Permission` constants to control what operations are allowed:

```php
use Phpdftk\Pdf\Toolkit\Encryption\Permission;

$enc->encrypt('user', 'owner', EncryptionMethod::Aes256,
    Permission::PRINT | Permission::COPY
);
```

### Available permissions

| Constant | Description |
|---|---|
| `Permission::PRINT` | Print the document |
| `Permission::MODIFY` | Modify document contents |
| `Permission::COPY` | Copy or extract text/graphics |
| `Permission::ANNOTATE` | Add or modify annotations |
| `Permission::FILL_FORMS` | Fill in form fields |
| `Permission::ACCESSIBILITY` | Extract text for accessibility |
| `Permission::ASSEMBLE` | Assemble the document (insert, rotate, delete pages) |
| `Permission::PRINT_HIGH` | High-resolution printing |
| `Permission::ALL` | All permissions granted |

Permissions can also be set separately:

```php
$enc->setPermissions(Permission::PRINT | Permission::FILL_FORMS);
```

## Querying encryption status

```php
$enc->isEncrypted(); // bool
```

## Saving

```php
// To file
$enc->save('output.pdf');

// To string
$bytes = $enc->toBytes();
```

## Document info

```php
$enc->getPageCount(); // int
```

## Escape hatch

```php
$reader = $enc->getReader(); // PdfReader
```
