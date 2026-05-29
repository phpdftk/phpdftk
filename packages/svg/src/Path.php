<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

use Phpdftk\Svg\Path\PathData;

/**
 * SVG `<path>` element per SVG 2 §9. The geometry lives in the `d` attribute;
 * call `d()` to get a typed `PathData` AST or `dRaw()` for the original
 * string (round-tripping, diagnostics).
 */
final class Path extends Element
{
    public function __construct()
    {
        parent::__construct('path');
    }

    /**
     * The original `d` attribute value, verbatim. Useful for sanitiser-style
     * workflows that want to compare or re-emit without parsing.
     */
    public function dRaw(): ?string
    {
        return $this->getAttribute('d');
    }

    /**
     * Parsed `d` attribute. Always returns a `PathData` — an absent or
     * malformed attribute resolves to an empty command list (the spec
     * accumulates commands up to the first error).
     */
    public function d(): PathData
    {
        $raw = $this->getAttribute('d');
        if ($raw === null) {
            return new PathData([]);
        }
        return PathData::parse($raw);
    }
}
