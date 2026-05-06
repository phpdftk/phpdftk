<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tokenizer;

/**
 * A single lexical token from the PDF byte stream, carrying its type,
 * string value, and byte offset within the source.
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $offset,
    ) {}
}
