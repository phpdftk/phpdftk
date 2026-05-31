<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `path([<fill-rule>?] <string>)` per CSS Shapes 1 §3.5. Defines
 * a clip / offset region via an SVG path data string. The path
 * string is stored verbatim; the painter parses it through the
 * existing SVG path-grammar parser at render time.
 *
 *   clip-path: path('M 0 0 L 100 100 Z');
 *   offset-path: path('M 0,0 C 50,50 100,0 100,100');
 *   shape-outside: path(evenodd, 'M 0 0 L 100 0 L 0 100 Z');
 */
final readonly class PathShape extends BasicShape
{
    public function __construct(
        public string $fillRule,
        public string $pathData,
    ) {}

    public function toCss(): string
    {
        $head = $this->fillRule === 'nonzero' ? '' : $this->fillRule . ', ';
        // String literal with escaped quotes.
        return 'path(' . $head . '"' . str_replace('"', '\\"', $this->pathData) . '")';
    }
}
