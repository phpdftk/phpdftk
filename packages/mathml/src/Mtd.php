<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mtd>` — table cell (MathML Core §3.3.7).
 *
 * Container that may hold any presentation-markup subtree (tokens,
 * rows, fractions, scripts, even nested tables). Treated like a
 * transparent `<mrow>` for content, but its bounding box drives the
 * column-width / row-height max in the parent {@see Mtable}'s layout.
 *
 * Per-cell attribute overrides:
 *
 *   - `columnalign` — overrides parent row + table for this cell.
 *   - `rowalign` — overrides parent row + table.
 *
 * The `columnspan` and `rowspan` attributes are not honoured by the
 * v1 painter — cells span exactly one column and one row regardless.
 * They round-trip through the parser.
 */
final class Mtd extends Element
{
    public function __construct()
    {
        parent::__construct('mtd');
    }

    /**
     * Horizontal alignment for THIS cell. Single token, lowercase. Null
     * when absent so the painter falls back to row → table cascade.
     */
    public function columnAlign(): ?string
    {
        $raw = $this->attributes['columnalign'] ?? null;
        if ($raw === null) {
            return null;
        }
        $token = strtolower(trim($raw));
        return match ($token) {
            'left', 'center', 'right' => $token,
            default => null,
        };
    }

    /** Vertical alignment for THIS cell. */
    public function rowAlign(): ?string
    {
        $raw = $this->attributes['rowalign'] ?? null;
        if ($raw === null) {
            return null;
        }
        $token = strtolower(trim($raw));
        return match ($token) {
            'top', 'center', 'bottom', 'baseline', 'axis' => $token,
            default => null,
        };
    }
}
