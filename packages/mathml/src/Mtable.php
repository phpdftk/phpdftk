<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mtable>` — table layout (MathML Core §3.3.7).
 *
 * Children are zero or more {@see Mtr} elements (with {@see Mtd} cells
 * inside). Cells lay out in a 2-D grid:
 *
 *   - Each column's width is `max(cell_widths)` across all rows in the
 *     column.
 *   - Each row's height is `max(cell_heights)` across all cells in the
 *     row. (Tracer-bullet uses a uniform row height = `fontSize`.)
 *   - Cells centre horizontally in their column and align on the math
 *     axis vertically by default.
 *
 * v1 painter scope:
 *
 *   - Arbitrary rows × columns. Ragged rows (fewer cells than the
 *     widest row) leave empty trailing columns.
 *   - Default centre alignment in each column.
 *   - Uniform row height (`fontSize`) — full row-height computation
 *     across nested constructs lands later.
 *   - `columnalign` / `rowalign` / `columnspacing` / `rowspacing` /
 *     `frame` / `framespacing` are NOT honoured yet — parser
 *     round-trips them so a follow-up can without revisiting the typed
 *     class.
 */
final class Mtable extends Element
{
    public function __construct()
    {
        parent::__construct('mtable');
    }
}
