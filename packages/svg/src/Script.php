<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §15.2 — `<script>` element. Carries inline or external
 * script content. Out of scope for static print rendering AND
 * a security concern (per §17.3 the parser must not execute it);
 * lifted to a typed class so the Translator can route it
 * explicitly to "skip entirely" instead of recursing into any
 * `<text>` etc. children it happens to contain.
 *
 * The element exposes no execution accessors — it's purely a
 * skip-marker.
 */
final class Script extends Element
{
    public function __construct()
    {
        parent::__construct('script');
    }
}
