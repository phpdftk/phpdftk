<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * Base for every node in the typed MathML tree: elements, text content.
 * Nodes form a parent/child tree rooted at a {@see MathmlDocument}.
 *
 * Mirrors the {@see \Phpdftk\Svg\Node} shape so callers that walk the
 * SVG and MathML trees can share traversal code paths.
 */
abstract class Node
{
    public ?Element $parent = null;
}
