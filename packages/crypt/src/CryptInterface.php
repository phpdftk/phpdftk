<?php declare(strict_types=1);
namespace Phpdftk\Crypt;

interface CryptInterface {
    public function encrypt(string $data, string $key): string;
    public function decrypt(string $data, string $key): string;
}
