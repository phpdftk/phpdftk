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
}
