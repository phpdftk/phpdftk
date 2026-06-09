<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mphantom>` — invisible spacing (MathML Core §3.3.6.5).
 *
 * Wraps a child element and renders it as if it were drawn — same
 * width, same height — but without emitting glyphs. The classic use
 * is forcing alignment across equation lines: `<mphantom>X</mphantom>`
 * reserves the same horizontal space as `<mi>X</mi>` without
 * visually rendering X.
 *
 * Painter scope: the v1 painter advances the cursor by the children's
 * estimated width but skips text emission entirely (no `Tj`). Path
 * elements (fraction bars, vinculums) under `<mphantom>` would still
 * draw under a strict reading of the spec, but the v1 painter
 * suppresses those too — pure space reservation. The follow-up that
 * adds a "muted ink" mode can render paths under a no-op text mode.
 */
final class Mphantom extends Element
{
    public function __construct()
    {
        parent::__construct('mphantom');
    }
}
