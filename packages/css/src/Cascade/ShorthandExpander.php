<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

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
            default => [$property => $value],
        };
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
            if ($color === null && $c instanceof \Phpdftk\Css\Value\Color) {
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
        return $v instanceof \Phpdftk\Css\Value\Color;
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
        $positionParts = [];
        foreach ($components as $c) {
            if ($this->looksLikeColor($c)) {
                $color = $c;
                continue;
            }
            if ($c instanceof \Phpdftk\Css\Value\Url) {
                $image = $c;
                continue;
            }
            if ($c instanceof Keyword) {
                $lower = strtolower($c->name);
                if (in_array($lower, ['repeat', 'no-repeat', 'repeat-x', 'repeat-y', 'round', 'space'], true)) {
                    $repeat = $c;
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
        foreach ($components as $c) {
            if ($c instanceof Keyword) {
                $lower = strtolower($c->name);
                if (in_array($lower, ['underline', 'overline', 'line-through', 'blink', 'none'], true)) {
                    $lineParts[] = $c;
                    continue;
                }
                if (in_array($lower, ['solid', 'double', 'dotted', 'dashed', 'wavy'], true)) {
                    $style = $c;
                    continue;
                }
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
        return $out;
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
}
