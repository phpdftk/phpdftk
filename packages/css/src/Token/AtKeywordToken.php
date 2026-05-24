<?php

declare(strict_types=1);

namespace Phpdftk\Css\Token;

/** `@media`, `@font-face`, etc. — value excludes the leading '@'. */
final readonly class AtKeywordToken extends Token
{
    public function __construct(public string $value) {}
}
