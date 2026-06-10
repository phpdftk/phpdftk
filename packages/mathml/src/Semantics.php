<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<semantics>` — presentation + annotation wrapper (MathML Core
 * §5.1).
 *
 * Carries one presentation child (the first element child) plus
 * any number of `<annotation>` / `<annotation-xml>` siblings.
 * Renderers display only the presentation child; the annotations
 * exist to carry alternate encodings (Content MathML, TeX
 * source, etc.) for consumers that want them.
 *
 * The element has no rendering attributes of its own; painters
 * walk the first element child as if `<semantics>` were
 * transparent.
 */
final class Semantics extends Element
{
    public function __construct()
    {
        parent::__construct('semantics');
    }
}
