<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mtr>` — table row (MathML Core §3.3.7).
 *
 * Container for {@see Mtd} cells. Only meaningful as a direct child of
 * {@see Mtable}; the painter scans `Mtable`'s typed children for `Mtr`
 * instances to discover row structure.
 *
 * A loose `<mtr>` outside `<mtable>` round-trips through the parser
 * unchanged but the painter walks its children inline.
 *
 * Per-row attribute overrides:
 *
 *   - `columnalign` — overrides the parent table's column alignment for
 *     this row only.
 *   - `rowalign` — overrides this row's vertical alignment.
 */
final class Mtr extends Element
{
    public function __construct()
    {
        parent::__construct('mtr');
    }

    /**
     * Per-column horizontal alignment for cells in THIS row. Same
     * positional-list semantics as {@see Mtable::columnAlign()}; empty
     * list when absent so the painter falls back to the table-level
     * setting.
     *
     * @return list<string>
     */
    public function columnAlign(): array
    {
        $raw = $this->attributes['columnalign'] ?? null;
        if ($raw === null) {
            return [];
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }
        $tokens = preg_split('/\s+/', strtolower($trimmed)) ?: [];
        $out = [];
        foreach ($tokens as $tok) {
            $out[] = match ($tok) {
                'left', 'center', 'right' => $tok,
                default => 'center',
            };
        }
        return $out;
    }

    /**
     * Vertical alignment of THIS row's cells. Single token, lowercase;
     * null when absent so the painter falls back to the table-level
     * `rowalign`.
     */
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
