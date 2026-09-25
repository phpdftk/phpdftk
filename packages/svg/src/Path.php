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
     * Parsed path geometry. Always returns a `PathData` — an absent or
     * malformed source resolves to an empty command list (the spec
     * accumulates commands up to the first error).
     *
     * SVG 2 §9.3 makes `d` a CSS property as well as a presentation
     * attribute, with the grammar `none | path(<string>)`, and the CSS
     * side WINS: §6.7 puts a presentation attribute at specificity 0,
     * so any rule naming the element out-ranks it. That is why this
     * consults the `style` declaration first and the attribute only as
     * the fallback — the opposite of every other accessor here.
     *
     * An unparseable declaration is ignored rather than fatal, which
     * per the cascade means the attribute is still in force.
     */
    public function d(): PathData
    {
        $property = self::parseDProperty($this->styleProperty('d'));
        if ($property !== null) {
            return PathData::parse($property);
        }
        $raw = $this->getAttribute('d');
        if ($raw === null) {
            return new PathData([]);
        }
        return PathData::parse($raw);
    }

    /**
     * The path string named by a CSS `d` declaration, or null when the
     * declaration is absent or unusable.
     *
     * `none` returns the empty string — an explicit "no geometry",
     * which is NOT the same as "no declaration" and must not fall
     * through to the attribute.
     */
    private static function parseDProperty(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $value = trim($raw);
        if ($value === '') {
            return null;
        }
        if (strcasecmp($value, 'none') === 0) {
            return '';
        }
        if (preg_match('/^path\(\s*(.*?)\s*\)$/is', $value, $m) !== 1) {
            return null;
        }
        $argument = $m[1];
        // `path()` takes an optional fill-rule keyword before the
        // string; the geometry is the quoted part either way.
        if (preg_match('/[\x22\x27](.*)[\x22\x27]\s*$/s', $argument, $q) !== 1) {
            return null;
        }
        return $q[1];
    }
}
