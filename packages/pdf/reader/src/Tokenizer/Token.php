<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tokenizer;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $offset,
    ) {
    }
}
