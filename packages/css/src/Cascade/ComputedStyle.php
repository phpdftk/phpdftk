<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LightDark;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\Url;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\ValueList;

/**
 * Typed accessor surface over a per-element {@see CascadedValues} bag
 * per `docs/plans/contracts.md` §1D.5. Each getter narrows the
 * underlying `Value` to the type(s) the property's grammar permits;
 * properties not explicitly set fall back to the registry's initial
 * value (via `CascadedValues::get()`).
 *
 * The class is intentionally a thin wrapper — it adds no caching or
 * computation of its own. The cascade resolves percentages, inherits
 * values, and produces the initial-value fallbacks; ComputedStyle
 * just exposes them with PHP-static-analysable signatures so
 * downstream consumers (the box generator, painter, SVG renderer)
 * don't have to type-check every `?Value` themselves.
 *
 * **Type narrowing fallback.** When a property's parsed value doesn't
 * match its declared type (an invalid `font-weight: foo` slipping
 * through), the getter returns the property's initial value instead
 * of throwing — keeping the accessor surface infallible at the cost
 * of swallowing parser errors that should have been caught earlier.
 */
final readonly class ComputedStyle
{
    public function __construct(private CascadedValues $values) {}

    // ========================================================================
    // Generic access (escape hatches per contracts.md)
    // ========================================================================

    public function get(string $property): ?Value
    {
        return $this->values->get($property);
    }

    public function has(string $property): bool
    {
        return $this->values->has($property);
    }

    /** @return array<string, Value> */
    public function all(): array
    {
        return $this->values->all();
    }

    public function getCustomProperty(string $name): ?Value
    {
        $name = str_starts_with($name, '--') ? $name : '--' . $name;
        return $this->values->get($name);
    }

    public function getUnknown(string $name): ?Value
    {
        return $this->values->get($name);
    }

    // ========================================================================
    // Color & background
    // ========================================================================

    public function getColor(): Color
    {
        return $this->expectColor('color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getBackgroundColor(): Color|Keyword
    {
        return $this->expectColorOrKeyword('background-color', 'transparent');
    }

    public function getBackgroundImage(): Value
    {
        return $this->values->get('background-image') ?? new Keyword('none');
    }

    public function getBackgroundRepeat(): Keyword
    {
        return $this->expectKeyword('background-repeat', 'repeat');
    }

    public function getBackgroundPosition(): Value
    {
        return $this->values->get('background-position') ?? new Keyword('0% 0%');
    }

    public function getBackgroundSize(): Value
    {
        return $this->values->get('background-size') ?? new Keyword('auto');
    }

    public function getBackgroundAttachment(): Keyword
    {
        return $this->expectKeyword('background-attachment', 'scroll');
    }

    public function getBackgroundOrigin(): Keyword
    {
        return $this->expectKeyword('background-origin', 'padding-box');
    }

    public function getBackgroundClip(): Keyword
    {
        return $this->expectKeyword('background-clip', 'border-box');
    }

    public function getOpacity(): Number
    {
        return $this->expectNumber('opacity', 1.0);
    }

    // ========================================================================
    // Font & text
    // ========================================================================

    public function getFontFamily(): Value
    {
        return $this->values->get('font-family') ?? new Keyword('serif');
    }

    public function getFontSize(): Length
    {
        return $this->expectLength('font-size', 16.0);
    }

    public function getFontStyle(): Keyword
    {
        return $this->expectKeyword('font-style', 'normal');
    }

    public function getFontWeight(): Integer|Keyword
    {
        $v = $this->values->get('font-weight');
        return $v instanceof Integer || $v instanceof Keyword ? $v : new Keyword('normal');
    }

    public function getLineHeight(): Length|Number|Keyword
    {
        $v = $this->values->get('line-height');
        return $v instanceof Length || $v instanceof Number || $v instanceof Keyword
            ? $v
            : new Keyword('normal');
    }

    public function getTextAlign(): Keyword
    {
        return $this->expectKeyword('text-align', 'start');
    }

    public function getTextAlignLast(): Keyword
    {
        return $this->expectKeyword('text-align-last', 'auto');
    }

    public function getTextDecorationLine(): Keyword
    {
        return $this->expectKeyword('text-decoration-line', 'none');
    }

    public function getTextDecorationStyle(): Keyword
    {
        return $this->expectKeyword('text-decoration-style', 'solid');
    }

    public function getTextDecorationColor(): Color
    {
        return $this->expectColor('text-decoration-color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getTextDecorationThickness(): Length|Keyword
    {
        return $this->expectLengthOrKeyword('text-decoration-thickness', 'auto');
    }

    public function getTextTransform(): Keyword
    {
        return $this->expectKeyword('text-transform', 'none');
    }

    public function getTextIndent(): Length
    {
        return $this->expectLength('text-indent', 0.0);
    }

    public function getTextJustify(): Keyword
    {
        return $this->expectKeyword('text-justify', 'auto');
    }

    public function getLetterSpacing(): Length|Keyword
    {
        return $this->expectLengthOrKeyword('letter-spacing', 'normal');
    }

    public function getWordSpacing(): Length|Keyword
    {
        return $this->expectLengthOrKeyword('word-spacing', 'normal');
    }

    public function getWhiteSpace(): Keyword
    {
        return $this->expectKeyword('white-space', 'normal');
    }

    public function getWordBreak(): Keyword
    {
        return $this->expectKeyword('word-break', 'normal');
    }

    public function getOverflowWrap(): Keyword
    {
        return $this->expectKeyword('overflow-wrap', 'normal');
    }

    public function getVerticalAlign(): Length|Keyword
    {
        return $this->expectLengthOrKeyword('vertical-align', 'baseline');
    }

    public function getDirection(): Keyword
    {
        return $this->expectKeyword('direction', 'ltr');
    }

    public function getUnicodeBidi(): Keyword
    {
        return $this->expectKeyword('unicode-bidi', 'normal');
    }

    public function getTabSize(): Length|Integer
    {
        $v = $this->values->get('tab-size');
        return $v instanceof Length || $v instanceof Integer ? $v : new Integer(8);
    }

    public function getQuotes(): Value
    {
        return $this->values->get('quotes') ?? new Keyword('auto');
    }

    // ========================================================================
    // Box model
    // ========================================================================

    public function getDisplay(): Keyword
    {
        return $this->expectKeyword('display', 'inline');
    }

    public function getPosition(): Keyword
    {
        return $this->expectKeyword('position', 'static');
    }

    public function getTop(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('top', 'auto');
    }

    public function getRight(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('right', 'auto');
    }

    public function getBottom(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('bottom', 'auto');
    }

    public function getLeft(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('left', 'auto');
    }

    public function getZIndex(): Integer|Keyword
    {
        $v = $this->values->get('z-index');
        return $v instanceof Integer || $v instanceof Keyword ? $v : new Keyword('auto');
    }

    public function getWidth(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('width', 'auto');
    }

    public function getHeight(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('height', 'auto');
    }

    public function getMinWidth(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('min-width', 'auto');
    }

    public function getMinHeight(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('min-height', 'auto');
    }

    public function getMaxWidth(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('max-width', 'none');
    }

    public function getMaxHeight(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('max-height', 'none');
    }

    public function getMarginTop(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('margin-top', 'auto');
    }

    public function getMarginRight(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('margin-right', 'auto');
    }

    public function getMarginBottom(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('margin-bottom', 'auto');
    }

    public function getMarginLeft(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('margin-left', 'auto');
    }

    public function getPaddingTop(): Length|Percentage
    {
        return $this->expectLengthOrPercentage('padding-top', 0.0);
    }

    public function getPaddingRight(): Length|Percentage
    {
        return $this->expectLengthOrPercentage('padding-right', 0.0);
    }

    public function getPaddingBottom(): Length|Percentage
    {
        return $this->expectLengthOrPercentage('padding-bottom', 0.0);
    }

    public function getPaddingLeft(): Length|Percentage
    {
        return $this->expectLengthOrPercentage('padding-left', 0.0);
    }

    public function getBorderTopWidth(): Length
    {
        return $this->expectLength('border-top-width', 0.0);
    }

    public function getBorderRightWidth(): Length
    {
        return $this->expectLength('border-right-width', 0.0);
    }

    public function getBorderBottomWidth(): Length
    {
        return $this->expectLength('border-bottom-width', 0.0);
    }

    public function getBorderLeftWidth(): Length
    {
        return $this->expectLength('border-left-width', 0.0);
    }

    public function getBorderTopStyle(): Keyword
    {
        return $this->expectKeyword('border-top-style', 'none');
    }

    public function getBorderRightStyle(): Keyword
    {
        return $this->expectKeyword('border-right-style', 'none');
    }

    public function getBorderBottomStyle(): Keyword
    {
        return $this->expectKeyword('border-bottom-style', 'none');
    }

    public function getBorderLeftStyle(): Keyword
    {
        return $this->expectKeyword('border-left-style', 'none');
    }

    public function getBorderTopColor(): Color
    {
        return $this->expectColor('border-top-color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getBorderRightColor(): Color
    {
        return $this->expectColor('border-right-color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getBorderBottomColor(): Color
    {
        return $this->expectColor('border-bottom-color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getBorderLeftColor(): Color
    {
        return $this->expectColor('border-left-color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getBorderTopLeftRadius(): Value
    {
        return $this->values->get('border-top-left-radius') ?? new Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px);
    }

    public function getBorderTopRightRadius(): Value
    {
        return $this->values->get('border-top-right-radius') ?? new Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px);
    }

    public function getBorderBottomLeftRadius(): Value
    {
        return $this->values->get('border-bottom-left-radius') ?? new Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px);
    }

    public function getBorderBottomRightRadius(): Value
    {
        return $this->values->get('border-bottom-right-radius') ?? new Length(0.0, \Phpdftk\Css\Value\LengthUnit::Px);
    }

    public function getBoxSizing(): Keyword
    {
        return $this->expectKeyword('box-sizing', 'content-box');
    }

    public function getBoxShadow(): Value
    {
        return $this->values->get('box-shadow') ?? new Keyword('none');
    }

    public function getOverflow(): Keyword
    {
        return $this->expectKeyword('overflow', 'visible');
    }

    public function getOverflowX(): Keyword
    {
        return $this->expectKeyword('overflow-x', 'visible');
    }

    public function getOverflowY(): Keyword
    {
        return $this->expectKeyword('overflow-y', 'visible');
    }

    public function getVisibility(): Keyword
    {
        return $this->expectKeyword('visibility', 'visible');
    }

    public function getOutlineWidth(): Length
    {
        return $this->expectLength('outline-width', 0.0);
    }

    public function getOutlineStyle(): Keyword
    {
        return $this->expectKeyword('outline-style', 'none');
    }

    public function getOutlineColor(): Color
    {
        return $this->expectColor('outline-color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getOutlineOffset(): Length
    {
        return $this->expectLength('outline-offset', 0.0);
    }

    public function getFloat(): Keyword
    {
        return $this->expectKeyword('float', 'none');
    }

    public function getClear(): Keyword
    {
        return $this->expectKeyword('clear', 'none');
    }

    public function getAspectRatio(): Value
    {
        return $this->values->get('aspect-ratio') ?? new Keyword('auto');
    }

    // ========================================================================
    // Flex
    // ========================================================================

    public function getFlexDirection(): Keyword
    {
        return $this->expectKeyword('flex-direction', 'row');
    }

    public function getFlexWrap(): Keyword
    {
        return $this->expectKeyword('flex-wrap', 'nowrap');
    }

    public function getJustifyContent(): Keyword
    {
        return $this->expectKeyword('justify-content', 'flex-start');
    }

    public function getAlignItems(): Keyword
    {
        return $this->expectKeyword('align-items', 'stretch');
    }

    public function getAlignContent(): Keyword
    {
        return $this->expectKeyword('align-content', 'stretch');
    }

    public function getAlignSelf(): Keyword
    {
        return $this->expectKeyword('align-self', 'auto');
    }

    public function getFlexGrow(): Number
    {
        return $this->expectNumber('flex-grow', 0.0);
    }

    public function getFlexShrink(): Number
    {
        return $this->expectNumber('flex-shrink', 1.0);
    }

    public function getFlexBasis(): Length|Percentage|Keyword
    {
        return $this->expectLengthPercentageOrKeyword('flex-basis', 'auto');
    }

    public function getOrder(): Integer
    {
        $v = $this->values->get('order');
        return $v instanceof Integer ? $v : new Integer(0);
    }

    public function getRowGap(): Length|Keyword
    {
        return $this->expectLengthOrKeyword('row-gap', 'normal');
    }

    public function getColumnGap(): Length|Keyword
    {
        return $this->expectLengthOrKeyword('column-gap', 'normal');
    }

    // ========================================================================
    // Tables
    // ========================================================================

    public function getBorderCollapse(): Keyword
    {
        return $this->expectKeyword('border-collapse', 'separate');
    }

    public function getCaptionSide(): Keyword
    {
        return $this->expectKeyword('caption-side', 'top');
    }

    // ========================================================================
    // Lists
    // ========================================================================

    public function getListStyleType(): Keyword|CssFunction
    {
        $v = $this->values->get('list-style-type');
        return $v instanceof Keyword || $v instanceof CssFunction
            ? $v
            : new Keyword('disc');
    }

    public function getListStylePosition(): Keyword
    {
        return $this->expectKeyword('list-style-position', 'outside');
    }

    public function getListStyleImage(): Value
    {
        return $this->values->get('list-style-image') ?? new Keyword('none');
    }

    // ========================================================================
    // Paged media
    // ========================================================================

    public function getPage(): Keyword|CssFunction
    {
        $v = $this->values->get('page');
        return $v instanceof Keyword || $v instanceof CssFunction
            ? $v
            : new Keyword('auto');
    }

    public function getBreakBefore(): Keyword
    {
        return $this->expectKeyword('break-before', 'auto');
    }

    public function getBreakAfter(): Keyword
    {
        return $this->expectKeyword('break-after', 'auto');
    }

    public function getBreakInside(): Keyword
    {
        return $this->expectKeyword('break-inside', 'auto');
    }

    public function getBoxDecorationBreak(): Keyword
    {
        return $this->expectKeyword('box-decoration-break', 'slice');
    }

    public function getOrphans(): Integer
    {
        $v = $this->values->get('orphans');
        return $v instanceof Integer ? $v : new Integer(2);
    }

    public function getWidows(): Integer
    {
        $v = $this->values->get('widows');
        return $v instanceof Integer ? $v : new Integer(2);
    }

    // ========================================================================
    // Multi-column
    // ========================================================================

    public function getColumnCount(): Integer|Keyword
    {
        $v = $this->values->get('column-count');
        return $v instanceof Integer || $v instanceof Keyword ? $v : new Keyword('auto');
    }

    public function getColumnWidth(): Length|Keyword
    {
        return $this->expectLengthOrKeyword('column-width', 'auto');
    }

    public function getColumnRuleWidth(): Length
    {
        return $this->expectLength('column-rule-width', 0.0);
    }

    public function getColumnRuleStyle(): Keyword
    {
        return $this->expectKeyword('column-rule-style', 'none');
    }

    public function getColumnRuleColor(): Color
    {
        return $this->expectColor('column-rule-color', new Color(0.0, 0.0, 0.0, 1.0));
    }

    public function getColumnSpan(): Keyword
    {
        return $this->expectKeyword('column-span', 'none');
    }

    public function getColumnFill(): Keyword
    {
        return $this->expectKeyword('column-fill', 'balance');
    }

    // ========================================================================
    // Effects
    // ========================================================================

    public function getTextShadow(): Value
    {
        return $this->values->get('text-shadow') ?? new Keyword('none');
    }

    public function getClipPath(): Value
    {
        return $this->values->get('clip-path') ?? new Keyword('none');
    }

    public function getFilter(): Value
    {
        return $this->values->get('filter') ?? new Keyword('none');
    }

    public function getTransform(): Value
    {
        return $this->values->get('transform') ?? new Keyword('none');
    }

    // ========================================================================
    // Type-narrowing helpers
    // ========================================================================

    private function expectKeyword(string $prop, string $fallback): Keyword
    {
        $v = $this->values->get($prop);
        return $v instanceof Keyword ? $v : new Keyword($fallback);
    }

    private function expectColor(string $prop, Color $fallback): Color
    {
        $v = $this->resolveLightDark($this->values->get($prop));
        return $v instanceof Color ? $v : $fallback;
    }

    private function expectColorOrKeyword(string $prop, string $keywordFallback): Color|Keyword
    {
        $v = $this->resolveLightDark($this->values->get($prop));
        return $v instanceof Color || $v instanceof Keyword ? $v : new Keyword($keywordFallback);
    }

    /**
     * CSS Color 5 §5 — pick the active branch of a `light-dark()`
     * call at computed-value time based on the cascaded
     * `color-scheme` property (CSS Color Adjustment 1 §2.2):
     *
     *   - if `color-scheme` lists `dark` and does not list `light`,
     *     the document opts into the dark branch;
     *   - otherwise the light branch wins (the document default).
     *
     * The original {@see LightDark} value is preserved on the
     * declaration so a future re-render under a different scheme
     * is a value-level switch, not a re-cascade.
     */
    private function resolveLightDark(?Value $v): ?Value
    {
        if (!$v instanceof LightDark) {
            return $v;
        }
        return $this->prefersDarkScheme() ? $v->dark : $v->light;
    }

    private function prefersDarkScheme(): bool
    {
        $scheme = $this->values->get('color-scheme');
        $hasLight = false;
        $hasDark = false;
        foreach ($this->extractSchemeIdents($scheme) as $ident) {
            $lc = strtolower($ident);
            if ($lc === 'light') {
                $hasLight = true;
            } elseif ($lc === 'dark') {
                $hasDark = true;
            }
        }
        return $hasDark && !$hasLight;
    }

    /**
     * @return list<string>
     */
    private function extractSchemeIdents(?Value $value): array
    {
        if ($value instanceof Keyword) {
            return [$value->name];
        }
        if ($value instanceof ValueList) {
            $out = [];
            foreach ($value->values as $v) {
                if ($v instanceof Keyword) {
                    $out[] = $v->name;
                }
            }
            return $out;
        }
        return [];
    }

    private function expectLength(string $prop, float $fallbackPx): Length
    {
        $v = $this->values->get($prop);
        return $v instanceof Length ? $v : new Length($fallbackPx, \Phpdftk\Css\Value\LengthUnit::Px);
    }

    private function expectLengthOrKeyword(string $prop, string $keywordFallback): Length|Keyword
    {
        $v = $this->values->get($prop);
        return $v instanceof Length || $v instanceof Keyword ? $v : new Keyword($keywordFallback);
    }

    private function expectLengthOrPercentage(string $prop, float $fallbackPx): Length|Percentage
    {
        $v = $this->values->get($prop);
        return $v instanceof Length || $v instanceof Percentage
            ? $v
            : new Length($fallbackPx, \Phpdftk\Css\Value\LengthUnit::Px);
    }

    private function expectLengthPercentageOrKeyword(string $prop, string $keywordFallback): Length|Percentage|Keyword
    {
        $v = $this->values->get($prop);
        return $v instanceof Length || $v instanceof Percentage || $v instanceof Keyword
            ? $v
            : new Keyword($keywordFallback);
    }

    private function expectNumber(string $prop, float $fallback): Number
    {
        $v = $this->values->get($prop);
        return $v instanceof Number ? $v : new Number($fallback);
    }
}
