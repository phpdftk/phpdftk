<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * An element from a namespace other than SVG that appeared inside an
 * SVG document — an XHTML `<div>`, a `<h:link rel="help">`, an RDF
 * node in `<metadata>`.
 *
 * SVG 2 §5.7 (Foreign namespaces and private data): such an element
 * and its DESCENDANTS are not rendered. Keeping them as a distinct
 * type rather than a `GenericElement` is what lets the painter skip
 * the whole subtree — without it, an `<svg>` nested inside an XHTML
 * wrapper reached the painter through the generic
 * "recurse into children" arm and painted.
 *
 * (Content inside `<foreignObject>` is the documented exception and is
 * modelled by {@see ForeignObject}, not by this class.)
 */
final class ForeignElement extends Element
{
    public function __construct(string $localName, public readonly ?string $namespaceUri = null)
    {
        parent::__construct($localName);
    }
}
