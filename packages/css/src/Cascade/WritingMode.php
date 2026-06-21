<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Value;

/**
 * CSS Writing Modes 4 — converts the cascaded `writing-mode` /
 * `direction` properties into a physical axis mapping so layout
 * code can ask "which physical axis is the block axis?" without
 * encoding the horizontal-tb assumption everywhere.
 *
 * The four shipped writing modes:
 *  - `horizontal-tb` — block flows top-to-bottom along Y; inline
 *    flows left-to-right (`direction: ltr`) or right-to-left
 *    (`direction: rtl`) along X. The default for HTML.
 *  - `vertical-rl` — block flows right-to-left along X; inline
 *    flows top-to-bottom along Y. Used for traditional CJK
 *    layouts. Inline `direction: rtl` reverses the Y direction.
 *  - `vertical-lr` — block flows left-to-right along X; inline
 *    flows top-to-bottom along Y. Mongolian-style.
 *  - `sideways-rl` / `sideways-lr` — same physical block axis as
 *    the vertical-* variants, but text glyphs rotate sideways.
 *    (Distinction matters for the painter; the resolver maps the
 *    same physical axes.)
 *
 * Returns physical-axis tuples so layout can map:
 *   - inline-start / inline-end / block-start / block-end →
 *     left / right / top / bottom per the active mode.
 *   - margin/padding/inset *-block-* and *-inline-* longhands.
 *   - Block-progression direction (cursor advance per child).
 *   - Inline-progression direction (per-character / per-glyph
 *     advance, including bidi flip under `direction: rtl`).
 */
