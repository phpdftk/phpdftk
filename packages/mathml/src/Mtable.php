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
 *     axis vertically by default; `columnalign` / `rowalign` override.
 *
 * v1 painter scope:
 *
 *   - Arbitrary rows × columns.
 *   - Per-column alignment via `columnalign` (`left | center | right`).
 *   - Per-row alignment via `rowalign` (`top | center | bottom |
 *     baseline | axis`).
 *   - Per-column spacing via `columnspacing` (CSS length list).
 *   - Per-row spacing via `rowspacing` (CSS length list).
 *
 * Round-tripped but NOT honoured yet: `frame`, `framespacing`, per-cell
 * `columnspan`, `rowspan`, the `displaystyle` propagation.
 */
final class Mtable extends Element
{
    public function __construct()
    {
        parent::__construct('mtable');
    }

    /**
     * Per-column horizontal alignment as a positional list, lowercased.
     * Each entry is one of `left`, `center`, `right`. Unknown tokens
     * fall to `center`. When the column count exceeds the list length,
     * the last entry repeats (per Core).
     *
     * Returns an empty list when the attribute is absent so the painter
     * applies the default (`center` everywhere).
     *
     * @return list<string>
     */
    public function columnAlign(): array
    {
        return $this->parseAlignList(
            $this->attributes['columnalign'] ?? null,
            ['left', 'center', 'right'],
            'center',
        );
    }

    /**
     * Per-row vertical alignment. `top | center | bottom | baseline |
     * axis`. Same length-extension rules as {@see columnAlign()}.
     *
     * @return list<string>
     */
    public function rowAlign(): array
    {
        return $this->parseAlignList(
            $this->attributes['rowalign'] ?? null,
            ['top', 'center', 'bottom', 'baseline', 'axis'],
            'axis',
        );
    }

    /**
     * Per-column gap in em (left of column N+1). Default `0.8em` per
     * Core's `0.8em` muskip default for column spacing. Unknown / non-em
     * units fall back to the default. Returns an empty list when the
     * attribute is absent so the painter uses its uniform default.
     *
     * @return list<float>
     */
    public function columnSpacingEm(): array
    {
        return $this->parseLengthListEm(
            $this->attributes['columnspacing'] ?? null,
            defaultEm: 0.8,
        );
    }

    /**
     * Per-row gap in em (above row N+1). Default `1.0ex ≈ 0.5em`.
     *
     * @return list<float>
     */
    public function rowSpacingEm(): array
    {
        return $this->parseLengthListEm(
            $this->attributes['rowspacing'] ?? null,
            defaultEm: 0.5,
        );
    }

    /**
     * @param list<string> $allowed
     * @return list<string>
     */
    private function parseAlignList(
        ?string $raw,
        array $allowed,
        string $fallback,
    ): array {
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
            $out[] = in_array($tok, $allowed, strict: true) ? $tok : $fallback;
        }
        return $out;
    }

    /**
     * @return list<float>
     */
    private function parseLengthListEm(?string $raw, float $defaultEm): array
    {
        if ($raw === null) {
            return [];
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }
        $tokens = preg_split('/\s+/', $trimmed) ?: [];
        $out = [];
        foreach ($tokens as $tok) {
            if ($tok === '') {
                continue;
            }
            if (!preg_match('/^(-?\d*\.?\d+)\s*([a-zA-Z%]*)$/', $tok, $m)) {
                $out[] = $defaultEm;
                continue;
            }
            $value = (float) $m[1];
            $unit = strtolower($m[2]);
            $em = match ($unit) {
                'em', ''  => $value,
                'ex'      => $value * 0.5,
                'px'      => $value / 16.0,
                'pt'      => $value / 12.0,
                default   => null,
            };
            $out[] = $em ?? $defaultEm;
        }
        return $out;
    }
}
