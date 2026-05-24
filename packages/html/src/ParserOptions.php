<?php

declare(strict_types=1);

namespace Phpdftk\Html;

/**
 * Optional knobs for {@see Parser}. Defaults match the most common
 * print/render use case: scripts off, encoding auto-detected.
 */
final readonly class ParserOptions
{
    public function __construct(
        /**
         * Affects <noscript> handling per WHATWG. When false (default), the
         * contents of <noscript> are parsed normally; when true, the entire
         * <noscript> body becomes a single text node.
         */
        public bool $scriptingEnabled = false,
        /**
         * Override BOM/meta charset detection. When null (default), the
         * tokenizer follows the WHATWG encoding-sniffing algorithm.
         */
        public ?string $assumedEncoding = null,
    ) {}

    public function withScriptingEnabled(bool $enabled): self
    {
        return new self(scriptingEnabled: $enabled, assumedEncoding: $this->assumedEncoding);
    }

    public function withAssumedEncoding(?string $encoding): self
    {
        return new self(scriptingEnabled: $this->scriptingEnabled, assumedEncoding: $encoding);
    }
}
