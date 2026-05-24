<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

final class EndTagToken extends Token
{
    public string $tagName = '';
    public bool $selfClosing = false; // per WHATWG, end tags may also set this; it's a parse error but recorded

    /** @var list<array{name: string, value: string}> */
    public array $attributes = []; // attributes on end tags are parse errors, but tokenized

    public ?int $currentAttribute = null;
}
