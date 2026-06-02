<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §6.4 — `<metadata>` element. Carries arbitrary RDF/XML
 * or other namespaced metadata. Never renders directly; the
 * typed class lets the Translator skip the element explicitly
 * and downstream tooling (sanitisers, optimisers) recognise it.
 */
final class Metadata extends Element
{
    public function __construct()
    {
        parent::__construct('metadata');
    }
}
