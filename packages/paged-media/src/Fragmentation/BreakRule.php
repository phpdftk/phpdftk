<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia\Fragmentation;

/**
 * CSS Fragmentation 3 §3 — `break-before`, `break-after`,
 * `break-inside` values, plus their legacy `page-break-*` aliases.
 *
 * `Auto`        — no constraint; the engine breaks where it normally
 *                  would based on remaining space
 * `Avoid`       — try not to break at this position; the engine may
 *                  still break if there's no other choice
 * `Always`      — force a break
 * `AvoidPage`   — try not to break across a *page* boundary
 *                  specifically; column breaks are still allowed
 * `Page`        — force a page break
 * `Left`/`Right`— force a break + skip to a left / right page
 * `Recto`/`Verso`— left/right per writing direction (logical)
 * `AvoidColumn` / `Column` — column-only variants
 *
 * Phase 4G.1 (extraction) maps CSS keyword values into these cases.
 */
enum BreakRule: string
{
    case Auto = 'auto';
    case Avoid = 'avoid';
    case Always = 'always';
    case AvoidPage = 'avoid-page';
    case Page = 'page';
    case Left = 'left';
    case Right = 'right';
    case Recto = 'recto';
    case Verso = 'verso';
    case AvoidColumn = 'avoid-column';
    case Column = 'column';

    /**
     * True for any value that forces a break (`Always`, `Page`,
     * `Left`, `Right`, `Recto`, `Verso`, `Column`).
     */
    public function isForced(): bool
    {
        return match ($this) {
            self::Always, self::Page, self::Left, self::Right,
            self::Recto, self::Verso, self::Column => true,
            default => false,
        };
    }

    /**
     * True for any value that requests the engine avoid a break
     * at this position if possible.
     */
    public function isAvoid(): bool
    {
        return match ($this) {
            self::Avoid, self::AvoidPage, self::AvoidColumn => true,
            default => false,
        };
    }
}
