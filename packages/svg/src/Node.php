<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * Base for every node in the typed SVG tree: elements, text content,
 * comments. Nodes form a parent/child tree rooted at an `SvgDocument`.
 *
 * The tree is built by `Parser` and consumed by `svg-to-pdf` (or by any
 * downstream library — sanitiser, format converter, animation extractor).
 * Mutation is supported (`appendChild` / `removeChild`) for those uses;
 * `Parser` itself never mutates after construction.
 */
abstract class Node
{
    public ?Element $parent = null;
}
