<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

final class DoctypeToken extends Token
{
    public ?string $name = null;
    public ?string $publicId = null;
    public ?string $systemId = null;
    public bool $forceQuirks = false;
}
