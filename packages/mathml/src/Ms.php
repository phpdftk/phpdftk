<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<ms>` — string literal token (MathML Core §3.2.6).
 *
 * Renders surrounded by a fixed U+0022 ASCII double quote on each
 * side — MathML Core specifies that through the UA stylesheet rules
 * `ms::before, ms::after { content: "\"" }`, which no attribute can
 * reach. The painter synthesises the quotes; the parser stores the
 * raw character data verbatim.
 *
 * The legacy MathML 3 `lquote` / `rquote` attributes were DROPPED in
 * Core and have no effect; {@see QUOTE} is what renders. The
 * accessors below are retained only so documents that carry the
 * legacy attributes can still be inspected.
 */
final class Ms extends Element
{
    /** The quote character `<ms>` renders on both sides. */
    public const string QUOTE = '"';

    public function __construct()
    {
        parent::__construct('ms');
    }

    /**
     * Raw legacy `lquote` attribute, or null when absent.
     *
     * NOT consulted when rendering — see the class docblock.
     */
    public function lquote(): ?string
    {
        $raw = $this->attributes['lquote'] ?? null;
        return $raw === null || $raw === '' ? null : $raw;
    }

    /**
     * Raw legacy `rquote` attribute, or null when absent.
     *
     * NOT consulted when rendering — see the class docblock.
     */
    public function rquote(): ?string
    {
        $raw = $this->attributes['rquote'] ?? null;
        return $raw === null || $raw === '' ? null : $raw;
    }
}
