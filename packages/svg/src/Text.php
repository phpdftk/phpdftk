<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * A leaf text node — `#text` data inside an element. Whitespace handling is
 * the caller's responsibility (SVG xml:space="preserve" support is the
 * cascade's job, not the parser's).
 */
final class Text extends Node
{
    public function __construct(public string $data) {}
}
