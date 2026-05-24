<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

/**
 * A run of character data. Per WHATWG the tokenizer emits one character at a
 * time, but we coalesce consecutive characters into a single token to keep
 * memory bounded — the tree construction stage's behaviour on character tokens
 * is identical whether they arrive one-at-a-time or in batches.
 */
final class CharacterToken extends Token
{
    public function __construct(public string $data) {}

    public function append(string $chars): void
    {
        $this->data .= $chars;
    }
}
