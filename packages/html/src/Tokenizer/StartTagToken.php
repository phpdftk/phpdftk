<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

final class StartTagToken extends Token
{
    public string $tagName = '';
    public bool $selfClosing = false;

    /** @var list<array{name: string, value: string}> */
    public array $attributes = [];

    /**
     * The attribute currently being built. Pointer into $attributes, or null
     * if no attribute is currently being accumulated. Set by the tokenizer's
     * "before attribute name" state when it allocates a new entry.
     */
    public ?int $currentAttribute = null;
}
