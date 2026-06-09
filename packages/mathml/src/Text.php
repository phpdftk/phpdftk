<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * Text content node. MathML Core puts character data inside token
 * elements (`<mn>`, `<mi>`, `<mo>`, `<ms>`, `<mtext>`); the parser
 * preserves the data verbatim — no whitespace collapse — so a
 * fraction-bar `<mo>/</mo>` and an identifier `<mi>x</mi>` both
 * survive round-tripping intact.
 */
final class Text extends Node
{
    public function __construct(public string $data) {}
}
