<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

final class CommentToken extends Token
{
    public function __construct(public string $data = '') {}

    public function append(string $chars): void
    {
        $this->data .= $chars;
    }
}
