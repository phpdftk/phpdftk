<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Cascade\ShorthandExpander;
use Phpdftk\Css\Parser;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use PHPUnit\Framework\TestCase;

final class ShorthandExpanderTest extends TestCase
{
    private ShorthandExpander $expander;
    private Parser $parser;
    private Cascade $cascade;

    protected function setUp(): void
    {
        $this->expander = new ShorthandExpander();
        $this->parser = new Parser();
        $this->cascade = new Cascade(PropertyRegistry::default());
    }

    private function value(string $css): \Phpdftk\Css\Value\Value
    {
        return $this->parser->parseValue($css);
    }

    public function testMarginOneValueExpandsToAllFour(): void
    {
        $out = $this->expander->expand('margin', $this->value('10px'));
        self::assertArrayHasKey('margin-top', $out);
        self::assertArrayHasKey('margin-right', $out);
        self::assertArrayHasKey('margin-bottom', $out);
        self::assertArrayHasKey('margin-left', $out);
        foreach ($out as $value) {
            self::assertInstanceOf(Length::class, $value);
            self::assertSame(10.0, $value->value);
        }
    }

    public function testMarginTwoValuesExpands(): void
    {
        $out = $this->expander->expand('margin', $this->value('10px 5px'));
        self::assertSame(10.0, $out['margin-top']->value);
        self::assertSame(5.0, $out['margin-right']->value);
        self::assertSame(10.0, $out['margin-bottom']->value);
        self::assertSame(5.0, $out['margin-left']->value);
    }

    public function testMarginThreeValuesExpands(): void
    {
        $out = $this->expander->expand('margin', $this->value('10px 5px 20px'));
        self::assertSame(10.0, $out['margin-top']->value);
        self::assertSame(5.0, $out['margin-right']->value);
        self::assertSame(20.0, $out['margin-bottom']->value);
        self::assertSame(5.0, $out['margin-left']->value);
    }

    public function testMarginFourValuesExpands(): void
    {
        $out = $this->expander->expand('margin', $this->value('10px 5px 20px 0px'));
        self::assertSame(10.0, $out['margin-top']->value);
        self::assertSame(5.0, $out['margin-right']->value);
        self::assertSame(20.0, $out['margin-bottom']->value);
        self::assertSame(0.0, $out['margin-left']->value);
    }

    public function testPaddingExpansion(): void
    {
        $out = $this->expander->expand('padding', $this->value('8px'));
        self::assertSame(8.0, $out['padding-top']->value);
        self::assertSame(8.0, $out['padding-left']->value);
    }

    public function testBorderWidthExpansion(): void
    {
        $out = $this->expander->expand('border-width', $this->value('2px 4px'));
        self::assertSame(2.0, $out['border-top-width']->value);
        self::assertSame(4.0, $out['border-right-width']->value);
        self::assertSame(2.0, $out['border-bottom-width']->value);
        self::assertSame(4.0, $out['border-left-width']->value);
    }

    public function testBorderStyleExpansion(): void
    {
        $out = $this->expander->expand('border-style', $this->value('solid dashed'));
        self::assertInstanceOf(Keyword::class, $out['border-top-style']);
        self::assertSame('solid', $out['border-top-style']->name);
        self::assertSame('dashed', $out['border-right-style']->name);
    }

    public function testBorderSideShorthand(): void
    {
        $out = $this->expander->expand('border-top', $this->value('1px solid red'));
        self::assertArrayHasKey('border-top-width', $out);
        self::assertArrayHasKey('border-top-style', $out);
        self::assertArrayHasKey('border-top-color', $out);
        self::assertSame(1.0, $out['border-top-width']->value);
        self::assertSame('solid', $out['border-top-style']->name);
        self::assertInstanceOf(Color::class, $out['border-top-color']);
    }

