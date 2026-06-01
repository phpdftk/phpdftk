<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\ListSeparator;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\ValueList;

/**
 * Per CSS Cascade 5 §3.2, shorthand declarations expand into their longhand
 * components before the cascade picks winners. This class implements the
 * structural box-edge shorthands needed by Phase 1F layout:
 *
 *  - `margin` / `padding` / `border-width` / `border-style` /
 *    `border-color` — four-sided variants.
 *  - `border-top` / `border-right` / `border-bottom` / `border-left` —
 *    composite per-side shorthand (width / style / color in any order).
 *  - `border` — combines `border-{width,style,color}` for all four sides.
 *
 * Shorthand value-list rules (CSS Backgrounds 3 §3.1 / CSS Box 3):
 *  - 1 value: applied to all four sides.
 *  - 2 values: top/bottom, left/right.
 *  - 3 values: top, left/right, bottom.
 *  - 4 values: top, right, bottom, left (clockwise from top).
 *
 * Unknown shorthands fall through unchanged so the cascade can still match
 * declarations the registry doesn't know about — they just don't decompose.
 */
final class ShorthandExpander
{
    /**
     * Expand `$property = $value`. Returns the resulting longhand map; the
     * shorthand name is intentionally omitted so the cascade doesn't keep
     * tracking it alongside its longhands.
     *
     * @return array<string, Value>
     */
    public function expand(string $property, Value $value): array
    {
        $name = strtolower($property);
        return match ($name) {
            'margin' => $this->expandFourSided('margin', $value, ['top', 'right', 'bottom', 'left']),
            'padding' => $this->expandFourSided('padding', $value, ['top', 'right', 'bottom', 'left']),
            'border-width' => $this->expandFourSided('border', $value, ['top-width', 'right-width', 'bottom-width', 'left-width']),
            'border-style' => $this->expandFourSided('border', $value, ['top-style', 'right-style', 'bottom-style', 'left-style']),
            'border-color' => $this->expandFourSided('border', $value, ['top-color', 'right-color', 'bottom-color', 'left-color']),
            // CSS Backgrounds 3 §6: `border-radius` expands like `margin`
            // but the corner suffix order is TL TR BR BL (clockwise from
            // top-left), and the horizontal/vertical pair `/` form is
            // ignored — Phase 1 only honours the symmetrical value.
            'border-radius' => $this->expandFourSided(
                'border',
                $this->stripSlashTail($value),
                ['top-left-radius', 'top-right-radius', 'bottom-right-radius', 'bottom-left-radius'],
            ),
            'border-top', 'border-right', 'border-bottom', 'border-left'
                => $this->expandBorderSide($name, $value),
            // CSS Logical Properties 1 §7 — `border-block` / `-inline`
            // shorthand both sides of the axis, each a three-slot
            // border (width || style || color) shorthand.
            'border-block' => $this->expandBorderAxis($value, 'block'),
            'border-inline' => $this->expandBorderAxis($value, 'inline'),
            'border-block-start', 'border-block-end',
            'border-inline-start', 'border-inline-end'
                => $this->expandBorderLogicalSide($name, $value),
            'border' => $this->expandBorder($value),
            'outline' => $this->expandOutline($value),
            'font' => $this->expandFont($value),
            'text-decoration' => $this->expandTextDecoration($value),
            'background' => $this->expandBackground($value),
            'list-style' => $this->expandListStyle($value),
            'columns' => $this->expandColumns($value),
            'column-rule' => $this->expandColumnRule($value),
            'gap' => $this->expandGap($value),
            'inset' => $this->expandInset($value),
            // CSS Logical Properties 1 §5 / §6 — axis pair
            // shorthands.
            'inset-block' => $this->expandLogicalPair('inset-block', $value, ['start', 'end']),
            'inset-inline' => $this->expandLogicalPair('inset-inline', $value, ['start', 'end']),
            'margin-block' => $this->expandLogicalPair('margin-block', $value, ['start', 'end']),
            'margin-inline' => $this->expandLogicalPair('margin-inline', $value, ['start', 'end']),
            'padding-block' => $this->expandLogicalPair('padding-block', $value, ['start', 'end']),
            'padding-inline' => $this->expandLogicalPair('padding-inline', $value, ['start', 'end']),
            'overflow' => $this->expandOverflow($value),
            'flex' => $this->expandFlex($value),
            'flex-flow' => $this->expandFlexFlow($value),
            'grid-column' => $this->expandGridLine($value, 'column'),
            'grid-row' => $this->expandGridLine($value, 'row'),
            'grid-area' => $this->expandGridArea($value),
            // CSS Box Alignment 3 §8 — `place-*` shorthands. Single
            // value applies to both axes; two values map first →
            // align (block-axis), second → justify (inline-axis).
            'place-items' => $this->expandPlaceShorthand($value, 'align-items', 'justify-items'),
            'place-content' => $this->expandPlaceShorthand($value, 'align-content', 'justify-content'),
            'place-self' => $this->expandPlaceShorthand($value, 'align-self', 'justify-self'),
            'transition' => $this->expandTransition($value),
            'animation' => $this->expandAnimation($value),
            'position-try' => $this->expandPositionTry($value),
            'text-emphasis' => $this->expandTextEmphasis($value),
            'mask' => $this->expandMask($value),
            'border-image' => $this->expandBorderImage($value),
            'mask-border' => $this->expandMaskBorder($value),
            // Legacy property aliases — these write to BOTH the
            // alias and the modern target so author CSS that still
            // uses the old name keeps working alongside the new one.
            'page-break-before' => $this->alias($property, $value, 'break-before'),
            'page-break-after' => $this->alias($property, $value, 'break-after'),
            'page-break-inside' => $this->alias($property, $value, 'break-inside'),
            'inset-area' => $this->alias($property, $value, 'position-area'),
            'word-wrap' => $this->alias($property, $value, 'overflow-wrap'),
            'grid-gap' => [...$this->expandGap($value), 'grid-gap' => $value],
            'grid-row-gap' => $this->alias($property, $value, 'row-gap'),
            'grid-column-gap' => $this->alias($property, $value, 'column-gap'),
            'text-wrap' => $this->expandTextWrap($value),
            'white-space' => $this->expandWhiteSpace($value),
            'caret' => $this->expandCaret($value),
            'font-synthesis' => $this->expandFontSynthesis($value),
            'font-variant' => $this->expandFontVariant($value),
            default => [$property => $value],
        };
    }

    /**
     * Expand a `<prefix>: <start> [<end>]` logical-pair shorthand
     * into `<prefix>-start` / `<prefix>-end`. One value applies
     * to both sides; two values map first → start, second → end
     * per CSS Logical Properties 1 §5.
     *
     * @param list<string> $suffixes
     * @return array<string, Value>
     */
    private function expandLogicalPair(string $prefix, Value $value, array $suffixes): array
    {
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        $start = $components[0];
        $end = $components[1] ?? $start;
        return [
            $prefix . '-' . $suffixes[0] => $start,
            $prefix . '-' . $suffixes[1] => $end,
        ];
    }

    /**
     * CSS Box Alignment 3 §8.3 — `place-items` / `place-content` /
     * `place-self`. One value applies to both axes; two values map
     * first → block-axis (`align-*`), second → inline-axis
     * (`justify-*`).
     *
     * @return array<string, Value>
     */
    private function expandPlaceShorthand(Value $value, string $alignProp, string $justifyProp): array
    {
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        $align = $components[0];
        $justify = $components[1] ?? $align;
        return [
            $alignProp => $align,
            $justifyProp => $justify,
        ];
    }

    /**
     * CSS Grid Layout 2 §8.5 — `grid-area` shorthand. Three forms:
     *  - Single `<custom-ident>` (any non-`auto` keyword) — propagates
     *    to all four longhands as a name reference. Layout resolves
     *    the name against the grid container's `grid-template-areas`
     *    map; falls back to `auto` when the name doesn't exist.
     *  - Single `<integer>` — applies to `grid-row-start` only; the
     *    other three default to `auto`.
     *  - Slash-separated 2-, 3-, or 4-value form — fills row-start /
     *    column-start / row-end / column-end. Omitted positions
     *    mirror the spec's "missing → matching opposite or auto"
     *    rule (Phase-2 simplification: missing → `auto`).
     *
     * @return array<string, Value>
     */
    private function expandGridArea(Value $value): array
    {
        $autoKey = new \Phpdftk\Css\Value\Keyword('auto');
        // Single-value forms.
        if (!($value instanceof \Phpdftk\Css\Value\ValueList)
            || $value->separator !== \Phpdftk\Css\Value\ListSeparator::Slash
        ) {
            if ($value instanceof \Phpdftk\Css\Value\Keyword
                && strtolower($value->name) !== 'auto'
                && strtolower($value->name) !== 'none'
            ) {
                // Custom-ident name — propagate to all four sides.
                return [
                    'grid-row-start' => $value,
                    'grid-column-start' => $value,
                    'grid-row-end' => $value,
                    'grid-column-end' => $value,
                ];
            }
            // Bare integer / `auto` — only row-start gets the value.
            return [
                'grid-row-start' => $value,
                'grid-column-start' => $autoKey,
                'grid-row-end' => $autoKey,
                'grid-column-end' => $autoKey,
            ];
        }
        // Slash-separated list — positional mapping.
        $vs = $value->values;
        return [
            'grid-row-start' => $vs[0] ?? $autoKey,
            'grid-column-start' => $vs[1] ?? $autoKey,
            'grid-row-end' => $vs[2] ?? $autoKey,
            'grid-column-end' => $vs[3] ?? $autoKey,
        ];
    }

