<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<ms>` — string literal token (MathML Core §3.2.6).
 *
 * Renders surrounded by the `lquote` / `rquote` attribute values
 * (defaults: U+0022 ASCII double quote). Painter handles the quote
 * synthesis; the parser stores the raw character data verbatim.
 */
final class Ms extends Element
{
    public function __construct()
    {
        parent::__construct('ms');
    }

    /** Opening quote character — defaults to `"` when absent. */
    public function lquote(): string
    {
        $raw = $this->attributes['lquote'] ?? null;
        return $raw === null || $raw === '' ? '"' : $raw;
    }

    /** Closing quote character — defaults to `"` when absent. */
    public function rquote(): string
    {
        $raw = $this->attributes['rquote'] ?? null;
        return $raw === null || $raw === '' ? '"' : $raw;
    }
}
