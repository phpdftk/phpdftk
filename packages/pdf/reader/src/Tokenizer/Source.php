<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tokenizer;

/**
 * Seekable byte source abstraction for the PDF tokenizer.
 */
interface Source
{
    public function read(int $length): string;
    public function readByte(): ?string;
    public function peek(int $length = 1): string;
    public function seek(int $offset): void;
    public function tell(): int;
    public function size(): int;
    public function isEof(): bool;
}
