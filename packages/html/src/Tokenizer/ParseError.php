<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

/**
 * A tokenizer-level parse error per WHATWG §13.2.5. WHATWG mandates that
 * parsing always succeeds — every parse error is recoverable. Errors are
 * collected on the tokenizer and exposed to the caller for diagnostics.
 */
final readonly class ParseError
{
    public function __construct(
        public ParseErrorCode $code,
        public int $position,
    ) {}
}