    /**
     * CSS Grid Layout 2 §8.3.1 — `grid-column` / `grid-row` shorthand.
     * Accepts `<start>` (end omitted, defaults to auto) or
     * `<start> / <end>`. Numeric start/end are kept as `Integer`;
     * `auto` falls through as a Keyword. The `span N` syntax is
     * deferred (Phase-2 follow-up — span support requires the layout
     * to grow the implicit grid).
     *
     * @return array<string, Value>
     */
    private function expandGridLine(Value $value, string $axis): array
    {
        $startKey = "grid-{$axis}-start";
        $endKey = "grid-{$axis}-end";
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Slash
            && count($value->values) >= 2
        ) {
            return [
                $startKey => $value->values[0],
                $endKey => $value->values[1],
            ];
        }
        return [
            $startKey => $value,
            $endKey => new \Phpdftk\Css\Value\Keyword('auto'),
        ];
    }

    /**
     * `margin: 10px;` → margin-top/right/bottom/left = 10px
     * `margin: 10px 5px;` → top/bottom = 10px, left/right = 5px
     * `margin: 10px 5px 20px;` → top = 10, right/left = 5, bottom = 20
     * `margin: 10px 5px 20px 0;` → t r b l
     *
     * @param array{0:string, 1:string, 2:string, 3:string} $suffixes
     * @return array<string, Value>
     */
    private function expandFourSided(string $prefix, Value $value, array $suffixes): array
    {
        $components = $this->toComponents($value);
        $count = count($components);
        if ($count === 0) {
            return [];
        }
        [$top, $right, $bottom, $left] = match ($count) {
            1 => [$components[0], $components[0], $components[0], $components[0]],
            2 => [$components[0], $components[1], $components[0], $components[1]],
            3 => [$components[0], $components[1], $components[2], $components[1]],
            default => [$components[0], $components[1], $components[2], $components[3]],
        };
        return [
            $prefix . '-' . $suffixes[0] => $top,
            $prefix . '-' . $suffixes[1] => $right,
            $prefix . '-' . $suffixes[2] => $bottom,
            $prefix . '-' . $suffixes[3] => $left,
        ];
    }

    /**
     * `border-top: 1px solid red` → expands to border-top-width, -style, -color.
     * Components can appear in any order; missing fields default to the
     * spec's initial value (`medium` for width, `none` for style, `currentcolor`
     * for color) but we leave the omitted longhands out so the registry's
     * initial values apply.
     *
     * @return array<string, Value>
     */
    private function expandBorderSide(string $shorthand, Value $value): array
    {
        // shorthand is e.g. "border-top".
        $side = substr($shorthand, strlen('border-'));
        $components = $this->toComponents($value);
        return $this->classifyBorderComponents($components, [$side]);
    }

    /** @return array<string, Value> */
    private function expandBorder(Value $value): array
    {
        $components = $this->toComponents($value);
        return $this->classifyBorderComponents($components, ['top', 'right', 'bottom', 'left']);
    }

    /**
     * `outline: 2px solid red` → outline-width, outline-style, outline-color.
     * Free-order components like border-side.
     *
     * @return array<string, Value>
     */
    private function expandOutline(Value $value): array
    {
        $components = $this->toComponents($value);
        $width = null;
        $style = null;
        $color = null;
        foreach ($components as $c) {
            if ($style === null && $this->looksLikeBorderStyle($c)) {
                $style = $c;
                continue;
            }
            if ($width === null && $this->looksLikeBorderWidth($c)) {
                $width = $c;
                continue;
            }
            if ($color === null && $this->isColorComponent($c)) {
                $color = $c;
                continue;
            }
            // CSS Basic UI 4 §3.3 — `outline-color: invert` is the
            // legacy CSS 2.1 keyword that requests xor-blending
            // against the underlying pixels. Print medium can't
            // implement it; the cascade preserves it so author CSS
            // round-trips.
            if ($color === null && $c instanceof Keyword
                && strtolower($c->name) === 'invert'
            ) {
                $color = $c;
            }
        }
        $out = [];
        if ($width !== null) {
            $out['outline-width'] = $width;
        }
        if ($style !== null) {
            $out['outline-style'] = $style;
        }
        if ($color !== null) {
            $out['outline-color'] = $color;
        }
        return $out;
    }

    /**
     * Map free-order `<width> <style> <color>` components onto every side
     * listed in `$sides`. Order-agnostic per CSS Backgrounds 3 §3.1.
     *
     * @param list<Value> $components
     * @param list<string> $sides
     * @return array<string, Value>
     */
    private function classifyBorderComponents(array $components, array $sides): array
    {
        $width = null;
        $style = null;
        $color = null;
        foreach ($components as $c) {
            if ($style === null && $this->looksLikeBorderStyle($c)) {
                $style = $c;
                continue;
            }
            if ($width === null && $this->looksLikeBorderWidth($c)) {
                $width = $c;
                continue;
            }
            if ($color === null && $this->looksLikeColor($c)) {
                $color = $c;
                continue;
            }
        }
        $out = [];
        foreach ($sides as $side) {
            if ($width !== null) {
                $out["border-$side-width"] = $width;
            }
            if ($style !== null) {
                $out["border-$side-style"] = $style;
            }
            if ($color !== null) {
                $out["border-$side-color"] = $color;
            }
        }
        return $out;
    }

    /** @return list<Value> */
    private function toComponents(Value $value): array
    {
        if ($value instanceof ValueList) {
            return $value->values;
        }
        return [$value];
    }

    /**
     * `border-radius: 5px 10px / 8px 12px` — Phase 1 ignores the second
     * (vertical) radius set after `/`; drop everything past the slash so
     * the horizontal radii feed `expandFourSided`.
     */
    private function stripSlashTail(Value $value): Value
    {
        if (!($value instanceof ValueList)) {
            return $value;
        }
        if ($value->separator !== \Phpdftk\Css\Value\ListSeparator::Slash) {
            return $value;
        }
        // The slash-separated outer list has the horizontal radii as its
        // first item; keep only that. The horizontal-radii item may itself
        // be a Space ValueList.
        $first = $value->values[0] ?? $value;
        return $first;
    }

    private function looksLikeBorderWidth(Value $v): bool
    {
        if ($v instanceof \Phpdftk\Css\Value\Length) {
            return true;
        }
        if ($v instanceof \Phpdftk\Css\Value\Keyword) {
            return in_array(strtolower($v->name), ['thin', 'medium', 'thick'], true);
        }
        return false;
    }

    private function looksLikeBorderStyle(Value $v): bool
    {
        if (!$v instanceof \Phpdftk\Css\Value\Keyword) {
            return false;
        }
        return in_array(strtolower($v->name), [
            'none', 'hidden', 'dotted', 'dashed', 'solid',
            'double', 'groove', 'ridge', 'inset', 'outset',
        ], true);
    }

    private function looksLikeColor(Value $v): bool
    {
        // Accept typed Color values plus the keyword forms the
        // shorthand grammar permits everywhere a `<color>` appears
        // (`currentcolor`, `transparent`). Other Keyword tokens
        // remain candidates for their own slots (border-style,
        // -width, etc.) — `isColorComponent` whitelists the two
        // color-bearing keywords specifically.
        return $this->isColorComponent($v);
    }

    /**
     * CSS Fonts 4 §6.7: `font: [<style> || <variant> || <weight> || <stretch>]?
     * <size> [/ <line-height>]? <family>`.
     *
     * The input value can carry three nested structures depending on whether
     * comma-separated families and a slash-separated line-height are present:
     *
     *  - bare Space list: `bold 16px Arial`
     *  - Slash wrapping Space lists: `bold 16px/1.5 Arial`
     *  - Comma wrapping the above + extra family items: `bold 16px Arial, sans-serif`
     *  - Comma + Slash combo: `bold 16px/1.5 Arial, sans-serif`
     *
     * @return array<string, Value>
     */
    private function expandFont(Value $value): array
    {
        $head = $value;
        $extraFamilies = [];
        if ($value instanceof ValueList && $value->separator === ListSeparator::Comma) {
            $head = $value->values[0] ?? $value;
            $extraFamilies = array_slice($value->values, 1);
        }

        $lineHeight = null;
        $tail = [];
        if ($head instanceof ValueList && $head->separator === ListSeparator::Slash) {
            $sizeSegment = $head->values[0] ?? $head;
            $afterSlash = $head->values[1] ?? null;
            if ($afterSlash !== null) {
                $afterItems = $afterSlash instanceof ValueList && $afterSlash->separator === ListSeparator::Space
                    ? $afterSlash->values
                    : [$afterSlash];
                $lineHeight = $afterItems[0] ?? null;
                $tail = array_slice($afterItems, 1);
            }
            $head = $sizeSegment;
        }

        $items = $head instanceof ValueList && $head->separator === ListSeparator::Space
            ? $head->values
            : [$head];

        $style = $variant = $weight = $stretch = $size = null;
        $familyHead = [];
        foreach ($items as $item) {
            if ($size === null) {
                if ($style === null && $this->looksLikeFontStyle($item)) {
                    $style = $item;
                    continue;
                }
                if ($variant === null && $this->looksLikeFontVariant($item)) {
                    $variant = $item;
                    continue;
                }
                if ($weight === null && $this->looksLikeFontWeight($item)) {
                    $weight = $item;
                    continue;
                }
                if ($stretch === null && $this->looksLikeFontStretch($item)) {
                    $stretch = $item;
                    continue;
                }
                if ($this->looksLikeFontSize($item)) {
                    $size = $item;
                    continue;
                }
                continue;
            }
            $familyHead[] = $item;
        }

        $allFamilies = array_merge($familyHead, $tail, $extraFamilies);

        $out = [];
        if ($size !== null) {
            $out['font-size'] = $size;
        }
        if ($style !== null) {
            $out['font-style'] = $style;
        }
        if ($variant !== null) {
            $out['font-variant'] = $variant;
        }
        if ($weight !== null) {
            $out['font-weight'] = $weight;
        }
        if ($stretch !== null) {
            $out['font-stretch'] = $stretch;
        }
        if ($lineHeight !== null) {
            $out['line-height'] = $lineHeight;
        }
        if ($allFamilies !== []) {
            $out['font-family'] = count($allFamilies) === 1
                ? $allFamilies[0]
                : new ValueList(array_values($allFamilies), ListSeparator::Comma);
        }
        return $out;
    }

    private function looksLikeFontStyle(Value $v): bool
    {
        if (!$v instanceof Keyword) {
            return false;
        }
        return in_array(strtolower($v->name), ['italic', 'oblique'], true);
    }

    private function looksLikeFontVariant(Value $v): bool
    {
        return $v instanceof Keyword && strtolower($v->name) === 'small-caps';
    }

    private function looksLikeFontWeight(Value $v): bool
    {
        if ($v instanceof Keyword
            && in_array(strtolower($v->name), ['bold', 'bolder', 'lighter'], true)
        ) {
            return true;
        }
        if ($v instanceof Integer || $v instanceof Number) {
            $n = $v instanceof Integer ? $v->value : (int) $v->value;
            return $n >= 1 && $n <= 1000;
        }
        return false;
    }

    private function looksLikeFontStretch(Value $v): bool
    {
        if (!$v instanceof Keyword) {
            return false;
        }
        return in_array(strtolower($v->name), [
            'ultra-condensed', 'extra-condensed', 'condensed', 'semi-condensed',
            'semi-expanded', 'expanded', 'extra-expanded', 'ultra-expanded',
        ], true);
    }

    /**
     * `background: <bg-image> || <position> [ / <bg-size> ]? || <repeat> ||
     * <attachment> || <box> || <box> || <color>` (CSS Backgrounds 3 §3.10),
     * with `<box>` reused for both `background-origin` and `background-clip`.
     *
     * Phase-1 implementation classifies components by their parsed type and
     * keyword vocabulary rather than tracking the per-component grammar
     * precisely — sufficient for the common author-CSS patterns
     * (`background: red`, `background: url(x.png)`, `background: #fff
     * no-repeat`, `background: url(bg.jpg) center / cover`). Components the
     * classifier doesn't recognise are dropped, which is forgiving but
     * sometimes lossy — matches browser behaviour for malformed inputs.
     *
     * @return array<string, Value>
     */
    private function expandBackground(Value $value): array
    {
        // Slash separates position from size: `background: <pos> / <size>`.
        $position = null;
        $size = null;
        $body = $value;
        if ($value instanceof ValueList && $value->separator === ListSeparator::Slash) {
            $body = $value->values[0] ?? $value;
            $size = $value->values[1] ?? null;
        }

        $components = $this->toComponents($body);
        $color = null;
        $image = null;
        $repeat = null;
        $attachment = null;
        $origin = null;
        $clip = null;
        $positionParts = [];
        $geoBoxKw = ['border-box', 'padding-box', 'content-box', 'text'];
        foreach ($components as $c) {
            if ($this->looksLikeColor($c)) {
                $color = $c;
                continue;
            }
            // CSS Backgrounds 3 §3.1 — `background-image` accepts
            // any <image>: url, image-set, gradient, cross-fade.
            if ($c instanceof \Phpdftk\Css\Value\Url
                || $c instanceof \Phpdftk\Css\Value\Gradient
                || $c instanceof \Phpdftk\Css\Value\ImageSet
                || $c instanceof \Phpdftk\Css\Value\CrossFade
            ) {
                $image = $c;
                continue;
            }
            if ($c instanceof Keyword) {
                $lower = strtolower($c->name);
                if (in_array($lower, ['repeat', 'no-repeat', 'repeat-x', 'repeat-y', 'round', 'space'], true)) {
                    $repeat = $c;
                    continue;
                }
                if (in_array($lower, ['scroll', 'fixed', 'local'], true)) {
                    $attachment = $c;
                    continue;
                }
                if (in_array($lower, $geoBoxKw, true)) {
                    // First geometry-box → origin AND clip; second → clip only.
                    if ($origin === null) {
                        $origin = $c;
                        $clip = $c;
                    } else {
                        $clip = $c;
                    }
                    continue;
                }
                if (in_array($lower, ['top', 'bottom', 'left', 'right', 'center'], true)) {
                    $positionParts[] = $c;
                    continue;
                }
            }
            if ($c instanceof Length || $c instanceof Percentage) {
                $positionParts[] = $c;
            }
        }
        if ($positionParts !== []) {
            $position = count($positionParts) === 1
                ? $positionParts[0]
                : new ValueList($positionParts, ListSeparator::Space);
        }

        $out = [];
        if ($color !== null) {
            $out['background-color'] = $color;
        }
        if ($image !== null) {
            $out['background-image'] = $image;
        }
        if ($repeat !== null) {
            $out['background-repeat'] = $repeat;
        }
        if ($attachment !== null) {
            $out['background-attachment'] = $attachment;
        }
        if ($origin !== null) {
            $out['background-origin'] = $origin;
        }
        if ($clip !== null) {
            $out['background-clip'] = $clip;
        }
        if ($position !== null) {
            $out['background-position'] = $position;
        }
        if ($size !== null) {
            $out['background-size'] = $size;
        }
        return $out;
    }

    /**
     * `list-style: <list-style-type> || <list-style-position> ||
     * <list-style-image>` (CSS Lists 3 §1.4). Free order; the `none`
     * keyword is genuinely ambiguous between type and image — per spec we
     * apply it to whichever side hasn't been set yet, defaulting to type
     * when both are still free.
     *
     * @return array<string, Value>
     */
    private function expandListStyle(Value $value): array
    {
        $type = null;
        $position = null;
        $image = null;
        $components = $this->toComponents($value);

        $typeKeywords = [
            'disc', 'circle', 'square', 'decimal', 'decimal-leading-zero',
            'lower-alpha', 'upper-alpha', 'lower-roman', 'upper-roman',
            'lower-greek', 'lower-latin', 'upper-latin', 'armenian', 'georgian',
            'hebrew', 'cjk-decimal', 'simp-chinese-formal', 'simp-chinese-informal',
            'trad-chinese-formal', 'trad-chinese-informal',
        ];

        foreach ($components as $c) {
            if ($c instanceof \Phpdftk\Css\Value\Url) {
                $image = $c;
                continue;
            }
            if (!$c instanceof Keyword) {
                continue;
            }
            $lower = strtolower($c->name);
            if ($lower === 'inside' || $lower === 'outside') {
                $position = $c;
                continue;
            }
            if ($lower === 'none') {
                if ($type === null) {
                    $type = $c;
                } elseif ($image === null) {
                    $image = $c;
                }
                continue;
            }
            if (in_array($lower, $typeKeywords, true)) {
                $type = $c;
            }
        }

        $out = [];
        if ($type !== null) {
            $out['list-style-type'] = $type;
        }
        if ($position !== null) {
            $out['list-style-position'] = $position;
        }
        if ($image !== null) {
            $out['list-style-image'] = $image;
        }
        return $out;
    }

    /**
     * `text-decoration: <line> || <style> || <color>` (CSS Text Decoration 3
     * §2). `<line>` itself can be a space-list of `underline` / `overline` /
     * `line-through` / `blink`, or `none`. Order is free.
     *
     * @return array<string, Value>
     */
    private function expandTextDecoration(Value $value): array
    {
        $components = $this->toComponents($value);
        $lineParts = [];
        $style = null;
        $color = null;
        $thickness = null;
        foreach ($components as $c) {
            if ($c instanceof Keyword) {
                $lower = strtolower($c->name);
                if (in_array($lower, [
                    'underline', 'overline', 'line-through', 'blink', 'none',
                    // CSS Text Decoration 4 §2.1 — spelling-error /
                    // grammar-error are decoration-line keywords drawn
                    // with the UA's spell/grammar squiggly style.
                    'spelling-error', 'grammar-error',
                ], true)) {
                    $lineParts[] = $c;
                    continue;
                }
                if (in_array($lower, ['solid', 'double', 'dotted', 'dashed', 'wavy'], true)) {
                    $style = $c;
                    continue;
                }
                // CSS Text Decoration 4 §1.5 — `auto` / `from-font`
                // are the named thickness forms.
                if ($thickness === null && in_array($lower, ['auto', 'from-font'], true)) {
                    $thickness = $c;
                    continue;
                }
            }
            // Length / Percentage at this slot are thickness values
            // per §1.5; the only other place a Length appears in the
            // shorthand is the color slot, which Length isn't.
            if ($thickness === null && ($c instanceof Length || $c instanceof Percentage)) {
                $thickness = $c;
                continue;
            }
            if ($this->looksLikeColor($c)) {
                $color = $c;
            }
        }
        $out = [];
        if ($lineParts !== []) {
            $out['text-decoration-line'] = count($lineParts) === 1
                ? $lineParts[0]
                : new ValueList($lineParts, ListSeparator::Space);
        }
        if ($style !== null) {
            $out['text-decoration-style'] = $style;
        }
        if ($color !== null) {
            $out['text-decoration-color'] = $color;
        }
        if ($thickness !== null) {
            $out['text-decoration-thickness'] = $thickness;
        }
        return $out;
    }

    /**
     * `flex: <flex-grow> <flex-shrink>? <flex-basis>?` (CSS Flex 1
     * §7.2). Common forms:
     *  - `flex: <number>` → grow with shrink=1, basis=0%.
     *  - `flex: <number> <number>` → grow + shrink, basis=0%.
     *  - `flex: <number> <number> <length>` → all three explicit.
     *  - `flex: none` → 0 0 auto.
     *  - `flex: auto` → 1 1 auto.
     *  - `flex: initial` → 0 1 auto (the spec initial).
     *
     * @return array<string, Value>
     */
    private function expandFlex(Value $value): array
    {
        if ($value instanceof Keyword) {
            return match (strtolower($value->name)) {
                'none' => [
                    'flex-grow' => new Number(0),
                    'flex-shrink' => new Number(0),
                    'flex-basis' => new Keyword('auto'),
                ],
                'auto' => [
                    'flex-grow' => new Number(1),
                    'flex-shrink' => new Number(1),
                    'flex-basis' => new Keyword('auto'),
                ],
                'initial' => [
                    'flex-grow' => new Number(0),
                    'flex-shrink' => new Number(1),
                    'flex-basis' => new Keyword('auto'),
                ],
                default => [],
            };
        }
        $components = $this->toComponents($value);
        $grow = null;
        $shrink = null;
        $basis = null;
        $numericCount = 0;
        foreach ($components as $c) {
            if (($c instanceof Number || $c instanceof Integer) && $numericCount < 2) {
                if ($numericCount === 0) {
                    $grow = new Number($c instanceof Number ? $c->value : (float) $c->value);
                } else {
                    $shrink = new Number($c instanceof Number ? $c->value : (float) $c->value);
                }
                $numericCount++;
            } elseif ($basis === null) {
                $basis = $c;
            }
        }
        $out = [];
        if ($grow !== null) {
            $out['flex-grow'] = $grow;
        }
        if ($shrink !== null) {
            $out['flex-shrink'] = $shrink;
        }
        if ($basis !== null) {
            $out['flex-basis'] = $basis;
        }
        // Per spec, omitted basis defaults to 0% when grow is set
        // (so `flex: 2` → grow:2, shrink:1, basis:0%). Use Length 0
        // as the closest approximation.
        if ($grow !== null && $basis === null) {
            $out['flex-basis'] = new \Phpdftk\Css\Value\Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px);
        }
        // Omitted shrink defaults to 1.
        if ($grow !== null && $shrink === null) {
            $out['flex-shrink'] = new Number(1.0);
        }
        return $out;
    }

    /**
     * `flex-flow: <flex-direction> || <flex-wrap>` (CSS Flex 1 §6.2).
     *
     * @return array<string, Value>
     */
    private function expandFlexFlow(Value $value): array
    {
        $components = $this->toComponents($value);
        $directions = ['row', 'row-reverse', 'column', 'column-reverse'];
        $wraps = ['nowrap', 'wrap', 'wrap-reverse'];
        $out = [];
        foreach ($components as $c) {
            if (!($c instanceof Keyword)) {
                continue;
            }
            $name = strtolower($c->name);
            if (in_array($name, $directions, true) && !isset($out['flex-direction'])) {
                $out['flex-direction'] = $c;
            } elseif (in_array($name, $wraps, true) && !isset($out['flex-wrap'])) {
                $out['flex-wrap'] = $c;
            }
        }
        return $out;
    }

    /**
     * `overflow: <visible|hidden|clip|scroll|auto>{1,2}` (CSS
     * Overflow 3 §3.2). Single value applies to both axes;
     * two values are `overflow-x overflow-y`. Also keeps the legacy
     * `overflow` longhand so existing painter code keeps reading
     * the un-prefixed value.
     *
     * @return array<string, Value>
     */
    private function expandOverflow(Value $value): array
    {
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        [$x, $y] = count($components) === 1
            ? [$components[0], $components[0]]
            : [$components[0], $components[1]];
        return [
            'overflow-x' => $x,
            'overflow-y' => $y,
            // Keep the legacy direct property in sync so any reader
            // that still reaches for `overflow` sees a sensible value
            // (the X axis for asymmetric splits).
            'overflow' => $x,
        ];
    }

    /**
     * `inset: <length> [<length>{1,3}]?` (CSS Position 3 §3.3).
     * Shorthand for `top` / `right` / `bottom` / `left` using the
     * standard 1-to-4-value clockwise pattern (TRBL).
     *
     * @return array<string, Value>
     */
    private function expandInset(Value $value): array
    {
        $components = $this->toComponents($value);
        $count = count($components);
        if ($count === 0) {
            return [];
        }
        [$top, $right, $bottom, $left] = match ($count) {
            1 => [$components[0], $components[0], $components[0], $components[0]],
            2 => [$components[0], $components[1], $components[0], $components[1]],
            3 => [$components[0], $components[1], $components[2], $components[1]],
            default => [$components[0], $components[1], $components[2], $components[3]],
        };
        return [
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'left' => $left,
        ];
    }

    /**
     * `gap: <row-gap> [<column-gap>]?` (CSS Box Alignment 3 §8.3).
     * One value: applies to both axes; two values: row first, column
     * second.
     *
     * @return array<string, Value>
     */
    private function expandGap(Value $value): array
    {
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        [$row, $col] = match (count($components)) {
            1 => [$components[0], $components[0]],
            default => [$components[0], $components[1]],
        };
        return [
            'row-gap' => $row,
            'column-gap' => $col,
        ];
    }

    /**
     * `columns: <'column-width'> || <'column-count'>` (CSS Multi-column 1
     * §10.1). Either side may be omitted; `auto` may appear as either side
     * and is assigned to whichever slot is still free (width first).
     *
     * @return array<string, Value>
     */
    private function expandColumns(Value $value): array
    {
        $components = $this->toComponents($value);
        $width = null;
        $count = null;
        foreach ($components as $c) {
            if ($count === null && ($c instanceof Integer
                || ($c instanceof Number && floor($c->value) === $c->value))
            ) {
                $count = $c;
                continue;
            }
            if ($width === null && ($c instanceof Length || $c instanceof Percentage)) {
                $width = $c;
                continue;
            }
            if ($c instanceof Keyword && strtolower($c->name) === 'auto') {
                if ($width === null) {
                    $width = $c;
                } elseif ($count === null) {
                    $count = $c;
                }
            }
        }
        $out = [];
        if ($width !== null) {
            $out['column-width'] = $width;
        }
        if ($count !== null) {
            $out['column-count'] = $count;
        }
        return $out;
    }

    /**
     * `column-rule: <'column-rule-width'> || <'column-rule-style'> ||
     * <'column-rule-color'>` (CSS Multi-column 1 §3.2). Free order; reuses
     * the border-width / border-style / color classifiers because the value
     * grammars match.
     *
     * @return array<string, Value>
     */
    private function expandColumnRule(Value $value): array
    {
        $components = $this->toComponents($value);
        $width = null;
        $style = null;
        $color = null;
        foreach ($components as $c) {
            if ($style === null && $this->looksLikeBorderStyle($c)) {
                $style = $c;
                continue;
            }
            if ($width === null && $this->looksLikeBorderWidth($c)) {
                $width = $c;
                continue;
            }
            if ($color === null && $this->looksLikeColor($c)) {
                $color = $c;
            }
        }
        $out = [];
        if ($width !== null) {
            $out['column-rule-width'] = $width;
        }
        if ($style !== null) {
            $out['column-rule-style'] = $style;
        }
        if ($color !== null) {
            $out['column-rule-color'] = $color;
        }
        return $out;
    }

    /**
     * CSS Anchor Positioning 1 §8 — `position-try` shorthand for
     * `position-try-order` + `position-try-fallbacks`. Two-form
     * grammar:
     *
     *   - Single value (no order keyword): all components feed the
     *     fallbacks list; order defaults to `normal`.
     *   - Leading order keyword (`normal | most-width | most-height
     *     | most-block-size | most-inline-size`) sets the order
     *     before the rest of the components feed the fallbacks list.
     *
     * @return array<string, Value>
     */
    private function expandPositionTry(Value $value): array
    {
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        $orderKeywords = [
            'normal', 'most-width', 'most-height',
            'most-block-size', 'most-inline-size',
        ];
        $order = new Keyword('normal');
        $fallbackComponents = $components;
        $head = $components[0];
        if ($head instanceof Keyword
            && in_array(strtolower($head->name), $orderKeywords, true)
        ) {
            $order = $head;
            $fallbackComponents = array_slice($components, 1);
        }
        $fallbacks = match (count($fallbackComponents)) {
            0 => new Keyword('none'),
            1 => $fallbackComponents[0],
            default => new \Phpdftk\Css\Value\ValueList(
                $fallbackComponents,
                \Phpdftk\Css\Value\ListSeparator::Comma,
            ),
        };
        return [
            'position-try-order' => $order,
            'position-try-fallbacks' => $fallbacks,
        ];
    }

    /**
     * CSS Masking 1 §17 — `mask` shorthand. Per the spec each comma-
     * separated layer carries up to 8 components:
     *
     *   <mask-image> || <position> [/ <size>]? || <repeat-style>
     *     || <geometry-box> [<geometry-box> | no-clip]?
     *     || <compositing-operator> || <masking-mode>
     *
     * This expander handles the practical single-layer subset
     * authors actually write (the only one any browser ships
     * end-to-end painting for): pick out the image source (Url /
     * ImageSet / gradient typed values), the position+size pair,
     * the repeat keyword, the geometry-box keywords (which assign
     * to both mask-origin and mask-clip), the compositing operator,
     * and the masking mode. Anything not recognised at a slot is
     * left at its registry default.
     *
     * @return array<string, Value>
     */
    private function expandMask(Value $value): array
    {
        $layers = $this->toCommaLayers($value);
        $images = [];
        $positions = [];
        $sizes = [];
        $repeats = [];
        $origins = [];
        $clips = [];
        $composites = [];
        $modes = [];
        $repeatKw = ['repeat', 'repeat-x', 'repeat-y', 'space', 'round', 'no-repeat'];
        $geoBoxKw = [
            'border-box', 'padding-box', 'content-box',
            'margin-box', 'fill-box', 'stroke-box', 'view-box',
        ];
        $compositeKw = ['add', 'subtract', 'intersect', 'exclude'];
        $modeKw = ['match-source', 'alpha', 'luminance'];
        foreach ($layers as $layer) {
            $componentsAll = $this->toComponents($layer);
            $image = null;
            $position = null;
            $size = null;
            $repeat = null;
            $origin = null;
            $clip = null;
            $composite = null;
            $mode = null;

            // Pull out `<position> / <size>` first if there's a slash
            // anywhere in the layer. The slash form arrives as a
            // ValueList(Slash). Other components remain in $rest.
            $slashLayer = null;
            $afterSlash = null;
            $rest = $componentsAll;
            if ($layer instanceof ValueList && $layer->separator === ListSeparator::Slash) {
                $slashLayer = $layer->values[0] ?? null;
                $afterSlash = $layer->values[1] ?? null;
                $rest = $slashLayer instanceof ValueList
                    && $slashLayer->separator === ListSeparator::Space
                        ? $slashLayer->values
                        : ($slashLayer !== null ? [$slashLayer] : []);
                $size = $afterSlash;
            }
            foreach ($rest as $c) {
                if ($image === null && $this->looksLikeMaskImage($c)) {
                    $image = $c;
                    continue;
                }
                if ($repeat === null && $c instanceof Keyword
                    && in_array(strtolower($c->name), $repeatKw, true)
                ) {
                    $repeat = $c;
                    continue;
                }
                if ($c instanceof Keyword
                    && in_array(strtolower($c->name), $geoBoxKw, true)
                ) {
                    // Per spec, first geometry-box → mask-origin AND
                    // mask-clip; second → mask-clip only.
                    if ($origin === null) {
                        $origin = $c;
                        $clip = $c;
                    } else {
                        $clip = $c;
                    }
                    continue;
                }
                if ($clip === null && $c instanceof Keyword && strtolower($c->name) === 'no-clip') {
                    $clip = $c;
                    continue;
                }
                if ($composite === null && $c instanceof Keyword
                    && in_array(strtolower($c->name), $compositeKw, true)
                ) {
                    $composite = $c;
                    continue;
                }
                if ($mode === null && $c instanceof Keyword
                    && in_array(strtolower($c->name), $modeKw, true)
                ) {
                    $mode = $c;
                    continue;
                }
                // Anything left is treated as part of the position.
                if ($position === null) {
                    $position = $c;
                } elseif ($position instanceof ValueList && $position->separator === ListSeparator::Space) {
                    $position = new ValueList(
                        [...$position->values, $c],
                        ListSeparator::Space,
                    );
                } else {
                    $position = new ValueList([$position, $c], ListSeparator::Space);
                }
            }
            $images[] = $image ?? new Keyword('none');
            $positions[] = $position ?? new Keyword('0%');
            $sizes[] = $size ?? new Keyword('auto');
            $repeats[] = $repeat ?? new Keyword('repeat');
            $origins[] = $origin ?? new Keyword('border-box');
            $clips[] = $clip ?? new Keyword('border-box');
            $composites[] = $composite ?? new Keyword('add');
            $modes[] = $mode ?? new Keyword('match-source');
        }
        return [
            'mask-image' => $this->joinComma($images),
            'mask-position' => $this->joinComma($positions),
            'mask-size' => $this->joinComma($sizes),
            'mask-repeat' => $this->joinComma($repeats),
            'mask-origin' => $this->joinComma($origins),
            'mask-clip' => $this->joinComma($clips),
            'mask-composite' => $this->joinComma($composites),
            'mask-mode' => $this->joinComma($modes),
        ];
    }

    /**
     * CSS Backgrounds 3 §6.1 — `border-image` shorthand. Per the
     * spec:
     *
     *   border-image: <source> || <slice> [/ <width> | / <width>?
     *                 / <outset>]? || <repeat>
     *
     * Picks out the typed image source first, the repeat keyword(s)
     * (1 or 2 of stretch | repeat | round | space), and the
     * slash-split <slice> / <width> / <outset> chain. The slice /
     * width / outset components may each be 1-4 numbers, a single
     * value, or `fill` (slice only).
     *
     * @return array<string, Value>
     */
    private function expandBorderImage(Value $value): array
    {
        $repeatKw = ['stretch', 'repeat', 'round', 'space'];
        // The shorthand uses `/` to separate slice / width / outset
        // chunks. Authors usually write them on a single layer (no
        // top-level commas), so flatten to a flat component list and
        // then peel slash groups.
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        $source = null;
        $repeats = [];
        $slashChunks = [[]];
        foreach ($components as $c) {
            if ($source === null && $this->looksLikeMaskImage($c)) {
                $source = $c;
                continue;
            }
            if ($c instanceof Keyword
                && in_array(strtolower($c->name), $repeatKw, true)
            ) {
                $repeats[] = $c;
                continue;
            }
            $slashChunks[count($slashChunks) - 1][] = $c;
        }
        // If the input was a ValueList(Slash), explode it.
        if ($value instanceof ValueList && $value->separator === ListSeparator::Slash) {
            $slashChunks = array_map(
                fn(Value $g) => $g instanceof ValueList && $g->separator === ListSeparator::Space
                    ? $g->values
                    : [$g],
                $value->values,
            );
            // Re-scan first chunk for source / repeats so they don't
            // pollute the slice list.
            $reScanned = [];
            foreach ($slashChunks[0] ?? [] as $c) {
                if ($source === null && $this->looksLikeMaskImage($c)) {
                    $source = $c;
                    continue;
                }
                if ($c instanceof Keyword
                    && in_array(strtolower($c->name), $repeatKw, true)
                ) {
                    $repeats[] = $c;
                    continue;
                }
                $reScanned[] = $c;
            }
            $slashChunks[0] = $reScanned;
        }
        $out = [];
        if ($source !== null) {
            $out['border-image-source'] = $source;
        }
        if ($slashChunks[0] !== []) {
            $out['border-image-slice'] = $this->joinSpace($slashChunks[0]);
        }
        if (isset($slashChunks[1]) && $slashChunks[1] !== []) {
            $out['border-image-width'] = $this->joinSpace($slashChunks[1]);
        }
        if (isset($slashChunks[2]) && $slashChunks[2] !== []) {
            $out['border-image-outset'] = $this->joinSpace($slashChunks[2]);
        }
        if ($repeats !== []) {
            $out['border-image-repeat'] = count($repeats) === 1
                ? $repeats[0]
                : new ValueList($repeats, ListSeparator::Space);
        }
        return $out;
    }

    /**
     * CSS Logical Properties 1 §7 — `border-block` / `border-inline`
     * fan out the same width/style/color tuple to both sides of
     * the chosen axis.
     *
     * @return array<string, Value>
     */
    private function expandBorderAxis(Value $value, string $axis): array
    {
        $components = $this->toComponents($value);
        $sides = $axis === 'block'
            ? ['block-start', 'block-end']
            : ['inline-start', 'inline-end'];
        return $this->classifyBorderComponents($components, $sides);
    }

    /**
     * CSS Logical Properties 1 §7 — single-side logical shorthand
     * (e.g. `border-block-start: 1px solid red`). Same width /
     * style / color slots, but on one side instead of four.
     *
     * @return array<string, Value>
     */
    private function expandBorderLogicalSide(string $shorthand, Value $value): array
    {
        $components = $this->toComponents($value);
        // `border-block-start` → side suffix is `block-start`.
        $side = substr($shorthand, strlen('border-'));
        return $this->classifyBorderComponents($components, [$side]);
    }

    /**
     * Write the value to BOTH the legacy alias and the modern
     * canonical property name. The modern property is the one the
     * renderer reads; the legacy name is also retained so any
     * downstream code reading the original property still sees it.
     *
     * @return array<string, Value>
     */
    private function alias(string $legacy, Value $value, string $modern): array
    {
        return [
            $legacy => $value,
            $modern => $value,
        ];
    }

    /**
     * CSS Masking 1 §13 — `mask-border` shorthand, the masking
     * analogue of `border-image`. Same grammar shape, with an
     * additional optional `<mask-border-mode>` keyword:
     *
     *   mask-border = <source> || <slice> [/ <width> [/ <outset>]?]?
     *                 || <repeat> || <mode>
     *
     *   <mode>: luminance | alpha
     *
     * @return array<string, Value>
     */
    private function expandMaskBorder(Value $value): array
    {
        $repeatKw = ['stretch', 'repeat', 'round', 'space'];
        $modeKw = ['luminance', 'alpha'];
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        $source = null;
        $repeats = [];
        $mode = null;
        $slashChunks = [[]];
        foreach ($components as $c) {
            if ($source === null && $this->looksLikeMaskImage($c)) {
                $source = $c;
                continue;
            }
            if ($mode === null && $c instanceof Keyword
                && in_array(strtolower($c->name), $modeKw, true)
            ) {
                $mode = $c;
                continue;
            }
            if ($c instanceof Keyword
                && in_array(strtolower($c->name), $repeatKw, true)
            ) {
                $repeats[] = $c;
                continue;
            }
            $slashChunks[count($slashChunks) - 1][] = $c;
        }
        if ($value instanceof ValueList && $value->separator === ListSeparator::Slash) {
            // Same slash-chunk explosion border-image uses, then
            // re-scan the first chunk for source / mode / repeat.
            $slashChunks = array_map(
                fn(Value $g) => $g instanceof ValueList && $g->separator === ListSeparator::Space
                    ? $g->values
                    : [$g],
                $value->values,
            );
            $reScanned = [];
            foreach ($slashChunks[0] ?? [] as $c) {
                if ($source === null && $this->looksLikeMaskImage($c)) {
                    $source = $c;
                    continue;
                }
                if ($mode === null && $c instanceof Keyword
                    && in_array(strtolower($c->name), $modeKw, true)
                ) {
                    $mode = $c;
                    continue;
                }
                if ($c instanceof Keyword
                    && in_array(strtolower($c->name), $repeatKw, true)
                ) {
                    $repeats[] = $c;
                    continue;
                }
                $reScanned[] = $c;
            }
            $slashChunks[0] = $reScanned;
        }
        $out = [];
        if ($source !== null) {
            $out['mask-border-source'] = $source;
        }
        if ($slashChunks[0] !== []) {
            $out['mask-border-slice'] = $this->joinSpace($slashChunks[0]);
        }
        if (isset($slashChunks[1]) && $slashChunks[1] !== []) {
            $out['mask-border-width'] = $this->joinSpace($slashChunks[1]);
        }
        if (isset($slashChunks[2]) && $slashChunks[2] !== []) {
            $out['mask-border-outset'] = $this->joinSpace($slashChunks[2]);
        }
        if ($repeats !== []) {
            $out['mask-border-repeat'] = count($repeats) === 1
                ? $repeats[0]
                : new ValueList($repeats, ListSeparator::Space);
        }
        if ($mode !== null) {
            $out['mask-border-mode'] = $mode;
        }
        return $out;
    }

    /**
     * Join a list of space-separated component values into a single
     * Value, collapsing the trivial cases.
     *
     * @param list<Value> $values
     */
    private function joinSpace(array $values): Value
    {
        if (count($values) === 1) {
            return $values[0];
        }
        return new ValueList(array_values($values), ListSeparator::Space);
    }

    /**
     * CSS Fonts 4 §6.11 — `font-variant` shorthand.
     *
     *   font-variant = normal | none | [ <common-lig-values> ||
     *                  <discretionary-lig-values> || <historical-
     *                  lig-values> || <contextual-alt-values> ||
     *                  stylistic(<ident>) || historical-forms ||
     *                  styleset(<ident>+) || character-variant(...) ||
     *                  swash(<ident>) || ornaments(<ident>) ||
     *                  annotation(<ident>) || [ small-caps |
     *                  all-small-caps | petite-caps | all-petite-caps |
     *                  unicase | titling-caps ] || <numeric-figure-values>
     *                  || <numeric-spacing-values> ||
     *                  <numeric-fraction-values> || ordinal || slashed-zero
     *                  || <east-asian-variant-values> ||
     *                  <east-asian-width-values> || ruby || sub | super ]
     *
     * Each component routes to its respective longhand by keyword.
     * `normal` / `none` shortcuts each set all longhands to that
     * keyword.
     *
     * @return array<string, Value>
     */
    private function expandFontVariant(Value $value): array
    {
        $allLonghands = [
            'font-variant-ligatures',
            'font-variant-caps',
            'font-variant-numeric',
            'font-variant-east-asian',
            'font-variant-position',
            'font-variant-alternates',
            'font-variant-emoji',
        ];
        $components = $this->toComponents($value);
        // `normal` / `none` shortcuts.
        if (count($components) === 1 && $components[0] instanceof Keyword) {
            $kw = strtolower($components[0]->name);
            if ($kw === 'normal' || $kw === 'none') {
                $out = [];
                $target = $kw === 'none'
                    ? new Keyword('none')
                    : new Keyword('normal');
                foreach ($allLonghands as $prop) {
                    $out[$prop] = $target;
                }
                return $out;
            }
        }
        // Per-component routing by keyword vocabulary.
        $capsKw = [
            'small-caps', 'all-small-caps', 'petite-caps',
            'all-petite-caps', 'unicase', 'titling-caps',
        ];
        $positionKw = ['sub', 'super'];
        $ligKw = [
            'common-ligatures', 'no-common-ligatures',
            'discretionary-ligatures', 'no-discretionary-ligatures',
            'historical-ligatures', 'no-historical-ligatures',
            'contextual', 'no-contextual',
        ];
        $numericKw = [
            'lining-nums', 'oldstyle-nums', 'proportional-nums', 'tabular-nums',
            'diagonal-fractions', 'stacked-fractions',
            'ordinal', 'slashed-zero',
        ];
        $eastAsianKw = [
            'jis78', 'jis83', 'jis90', 'jis04',
            'simplified', 'traditional',
            'full-width', 'proportional-width', 'ruby',
        ];
        $emojiKw = ['text', 'emoji', 'unicode'];
        $alternatesKw = ['historical-forms'];

        $buckets = [];
        foreach ($components as $c) {
            if (!($c instanceof Keyword)) {
                continue;
            }
            $kw = strtolower($c->name);
            $prop = match (true) {
                in_array($kw, $capsKw, true) => 'font-variant-caps',
                in_array($kw, $positionKw, true) => 'font-variant-position',
                in_array($kw, $ligKw, true) => 'font-variant-ligatures',
                in_array($kw, $numericKw, true) => 'font-variant-numeric',
                in_array($kw, $eastAsianKw, true) => 'font-variant-east-asian',
                in_array($kw, $emojiKw, true) => 'font-variant-emoji',
                in_array($kw, $alternatesKw, true) => 'font-variant-alternates',
                default => null,
            };
            if ($prop === null) {
                continue;
            }
            $buckets[$prop][] = $c;
        }
        $out = [];
        foreach ($buckets as $prop => $entries) {
            $out[$prop] = count($entries) === 1
                ? $entries[0]
                : new ValueList($entries, ListSeparator::Space);
        }
        return $out;
    }

    /**
     * CSS Fonts 4 §6.7 — `font-synthesis` shorthand for the four
     * synthesis axis longhands. Two grammar shapes:
     *
     *   - `none` → all four longhands become `none` (UA must not
     *     synthesise anything; respect the font as-shipped).
     *   - `[ weight || style || small-caps || position ]` → each
     *     listed axis sets its longhand to `auto`, unlisted axes
     *     fall to `none`.
     *
     * Default for each longhand is `auto`; this shorthand only
     * fires when authors explicitly opt out via `none` or restrict
     * the active set.
     *
     * @return array<string, Value>
     */
    private function expandFontSynthesis(Value $value): array
    {
        $longhands = [
            'weight' => 'font-synthesis-weight',
            'style' => 'font-synthesis-style',
            'small-caps' => 'font-synthesis-small-caps',
            'position' => 'font-synthesis-position',
        ];
        $components = $this->toComponents($value);
        $none = new Keyword('none');
        $auto = new Keyword('auto');
        // Single `none` sets every longhand to none.
        if (count($components) === 1
            && $components[0] instanceof Keyword
            && strtolower($components[0]->name) === 'none'
        ) {
            $out = [];
            foreach ($longhands as $prop) {
                $out[$prop] = $none;
            }
            return $out;
        }
        $on = [];
        foreach ($components as $c) {
            if (!($c instanceof Keyword)) {
                continue;
            }
            $kw = strtolower($c->name);
            if (isset($longhands[$kw])) {
                $on[$kw] = true;
            }
        }
        $out = [];
        foreach ($longhands as $kw => $prop) {
            $out[$prop] = isset($on[$kw]) ? $auto : $none;
        }
        return $out;
    }

    /**
     * CSS UI 4 §5.3 — `caret` shorthand for `caret-color` +
     * `caret-shape`. Any-order: typed color → caret-color,
     * recognised shape keyword → caret-shape.
     *
     *   caret-shape: auto | bar | block | underscore
     *
     * @return array<string, Value>
     */
    private function expandCaret(Value $value): array
    {
        $shapeKw = ['auto', 'bar', 'block', 'underscore'];
        $components = $this->toComponents($value);
        $color = null;
        $shape = null;
        foreach ($components as $c) {
            if ($color === null && $this->isColorComponent($c)) {
                $color = $c;
                continue;
            }
            if ($shape === null && $c instanceof Keyword
                && in_array(strtolower($c->name), $shapeKw, true)
            ) {
                $shape = $c;
            }
        }
        $out = [];
        if ($color !== null) {
            $out['caret-color'] = $color;
        }
        if ($shape !== null) {
            $out['caret-shape'] = $shape;
        }
        return $out;
    }

    /**
     * CSS Text 4 §3.1 — `white-space` shorthand for
     * `white-space-collapse` + `text-wrap-mode`. Two shapes:
     *
     * 1. Legacy single-keyword forms (CSS 2.1):
     *      normal       → collapse + wrap
     *      pre          → preserve + nowrap
     *      pre-wrap     → preserve + wrap
     *      pre-line     → preserve-breaks + wrap
     *      nowrap       → collapse + nowrap
     *      break-spaces → break-spaces + wrap
     *
     * 2. New two-keyword form (CSS Text 4):
     *      <white-space-collapse> || <text-wrap-mode>
     *
     * The cascade also keeps `white-space` as a longhand itself
     * (so reading back the original declaration still works);
     * downstream layout reads through the new longhands.
     *
     * @return array<string, Value>
     */
    private function expandWhiteSpace(Value $value): array
    {
        $legacyMap = [
            'normal' => ['collapse', 'wrap'],
            'pre' => ['preserve', 'nowrap'],
            'pre-wrap' => ['preserve', 'wrap'],
            'pre-line' => ['preserve-breaks', 'wrap'],
            'nowrap' => ['collapse', 'nowrap'],
            'break-spaces' => ['break-spaces', 'wrap'],
        ];
        $collapseKw = ['collapse', 'preserve', 'preserve-breaks', 'preserve-spaces', 'break-spaces'];
        $modeKw = ['wrap', 'nowrap'];

        $components = $this->toComponents($value);
        $collapse = null;
        $mode = null;
        if (count($components) === 1 && $components[0] instanceof Keyword) {
            $name = strtolower($components[0]->name);
            if (isset($legacyMap[$name])) {
                [$collapseName, $modeName] = $legacyMap[$name];
                $collapse = new Keyword($collapseName);
                $mode = new Keyword($modeName);
            }
        }
        if ($collapse === null && $mode === null) {
            // Two-keyword path; pick one of each by membership.
            foreach ($components as $c) {
                if (!($c instanceof Keyword)) {
                    continue;
                }
                $lc = strtolower($c->name);
                if ($collapse === null && in_array($lc, $collapseKw, true)) {
                    $collapse = $c;
                    continue;
                }
                if ($mode === null && in_array($lc, $modeKw, true)) {
                    $mode = $c;
                }
            }
        }
        $out = [
            // Preserve the original shorthand value too — some
            // downstream code reads `white-space` directly.
            'white-space' => $value,
        ];
        if ($collapse !== null) {
            $out['white-space-collapse'] = $collapse;
        }
        if ($mode !== null) {
            $out['text-wrap-mode'] = $mode;
        }
        return $out;
    }

    /**
     * CSS Text 4 §11 — `text-wrap` shorthand for
     * `text-wrap-mode` + `text-wrap-style`. Components may appear
     * in any order; each routes to its own longhand by keyword.
     *
     *   text-wrap-mode:  wrap | nowrap
     *   text-wrap-style: auto | balance | pretty | stable
     *
     * @return array<string, Value>
     */
    private function expandTextWrap(Value $value): array
    {
        $modeKw = ['wrap', 'nowrap'];
        $styleKw = ['auto', 'balance', 'pretty', 'stable'];
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        $mode = null;
        $style = null;
        foreach ($components as $c) {
            if (!($c instanceof Keyword)) {
                continue;
            }
            $lc = strtolower($c->name);
            if ($mode === null && in_array($lc, $modeKw, true)) {
                $mode = $c;
                continue;
            }
            if ($style === null && in_array($lc, $styleKw, true)) {
                $style = $c;
            }
        }
        $out = [];
        if ($mode !== null) {
            $out['text-wrap-mode'] = $mode;
        }
        if ($style !== null) {
            $out['text-wrap-style'] = $style;
        }
        return $out;
    }

    /**
     * Recognise a value that can serve as a mask source: a URL,
     * an image-set, a gradient (any typed Gradient subclass), or
     * the `none` keyword (which clears any earlier source).
     */
    private function looksLikeMaskImage(Value $v): bool
    {
        return $v instanceof \Phpdftk\Css\Value\Url
            || $v instanceof \Phpdftk\Css\Value\ImageSet
            || $v instanceof \Phpdftk\Css\Value\Gradient
            || ($v instanceof Keyword && strtolower($v->name) === 'none');
    }

    /**
     * CSS Text Decoration 4 §8.6 — `text-emphasis` shorthand for
     * `text-emphasis-style` + `text-emphasis-color`. Components
     * may appear in any order; the color component is distinguished
     * by being a Color value (typed) or `currentcolor` keyword.
     *
     * @return array<string, Value>
     */
    private function expandTextEmphasis(Value $value): array
    {
        $components = $this->toComponents($value);
        if ($components === []) {
            return [];
        }
        $style = null;
        $color = null;
        foreach ($components as $c) {
            if ($color === null && $this->isColorComponent($c)) {
                $color = $c;
                continue;
            }
            $style ??= $c;
        }
        $out = [];
        if ($style !== null) {
            $out['text-emphasis-style'] = $style;
        }
        if ($color !== null) {
            $out['text-emphasis-color'] = $color;
        }
        return $out;
    }

    private function isColorComponent(Value $v): bool
    {
        if ($v instanceof Color) {
            return true;
        }
        if ($v instanceof Keyword) {
            $lc = strtolower($v->name);
            return $lc === 'currentcolor' || $lc === 'transparent';
        }
        return false;
    }

    private function looksLikeFontSize(Value $v): bool
    {
        if ($v instanceof Length || $v instanceof Percentage) {
            return true;
        }
        if ($v instanceof Keyword) {
            return in_array(strtolower($v->name), [
                'xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large',
                'larger', 'smaller',
            ], true);
        }
        return false;
    }

    /**
     * CSS Transitions 1 §3.2 — `transition` is per-property:
     *
     *   transition: <property> <duration> <timing-function> <delay>
     *
     * Components may appear in any order. The first time-valued
     * component sets `transition-duration`, the second sets
     * `transition-delay`; any easing keyword/function sets
     * `transition-timing-function`; any non-time, non-easing
     * non-keyword ident sets `transition-property` (or `all` as
     * default). Multiple comma-separated layers are supported —
     * each layer's longhands form a comma-joined list per spec.
     *
     * @return array<string, Value>
     */
    private function expandTransition(Value $value): array
    {
        $layers = $this->toCommaLayers($value);
        $properties = [];
        $durations = [];
        $timings = [];
        $delays = [];
        foreach ($layers as $layer) {
            $components = $this->toComponents($layer);
            $property = null;
            $duration = null;
            $timing = null;
            $delay = null;
            foreach ($components as $c) {
                if ($c instanceof \Phpdftk\Css\Value\Time) {
                    if ($duration === null) {
                        $duration = $c;
                    } elseif ($delay === null) {
                        $delay = $c;
                    }
                    continue;
                }
                if ($this->isEasingValue($c)) {
                    $timing ??= $c;
                    continue;
                }
                if ($c instanceof Keyword) {
                    $property ??= $c;
                }
            }
            $properties[] = $property ?? new Keyword('all');
            $durations[] = $duration ?? new Keyword('0s');
            $timings[] = $timing ?? new Keyword('ease');
            $delays[] = $delay ?? new Keyword('0s');
        }
        return [
            'transition-property' => $this->joinComma($properties),
            'transition-duration' => $this->joinComma($durations),
            'transition-timing-function' => $this->joinComma($timings),
            'transition-delay' => $this->joinComma($delays),
        ];
    }

    /**
     * CSS Animations 1 §4.10 — `animation` is per-instance:
     *
     *   animation: <duration> <timing-function> <delay>
     *              <iteration-count> <direction> <fill-mode>
     *              <play-state> <name>
     *
     * Same any-order policy as `transition`: first time → duration,
     * second time → delay, easing → timing-function, number →
     * iteration-count, recognised keywords → direction / fill-mode /
     * play-state, remaining ident → name. Multi-layer (comma)
     * support.
     *
     * @return array<string, Value>
     */
    private function expandAnimation(Value $value): array
    {
        $layers = $this->toCommaLayers($value);
        $names = [];
        $durations = [];
        $timings = [];
        $delays = [];
        $iterations = [];
        $directions = [];
        $fillModes = [];
        $playStates = [];
        $directionKw = ['normal', 'reverse', 'alternate', 'alternate-reverse'];
        $fillModeKw = ['none', 'forwards', 'backwards', 'both'];
        $playStateKw = ['running', 'paused'];
        foreach ($layers as $layer) {
            $components = $this->toComponents($layer);
            $name = null;
            $duration = null;
            $timing = null;
            $delay = null;
            $iter = null;
            $direction = null;
            $fillMode = null;
            $playState = null;
            foreach ($components as $c) {
                if ($c instanceof \Phpdftk\Css\Value\Time) {
                    if ($duration === null) {
                        $duration = $c;
                    } elseif ($delay === null) {
                        $delay = $c;
                    }
                    continue;
                }
                if ($c instanceof \Phpdftk\Css\Value\Number
                    || $c instanceof \Phpdftk\Css\Value\Integer
                ) {
                    $iter ??= $c;
                    continue;
                }
                if ($c instanceof Keyword && strtolower($c->name) === 'infinite') {
                    $iter ??= $c;
                    continue;
                }
                if ($this->isEasingValue($c)) {
                    $timing ??= $c;
                    continue;
                }
                if ($c instanceof Keyword) {
                    $lc = strtolower($c->name);
                    if ($direction === null && in_array($lc, $directionKw, true)) {
                        $direction = $c;
                        continue;
                    }
                    if ($fillMode === null && in_array($lc, $fillModeKw, true)) {
                        $fillMode = $c;
                        continue;
                    }
                    if ($playState === null && in_array($lc, $playStateKw, true)) {
                        $playState = $c;
                        continue;
                    }
                    $name ??= $c;
                }
            }
            $names[] = $name ?? new Keyword('none');
            $durations[] = $duration ?? new Keyword('0s');
            $timings[] = $timing ?? new Keyword('ease');
            $delays[] = $delay ?? new Keyword('0s');
            $iterations[] = $iter ?? new \Phpdftk\Css\Value\Number(1);
            $directions[] = $direction ?? new Keyword('normal');
            $fillModes[] = $fillMode ?? new Keyword('none');
            $playStates[] = $playState ?? new Keyword('running');
        }
        return [
            'animation-name' => $this->joinComma($names),
            'animation-duration' => $this->joinComma($durations),
            'animation-timing-function' => $this->joinComma($timings),
            'animation-delay' => $this->joinComma($delays),
            'animation-iteration-count' => $this->joinComma($iterations),
            'animation-direction' => $this->joinComma($directions),
            'animation-fill-mode' => $this->joinComma($fillModes),
            'animation-play-state' => $this->joinComma($playStates),
        ];
    }

    /**
     * Split a top-level comma list into per-layer values. A single
     * non-list value yields one layer.
     *
     * @return list<Value>
     */
    private function toCommaLayers(Value $value): array
    {
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Comma
        ) {
            return $value->values;
        }
        return [$value];
    }

    /**
     * Join a list of per-layer longhand values into a comma-
     * separated ValueList (or pass through the single value).
     *
     * @param list<Value> $values
     */
    private function joinComma(array $values): Value
    {
        if (count($values) === 1) {
            return $values[0];
        }
        return new \Phpdftk\Css\Value\ValueList(
            $values,
            \Phpdftk\Css\Value\ListSeparator::Comma,
        );
    }

    /**
     * Recognise easing forms — both the named keywords and the
     * typed function-value classes that the value parser lifts
     * from cubic-bezier() / steps() / linear().
     */
    private function isEasingValue(Value $v): bool
    {
        if ($v instanceof Keyword) {
            return in_array(strtolower($v->name), [
                'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out',
                'step-start', 'step-end',
            ], true);
        }
        if ($v instanceof \Phpdftk\Css\Value\CubicBezier
            || $v instanceof \Phpdftk\Css\Value\StepsEasing
            || $v instanceof \Phpdftk\Css\Value\LinearEasing
        ) {
            return true;
        }
        return false;
    }
}
