<?php

declare(strict_types=1);

namespace Phpdftk\Crypt;

/**
 * Symmetric cipher contract for PDF stream/string encryption.
 *
 * The key is derived externally by {@see PdfKeyDerivation} — implementations
 * only handle the raw encrypt/decrypt with a pre-derived key.
 */
interface CryptInterface
{
    public function encrypt(string $data, string $key): string;
    public function decrypt(string $data, string $key): string;
}