final readonly class WritingMode
{
    public const HORIZONTAL_TB = 'horizontal-tb';
    public const VERTICAL_RL = 'vertical-rl';
    public const VERTICAL_LR = 'vertical-lr';
    public const SIDEWAYS_RL = 'sideways-rl';
    public const SIDEWAYS_LR = 'sideways-lr';

    public function __construct(
        public string $mode = self::HORIZONTAL_TB,
        public string $direction = 'ltr',
    ) {}

    /** Block-axis flows along x (vertical modes) or y (horizontal). */
    public function blockAxis(): string
    {
        return $this->isVertical() ? 'x' : 'y';
    }

    /** Inline-axis flows along the OTHER physical axis. */
    public function inlineAxis(): string
    {
        return $this->isVertical() ? 'y' : 'x';
    }

    /**
     * `+1` when the block axis progresses in the positive physical
     * direction (down for horizontal-tb, right for vertical-lr) or
     * `-1` for the reverse (right→left for vertical-rl / sideways-rl).
     */
    public function blockDirection(): int
    {
        return match ($this->mode) {
            self::VERTICAL_RL, self::SIDEWAYS_RL => -1,
            default => 1, // horizontal-tb, vertical-lr, sideways-lr
        };
    }

    /**
     * `+1` for ltr inline progression along its physical axis; `-1`
     * for rtl. For vertical modes the inline axis is y; rtl makes
     * inlines flow bottom→top.
     */
    public function inlineDirection(): int
    {
        return $this->direction === 'rtl' ? -1 : 1;
    }

    public function isVertical(): bool
    {
        return $this->mode !== self::HORIZONTAL_TB;
    }

    public function isHorizontal(): bool
    {
        return $this->mode === self::HORIZONTAL_TB;
    }

    public function isSideways(): bool
    {
        return $this->mode === self::SIDEWAYS_RL || $this->mode === self::SIDEWAYS_LR;
    }

    /**
     * Resolve `block-start` / `block-end` / `inline-start` /
     * `inline-end` to one of `top` / `right` / `bottom` / `left`
     * per the active mode + direction. Used by layout code that
     * needs to read `margin-block-start` and apply it as a physical
     * margin edge.
     */
    public function physicalEdge(string $logicalEdge): string
    {
        return match ([$this->mode, $this->direction, $logicalEdge]) {
            // horizontal-tb
            [self::HORIZONTAL_TB, 'ltr', 'block-start'], [self::HORIZONTAL_TB, 'rtl', 'block-start'] => 'top',
            [self::HORIZONTAL_TB, 'ltr', 'block-end'],   [self::HORIZONTAL_TB, 'rtl', 'block-end']   => 'bottom',
            [self::HORIZONTAL_TB, 'ltr', 'inline-start']                                              => 'left',
            [self::HORIZONTAL_TB, 'ltr', 'inline-end']                                                => 'right',
            [self::HORIZONTAL_TB, 'rtl', 'inline-start']                                              => 'right',
            [self::HORIZONTAL_TB, 'rtl', 'inline-end']                                                => 'left',
            // vertical-rl / sideways-rl
            [self::VERTICAL_RL, 'ltr', 'block-start'], [self::VERTICAL_RL, 'rtl', 'block-start'],
            [self::SIDEWAYS_RL, 'ltr', 'block-start'], [self::SIDEWAYS_RL, 'rtl', 'block-start'] => 'right',
            [self::VERTICAL_RL, 'ltr', 'block-end'],   [self::VERTICAL_RL, 'rtl', 'block-end'],
            [self::SIDEWAYS_RL, 'ltr', 'block-end'],   [self::SIDEWAYS_RL, 'rtl', 'block-end']   => 'left',
            [self::VERTICAL_RL, 'ltr', 'inline-start'],
            [self::SIDEWAYS_RL, 'ltr', 'inline-start'] => 'top',
            [self::VERTICAL_RL, 'ltr', 'inline-end'],
            [self::SIDEWAYS_RL, 'ltr', 'inline-end']   => 'bottom',
            [self::VERTICAL_RL, 'rtl', 'inline-start'],
            [self::SIDEWAYS_RL, 'rtl', 'inline-start'] => 'bottom',
            [self::VERTICAL_RL, 'rtl', 'inline-end'],
            [self::SIDEWAYS_RL, 'rtl', 'inline-end']   => 'top',
            // vertical-lr / sideways-lr
            [self::VERTICAL_LR, 'ltr', 'block-start'], [self::VERTICAL_LR, 'rtl', 'block-start'],
            [self::SIDEWAYS_LR, 'ltr', 'block-start'], [self::SIDEWAYS_LR, 'rtl', 'block-start'] => 'left',
            [self::VERTICAL_LR, 'ltr', 'block-end'],   [self::VERTICAL_LR, 'rtl', 'block-end'],
            [self::SIDEWAYS_LR, 'ltr', 'block-end'],   [self::SIDEWAYS_LR, 'rtl', 'block-end']   => 'right',
            [self::VERTICAL_LR, 'ltr', 'inline-start'],
            [self::SIDEWAYS_LR, 'ltr', 'inline-start'] => 'top',
            [self::VERTICAL_LR, 'ltr', 'inline-end'],
            [self::SIDEWAYS_LR, 'ltr', 'inline-end']   => 'bottom',
            [self::VERTICAL_LR, 'rtl', 'inline-start'],
            [self::SIDEWAYS_LR, 'rtl', 'inline-start'] => 'bottom',
            [self::VERTICAL_LR, 'rtl', 'inline-end'],
            [self::SIDEWAYS_LR, 'rtl', 'inline-end']   => 'top',
            default => 'top', // unreachable
        };
    }

    /**
     * Resolve the `writing-mode` + `direction` properties from a
     * CascadedValues into a WritingMode instance. Unknown keywords
     * fall back to the spec initial values.
     */
    public static function fromStyle(CascadedValues $style): self
    {
        $modeValue = $style->get('writing-mode');
        $mode = self::HORIZONTAL_TB;
        if ($modeValue instanceof Keyword) {
            $name = strtolower($modeValue->name);
            if (in_array(
                $name,
                [self::HORIZONTAL_TB, self::VERTICAL_RL, self::VERTICAL_LR, self::SIDEWAYS_RL, self::SIDEWAYS_LR],
                true,
            )) {
                $mode = $name;
            }
        }
        $directionValue = $style->get('direction');
        $direction = 'ltr';
        if ($directionValue instanceof Keyword) {
            $name = strtolower($directionValue->name);
            if ($name === 'rtl' || $name === 'ltr') {
                $direction = $name;
            }
        }
        return new self($mode, $direction);
    }

    public static function fromValues(?Value $writingMode, ?Value $direction): self
    {
        $mode = self::HORIZONTAL_TB;
        if ($writingMode instanceof Keyword) {
            $name = strtolower($writingMode->name);
            if (in_array(
                $name,
                [self::HORIZONTAL_TB, self::VERTICAL_RL, self::VERTICAL_LR, self::SIDEWAYS_RL, self::SIDEWAYS_LR],
                true,
            )) {
                $mode = $name;
            }
        }
        $dir = 'ltr';
        if ($direction instanceof Keyword) {
            $name = strtolower($direction->name);
            if ($name === 'rtl' || $name === 'ltr') {
                $dir = $name;
            }
        }
        return new self($mode, $dir);
    }
}