    public function testBorderShorthandExpandsToAllSides(): void
    {
        $out = $this->expander->expand('border', $this->value('2px solid red'));
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            self::assertArrayHasKey("border-$side-width", $out);
            self::assertArrayHasKey("border-$side-style", $out);
            self::assertArrayHasKey("border-$side-color", $out);
            self::assertSame(2.0, $out["border-$side-width"]->value);
            self::assertSame('solid', $out["border-$side-style"]->name);
        }
    }

    public function testBorderComponentOrderIsFree(): void
    {
        $out = $this->expander->expand('border', $this->value('solid red 3px'));
        self::assertSame(3.0, $out['border-top-width']->value);
        self::assertSame('solid', $out['border-top-style']->name);
        self::assertInstanceOf(Color::class, $out['border-top-color']);
    }

    public function testUnknownShorthandPassesThrough(): void
    {
        $value = $this->value('foo');
        $out = $this->expander->expand('something-else', $value);
        self::assertSame(['something-else' => $value], $out);
    }

    public function testFontShorthandSizeFamily(): void
    {
        $out = $this->expander->expand('font', $this->value('16px Arial'));
        self::assertInstanceOf(Length::class, $out['font-size']);
        self::assertSame(16.0, $out['font-size']->value);
        self::assertSame('arial', $out['font-family']->name);
    }

    public function testFontShorthandStyleWeightSizeFamily(): void
    {
        $out = $this->expander->expand('font', $this->value('italic bold 14pt Helvetica'));
        self::assertSame('italic', $out['font-style']->name);
        self::assertSame('bold', $out['font-weight']->name);
        self::assertSame(14.0, $out['font-size']->value);
        self::assertSame('helvetica', $out['font-family']->name);
    }

    public function testFontShorthandWithLineHeight(): void
    {
        $out = $this->expander->expand('font', $this->value('16px/1.5 sans-serif'));
        self::assertSame(16.0, $out['font-size']->value);
        $lh = $out['line-height'];
        self::assertSame(1.5, $lh instanceof \Phpdftk\Css\Value\Number ? $lh->value : null);
        self::assertSame('sans-serif', $out['font-family']->name);
    }

    public function testFontShorthandCommaSeparatedFamilies(): void
    {
        $out = $this->expander->expand('font', $this->value('16px Arial, sans-serif'));
        self::assertInstanceOf(\Phpdftk\Css\Value\ValueList::class, $out['font-family']);
        self::assertCount(2, $out['font-family']->values);
    }

    public function testFontShorthandFullForm(): void
    {
        $out = $this->expander->expand('font', $this->value('italic small-caps bold condensed 12px/1.4 Georgia, serif'));
        self::assertSame('italic', $out['font-style']->name);
        self::assertSame('small-caps', $out['font-variant']->name);
        self::assertSame('bold', $out['font-weight']->name);
        self::assertSame('condensed', $out['font-stretch']->name);
        self::assertSame(12.0, $out['font-size']->value);
        $lh = $out['line-height'];
        self::assertSame(1.4, $lh instanceof \Phpdftk\Css\Value\Number ? $lh->value : null);
        self::assertCount(2, $out['font-family']->values);
    }

    public function testFontShorthandNumericWeight(): void
    {
        $out = $this->expander->expand('font', $this->value('300 16px sans-serif'));
        $w = $out['font-weight'];
        // 300 may parse as Integer or Number depending on whether it has a decimal.
        $value = $w instanceof \Phpdftk\Css\Value\Integer ? $w->value
            : ($w instanceof \Phpdftk\Css\Value\Number ? (int) $w->value : null);
        self::assertSame(300, $value);
    }

    public function testFontShorthandSizeKeyword(): void
    {
        $out = $this->expander->expand('font', $this->value('large Verdana'));
        self::assertInstanceOf(Keyword::class, $out['font-size']);
        self::assertSame('large', $out['font-size']->name);
    }

    public function testListStyleTypeKeyword(): void
    {
        $out = $this->expander->expand('list-style', $this->value('square'));
        self::assertSame('square', $out['list-style-type']->name);
        self::assertArrayNotHasKey('list-style-position', $out);
        self::assertArrayNotHasKey('list-style-image', $out);
    }

    public function testListStyleTypeAndPosition(): void
    {
        $out = $this->expander->expand('list-style', $this->value('decimal inside'));
        self::assertSame('decimal', $out['list-style-type']->name);
        self::assertSame('inside', $out['list-style-position']->name);
    }

    public function testListStyleWithImage(): void
    {
        $out = $this->expander->expand('list-style', $this->value('url(dot.png) outside circle'));
        self::assertInstanceOf(\Phpdftk\Css\Value\Url::class, $out['list-style-image']);
        self::assertSame('outside', $out['list-style-position']->name);
        self::assertSame('circle', $out['list-style-type']->name);
    }

    public function testListStyleNoneAssignsToType(): void
    {
        $out = $this->expander->expand('list-style', $this->value('none'));
        self::assertSame('none', $out['list-style-type']->name);
        self::assertArrayNotHasKey('list-style-image', $out);
    }

    public function testBackgroundJustColor(): void
    {
        $out = $this->expander->expand('background', $this->value('#f0f0f0'));
        self::assertInstanceOf(\Phpdftk\Css\Value\Color::class, $out['background-color']);
        self::assertArrayNotHasKey('background-image', $out);
    }

    public function testBackgroundColorAndRepeat(): void
    {
        $out = $this->expander->expand('background', $this->value('red no-repeat'));
        self::assertInstanceOf(\Phpdftk\Css\Value\Color::class, $out['background-color']);
        self::assertSame('no-repeat', $out['background-repeat']->name);
    }

    public function testBackgroundWithUrlAndPosition(): void
    {
        $out = $this->expander->expand('background', $this->value('url(bg.png) top left repeat'));
        self::assertInstanceOf(\Phpdftk\Css\Value\Url::class, $out['background-image']);
        self::assertSame('repeat', $out['background-repeat']->name);
        self::assertInstanceOf(\Phpdftk\Css\Value\ValueList::class, $out['background-position']);
    }

    public function testBackgroundPositionSizeSlash(): void
    {
        $out = $this->expander->expand('background', $this->value('url(bg.jpg) center / cover'));
        self::assertInstanceOf(\Phpdftk\Css\Value\Url::class, $out['background-image']);
        self::assertSame('center', $out['background-position']->name);
        self::assertSame('cover', $out['background-size']->name);
    }

    public function testTextDecorationSingleLine(): void
    {
        $out = $this->expander->expand('text-decoration', $this->value('underline'));
        self::assertInstanceOf(Keyword::class, $out['text-decoration-line']);
        self::assertSame('underline', $out['text-decoration-line']->name);
    }

    public function testTextDecorationWithStyleAndColor(): void
    {
        $out = $this->expander->expand('text-decoration', $this->value('underline wavy red'));
        self::assertSame('underline', $out['text-decoration-line']->name);
        self::assertSame('wavy', $out['text-decoration-style']->name);
        self::assertInstanceOf(\Phpdftk\Css\Value\Color::class, $out['text-decoration-color']);
    }

    public function testTextDecorationMultipleLines(): void
    {
        $out = $this->expander->expand('text-decoration', $this->value('underline line-through'));
        self::assertInstanceOf(\Phpdftk\Css\Value\ValueList::class, $out['text-decoration-line']);
        self::assertCount(2, $out['text-decoration-line']->values);
    }

    public function testTextDecorationFreeOrder(): void
    {
        $out = $this->expander->expand('text-decoration', $this->value('blue solid underline'));
        self::assertSame('underline', $out['text-decoration-line']->name);
        self::assertSame('solid', $out['text-decoration-style']->name);
        self::assertInstanceOf(\Phpdftk\Css\Value\Color::class, $out['text-decoration-color']);
    }

    public function testTextDecorationWithThicknessLength(): void
    {
        $out = $this->expander->expand('text-decoration', $this->value('underline 2px'));
        self::assertArrayHasKey('text-decoration-thickness', $out);
        self::assertInstanceOf(Length::class, $out['text-decoration-thickness']);
        self::assertSame(2.0, $out['text-decoration-thickness']->value);
    }

    public function testTextDecorationWithThicknessKeyword(): void
    {
        $out = $this->expander->expand('text-decoration', $this->value('underline from-font'));
        self::assertSame('from-font', $out['text-decoration-thickness']->name);
    }

    public function testColumnsShorthandSplitsWidthAndCount(): void
    {
        $out = $this->expander->expand('columns', $this->value('200px 3'));
        self::assertInstanceOf(Length::class, $out['column-width']);
        self::assertSame(200.0, $out['column-width']->value);
        self::assertInstanceOf(\Phpdftk\Css\Value\Integer::class, $out['column-count']);
        self::assertSame(3, $out['column-count']->value);
    }

    public function testColumnsShorthandWithOnlyAutoOnlyExpandsWidth(): void
    {
        // `columns: auto` — auto assigns to whichever slot is still free
        // (width first), and column-count stays unset so the registry's
        // initial keeps the cascade defaulting to single-column.
        $out = $this->expander->expand('columns', $this->value('auto'));
        self::assertArrayHasKey('column-width', $out);
        self::assertInstanceOf(Keyword::class, $out['column-width']);
        self::assertSame('auto', $out['column-width']->name);
        self::assertArrayNotHasKey('column-count', $out);
    }

    public function testColumnRuleShorthandExpandsAllThreeLonghands(): void
    {
        $out = $this->expander->expand('column-rule', $this->value('2px dashed red'));
        self::assertInstanceOf(Length::class, $out['column-rule-width']);
        self::assertSame(2.0, $out['column-rule-width']->value);
        self::assertSame('dashed', $out['column-rule-style']->name);
        self::assertInstanceOf(Color::class, $out['column-rule-color']);
    }

    public function testColumnRuleShorthandStyleOnly(): void
    {
        // Only the style is recognisable — width and color stay unset so
        // the registry's `medium` (3px) and `currentcolor` apply.
        $out = $this->expander->expand('column-rule', $this->value('solid'));
        self::assertArrayHasKey('column-rule-style', $out);
        self::assertSame('solid', $out['column-rule-style']->name);
        self::assertArrayNotHasKey('column-rule-width', $out);
        self::assertArrayNotHasKey('column-rule-color', $out);
    }

    public function testColumnRuleShorthandUnknownTokensProduceEmpty(): void
    {
        // None of the classifiers (border-style / border-width / color)
        // accept `foobar`, so nothing lands in the output map.
        $out = $this->expander->expand('column-rule', $this->value('foobar'));
        self::assertSame([], $out);
    }

    public function testOverflowOneValueAppliesToBothAxes(): void
    {
        $out = $this->expander->expand('overflow', $this->value('hidden'));
        self::assertSame('hidden', $out['overflow-x']->name);
        self::assertSame('hidden', $out['overflow-y']->name);
    }

    public function testOverflowTwoValuesXThenY(): void
    {
        $out = $this->expander->expand('overflow', $this->value('hidden auto'));
        self::assertSame('hidden', $out['overflow-x']->name);
        self::assertSame('auto', $out['overflow-y']->name);
    }

    public function testOverflowKeepsLegacyOverflowKey(): void
    {
        // Existing painter code might still read `overflow` directly;
        // keep it set to the X-axis value so behavior doesn't regress.
        $out = $this->expander->expand('overflow', $this->value('hidden auto'));
        self::assertArrayHasKey('overflow', $out);
        self::assertSame('hidden', $out['overflow']->name);
    }

    public function testInsetOneValueExpandsToAllFour(): void
    {
        $out = $this->expander->expand('inset', $this->value('10px'));
        self::assertArrayHasKey('top', $out);
        self::assertArrayHasKey('right', $out);
        self::assertArrayHasKey('bottom', $out);
        self::assertArrayHasKey('left', $out);
        foreach ($out as $v) {
            self::assertSame(10.0, $v->value);
        }
    }

    public function testInsetTwoValuesExpandsTopBottomAndLeftRight(): void
    {
        $out = $this->expander->expand('inset', $this->value('5px 10px'));
        self::assertSame(5.0, $out['top']->value);
        self::assertSame(10.0, $out['right']->value);
        self::assertSame(5.0, $out['bottom']->value);
        self::assertSame(10.0, $out['left']->value);
    }

    public function testInsetFourValuesClockwise(): void
    {
        $out = $this->expander->expand('inset', $this->value('1px 2px 3px 4px'));
        self::assertSame(1.0, $out['top']->value);
        self::assertSame(2.0, $out['right']->value);
        self::assertSame(3.0, $out['bottom']->value);
        self::assertSame(4.0, $out['left']->value);
    }

    public function testInsetAutoKeywordExpands(): void
    {
        // `inset: auto` → all four sides auto.
        $out = $this->expander->expand('inset', $this->value('auto'));
        foreach ($out as $v) {
            self::assertInstanceOf(Keyword::class, $v);
            self::assertSame('auto', strtolower($v->name));
        }
    }

    public function testGapShorthandOneValueAppliesToBoth(): void
    {
        $out = $this->expander->expand('gap', $this->value('10px'));
        self::assertInstanceOf(Length::class, $out['row-gap']);
        self::assertInstanceOf(Length::class, $out['column-gap']);
        self::assertSame(10.0, $out['row-gap']->value);
        self::assertSame(10.0, $out['column-gap']->value);
    }

    public function testGapShorthandTwoValuesRowFirst(): void
    {
        $out = $this->expander->expand('gap', $this->value('10px 20px'));
        self::assertSame(10.0, $out['row-gap']->value);
        self::assertSame(20.0, $out['column-gap']->value);
    }

    public function testGapShorthandAcceptsNormalKeyword(): void
    {
        // `gap: normal` keeps both gaps at the initial value.
        $out = $this->expander->expand('gap', $this->value('normal'));
        self::assertSame('normal', $out['row-gap']->name);
        self::assertSame('normal', $out['column-gap']->name);
    }

    public function testCascadeAppliesShorthand(): void
    {
        // End-to-end: a margin shorthand in a stylesheet should land as
        // separate longhand values in the cascaded bag.
        $sheet = $this->parser->parseStylesheet('p { margin: 10px 20px; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $top = $values->get('margin-top');
        $right = $values->get('margin-right');
        self::assertInstanceOf(Length::class, $top);
        self::assertInstanceOf(Length::class, $right);
        self::assertSame(10.0, $top->value);
        self::assertSame(20.0, $right->value);
    }

    public function testPlaceItemsSingleValueAppliesToBothAxes(): void
    {
        $out = $this->expander->expand('place-items', $this->value('center'));
        self::assertArrayHasKey('align-items', $out);
        self::assertArrayHasKey('justify-items', $out);
        self::assertInstanceOf(Keyword::class, $out['align-items']);
        self::assertSame('center', $out['align-items']->name);
        self::assertSame('center', $out['justify-items']->name);
    }

    public function testPlaceItemsTwoValuesMapsAlignAndJustify(): void
    {
        $out = $this->expander->expand('place-items', $this->value('start end'));
        self::assertSame('start', $out['align-items']->name);
        self::assertSame('end', $out['justify-items']->name);
    }

    public function testPlaceContentTwoValues(): void
    {
        $out = $this->expander->expand('place-content', $this->value('space-between center'));
        self::assertArrayHasKey('align-content', $out);
        self::assertArrayHasKey('justify-content', $out);
        self::assertSame('space-between', $out['align-content']->name);
        self::assertSame('center', $out['justify-content']->name);
    }

    public function testPlaceSelfSingleValueAppliesToBothAxes(): void
    {
        $out = $this->expander->expand('place-self', $this->value('stretch'));
        self::assertSame('stretch', $out['align-self']->name);
        self::assertSame('stretch', $out['justify-self']->name);
    }

    public function testTransitionShorthandExpandsTo4Longhands(): void
    {
        $out = $this->expander->expand('transition', $this->value('opacity 200ms ease-in 50ms'));
        self::assertArrayHasKey('transition-property', $out);
        self::assertArrayHasKey('transition-duration', $out);
        self::assertArrayHasKey('transition-timing-function', $out);
        self::assertArrayHasKey('transition-delay', $out);
        self::assertSame('opacity', $out['transition-property']->name);
        self::assertSame(0.2, $out['transition-duration']->toSeconds());
        self::assertSame('ease-in', $out['transition-timing-function']->name);
        self::assertSame(0.05, $out['transition-delay']->toSeconds());
    }

    public function testTransitionBareProperty(): void
    {
        $out = $this->expander->expand('transition', $this->value('color'));
        self::assertSame('color', $out['transition-property']->name);
        // Defaults — duration 0s, easing ease.
        self::assertSame('0s', $out['transition-duration']->name);
        self::assertSame('ease', $out['transition-timing-function']->name);
    }

    public function testAnimationShorthandFullForm(): void
    {
        $out = $this->expander->expand(
            'animation',
            $this->value('slidein 1s ease-out 0.5s 3 alternate forwards paused'),
        );
        self::assertSame('slidein', $out['animation-name']->name);
        self::assertSame(1.0, $out['animation-duration']->toSeconds());
        self::assertSame('ease-out', $out['animation-timing-function']->name);
        self::assertSame(0.5, $out['animation-delay']->toSeconds());
        self::assertSame(3, $out['animation-iteration-count']->value);
        self::assertSame('alternate', $out['animation-direction']->name);
        self::assertSame('forwards', $out['animation-fill-mode']->name);
        self::assertSame('paused', $out['animation-play-state']->name);
    }

    public function testAnimationInfiniteCount(): void
    {
        $out = $this->expander->expand('animation', $this->value('spin 2s linear infinite'));
        self::assertSame('infinite', $out['animation-iteration-count']->name);
    }

    public function testAnimationCommaSeparatedLayers(): void
    {
        $out = $this->expander->expand('animation', $this->value('fade 1s, slide 2s'));
        $names = $out['animation-name'];
        self::assertInstanceOf(\Phpdftk\Css\Value\ValueList::class, $names);
        self::assertCount(2, $names->values);
        self::assertSame('fade', $names->values[0]->name);
        self::assertSame('slide', $names->values[1]->name);
    }

    public function testPositionTryDefaultsOrderToNormal(): void
    {
        $out = $this->expander->expand('position-try', $this->value('--fallback-a'));
        self::assertSame('normal', $out['position-try-order']->name);
        self::assertSame('--fallback-a', $out['position-try-fallbacks']->name);
    }

    public function testPositionTryWithOrderKeyword(): void
    {
        $out = $this->expander->expand('position-try', $this->value('most-width --fb'));
        self::assertSame('most-width', $out['position-try-order']->name);
        self::assertSame('--fb', $out['position-try-fallbacks']->name);
    }

    public function testTextEmphasisStyleOnly(): void
    {
        $out = $this->expander->expand('text-emphasis', $this->value('filled circle'));
        self::assertArrayHasKey('text-emphasis-style', $out);
        self::assertArrayNotHasKey('text-emphasis-color', $out);
    }

    public function testTextEmphasisStyleAndColor(): void
    {
        $out = $this->expander->expand('text-emphasis', $this->value('filled circle red'));
        self::assertArrayHasKey('text-emphasis-style', $out);
        self::assertArrayHasKey('text-emphasis-color', $out);
        self::assertInstanceOf(\Phpdftk\Css\Value\Color::class, $out['text-emphasis-color']);
    }

    public function testOutlineAcceptsCurrentcolor(): void
    {
        $out = $this->expander->expand('outline', $this->value('1px solid currentcolor'));
        self::assertSame('solid', $out['outline-style']->name);
        self::assertInstanceOf(Keyword::class, $out['outline-color']);
        self::assertSame('currentcolor', $out['outline-color']->name);
    }

    public function testOutlineAcceptsInvertKeyword(): void
    {
        $out = $this->expander->expand('outline', $this->value('1px solid invert'));
        self::assertInstanceOf(Keyword::class, $out['outline-color']);
        self::assertSame('invert', $out['outline-color']->name);
    }

    public function testTextEmphasisCurrentcolorKeyword(): void
    {
        $out = $this->expander->expand('text-emphasis', $this->value('dot currentcolor'));
        self::assertSame('currentcolor', $out['text-emphasis-color']->name);
    }

    public function testMaskSingleUrlExpandsToImage(): void
    {
        $out = $this->expander->expand('mask', $this->value('url(#m)'));
        self::assertArrayHasKey('mask-image', $out);
        self::assertInstanceOf(\Phpdftk\Css\Value\Url::class, $out['mask-image']);
    }

    public function testMaskKeywordsRouteToCorrectLonghands(): void
    {
        $out = $this->expander->expand('mask', $this->value('url(#m) no-repeat luminance add'));
        self::assertSame('no-repeat', $out['mask-repeat']->name);
        self::assertSame('luminance', $out['mask-mode']->name);
        self::assertSame('add', $out['mask-composite']->name);
    }

    public function testMaskGeometryBoxFirstAssignsBothOriginAndClip(): void
    {
        $out = $this->expander->expand('mask', $this->value('url(#m) content-box'));
        self::assertSame('content-box', $out['mask-origin']->name);
        self::assertSame('content-box', $out['mask-clip']->name);
    }

    public function testBorderImageSourceAndRepeat(): void
    {
        $out = $this->expander->expand('border-image', $this->value('url(b.png) round'));
        self::assertInstanceOf(\Phpdftk\Css\Value\Url::class, $out['border-image-source']);
        self::assertSame('round', $out['border-image-repeat']->name);
    }

    public function testBorderImageSliceWidth(): void
    {
        $out = $this->expander->expand('border-image', $this->value('url(b.png) 25 / 1'));
        self::assertArrayHasKey('border-image-slice', $out);
        self::assertArrayHasKey('border-image-width', $out);
    }

    public function testBorderImageTwoRepeatKeywords(): void
    {
        $out = $this->expander->expand('border-image', $this->value('url(b.png) round stretch'));
        $r = $out['border-image-repeat'];
        self::assertInstanceOf(\Phpdftk\Css\Value\ValueList::class, $r);
        self::assertCount(2, $r->values);
    }

    public function testCascadeAppliesTransitionShorthand(): void
    {
        $sheet = $this->parser->parseStylesheet(
            'p { transition: opacity 200ms ease-in 50ms; }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $prop = $values->get('transition-property');
        $dur = $values->get('transition-duration');
        self::assertInstanceOf(Keyword::class, $prop);
        self::assertSame('opacity', $prop->name);
        self::assertInstanceOf(\Phpdftk\Css\Value\Time::class, $dur);
        self::assertSame(0.2, $dur->toSeconds());
    }

    public function testPositionTryMultiFallbacksJoinAsComma(): void
    {
        $out = $this->expander->expand('position-try', $this->value('--a --b --c'));
        self::assertSame('normal', $out['position-try-order']->name);
        $fb = $out['position-try-fallbacks'];
        self::assertInstanceOf(\Phpdftk\Css\Value\ValueList::class, $fb);
        self::assertCount(3, $fb->values);
        self::assertSame('--a', $fb->values[0]->name);
    }
}
