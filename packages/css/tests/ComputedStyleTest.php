<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Css\Cascade\ComputedStyle;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\StringValue;
use PHPUnit\Framework\TestCase;

final class ComputedStyleTest extends TestCase
{
    private CascadedValues $values;
    private ComputedStyle $style;

    protected function setUp(): void
    {
        $this->values = new CascadedValues(PropertyRegistry::default());
        $this->style = new ComputedStyle($this->values);
    }

    // ---- Generic access ------------------------------------------------

    public function testGenericGetReturnsUnderlyingValue(): void
    {
        $this->values->set('color', new Color(1.0, 0.0, 0.0, 1.0));
        $v = $this->style->get('color');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(1.0, $v->r);
    }

    public function testGenericGetFallsBackToRegistryInitial(): void
    {
        $v = $this->style->get('display');
        self::assertInstanceOf(Keyword::class, $v);
        self::assertSame('inline', $v->name);
    }

    public function testHasReflectsExplicitlySetProperty(): void
    {
        self::assertFalse($this->style->has('color'));
        $this->values->set('color', new Color(0.0, 0.0, 0.0, 1.0));
        self::assertTrue($this->style->has('color'));
    }

    public function testAllReturnsOnlyExplicitlySetProperties(): void
    {
        $this->values->set('color', new Color(0.5, 0.5, 0.5, 1.0));
        $all = $this->style->all();
        self::assertArrayHasKey('color', $all);
        // 'display' was never set; it shouldn't appear in all() even
        // though the cascade returns its registry default for get().
        self::assertArrayNotHasKey('display', $all);
    }

    public function testCustomPropertyAccessByName(): void
    {
        $this->values->set('--theme', new StringValue('dark'));
        // Both forms (with and without leading `--`) should resolve.
        $v1 = $this->style->getCustomProperty('--theme');
        $v2 = $this->style->getCustomProperty('theme');
        self::assertInstanceOf(StringValue::class, $v1);
        self::assertSame('dark', $v1->value);
        self::assertSame($v1, $v2);
    }

    public function testUnknownPropertyAccess(): void
    {
        // Negative: a property never registered isn't accessible via
        // the typed getters, but the escape hatch `getUnknown` still
        // returns whatever the cascade has (or null).
        self::assertNull($this->style->getUnknown('-webkit-bogus'));
    }

    // ---- Typed accessors: positive ------------------------------------

    public function testGetColorReturnsCascadedColor(): void
    {
        $this->values->set('color', new Color(0.0, 0.5, 1.0, 1.0));
        self::assertSame(0.5, $this->style->getColor()->g);
    }

    public function testGetDisplayReturnsKeyword(): void
    {
        $this->values->set('display', new Keyword('flex'));
        self::assertSame('flex', $this->style->getDisplay()->name);
    }

    public function testGetWidthReturnsLength(): void
    {
        $this->values->set('width', new Length(120.0, LengthUnit::Px));
        $w = $this->style->getWidth();
        self::assertInstanceOf(Length::class, $w);
        self::assertSame(120.0, $w->value);
    }

    public function testGetFlexGrowReturnsNumber(): void
    {
        $this->values->set('flex-grow', new Number(2.5));
        self::assertSame(2.5, $this->style->getFlexGrow()->value);
    }

    public function testGetOrderReturnsInteger(): void
    {
        $this->values->set('order', new Integer(-1));
        self::assertSame(-1, $this->style->getOrder()->value);
    }

    public function testGetMarginPercentageHonoured(): void
    {
        $this->values->set('margin-top', new Percentage(50.0));
        $m = $this->style->getMarginTop();
        self::assertInstanceOf(Percentage::class, $m);
        self::assertSame(50.0, $m->value);
    }

    // ---- Typed accessors: initial-value fallback ----------------------

    public function testGetColorFallsBackToBlackWhenUnset(): void
    {
        // Negative: when `color` was never set, the cascade returns
        // the registry initial — which the typed accessor exposes.
        $c = $this->style->getColor();
        self::assertSame(0.0, $c->r);
        self::assertSame(0.0, $c->g);
        self::assertSame(0.0, $c->b);
    }

    public function testGetDisplayFallsBackToInlineWhenUnset(): void
    {
        self::assertSame('inline', $this->style->getDisplay()->name);
    }

    public function testGetWidthFallsBackToAutoWhenUnset(): void
    {
        $w = $this->style->getWidth();
        self::assertInstanceOf(Keyword::class, $w);
        self::assertSame('auto', $w->name);
    }

    public function testGetFlexGrowFallsBackToZeroWhenUnset(): void
    {
        self::assertSame(0.0, $this->style->getFlexGrow()->value);
    }

    public function testGetFlexShrinkFallsBackToOneWhenUnset(): void
    {
        // Negative: spec initial for flex-shrink is 1, not 0.
        self::assertSame(1.0, $this->style->getFlexShrink()->value);
    }

    public function testGetOrphansFallsBackToTwoWhenUnset(): void
    {
        self::assertSame(2, $this->style->getOrphans()->value);
    }

    public function testGetBoxSizingFallsBackToContentBoxWhenUnset(): void
    {
        self::assertSame('content-box', $this->style->getBoxSizing()->name);
    }

    public function testGetTextAlignLastFallsBackToAutoWhenUnset(): void
    {
        self::assertSame('auto', $this->style->getTextAlignLast()->name);
    }

    public function testGetMaxWidthFallsBackToNoneWhenUnset(): void
    {
        // Negative: max-* properties default to `none`, not `auto`.
        $w = $this->style->getMaxWidth();
        self::assertInstanceOf(Keyword::class, $w);
        self::assertSame('none', $w->name);
    }

    // ---- Type-narrowing fallback (corrupt-value tolerance) -------------

    public function testGetDisplayNarrowsWhenCascadeReturnsWrongType(): void
    {
        // Negative: corrupt cascade returns a Number for `display` →
        // ComputedStyle falls back to the type-narrow initial.
        $this->values->set('display', new Number(42.0));
        $d = $this->style->getDisplay();
        // Phase-1 simplification: invalid type → return the
        // accessor's hardcoded fallback (`inline` for display) rather
        // than propagating the Number.
        self::assertSame('inline', $d->name);
    }

    public function testGetColorNarrowsWhenCascadeReturnsWrongType(): void
    {
        $this->values->set('color', new Keyword('garbage'));
        $c = $this->style->getColor();
        self::assertInstanceOf(Color::class, $c);
        self::assertSame(0.0, $c->r);
    }

    public function testGetFlexGrowNarrowsWhenCascadeReturnsKeyword(): void
    {
        // Negative: keyword in numeric slot → falls back to initial 0.
        $this->values->set('flex-grow', new Keyword('nope'));
        self::assertSame(0.0, $this->style->getFlexGrow()->value);
    }

    public function testGetFontWeightAcceptsKeywordOrInteger(): void
    {
        // Both shapes are valid per spec.
        $this->values->set('font-weight', new Keyword('bold'));
        $w = $this->style->getFontWeight();
        self::assertInstanceOf(Keyword::class, $w);
        self::assertSame('bold', $w->name);

        $this->values->set('font-weight', new Integer(700));
        $w = $this->style->getFontWeight();
        self::assertInstanceOf(Integer::class, $w);
        self::assertSame(700, $w->value);
    }

    public function testGetLineHeightAcceptsAllThreeForms(): void
    {
        $this->values->set('line-height', new Length(20.0, LengthUnit::Px));
        self::assertInstanceOf(Length::class, $this->style->getLineHeight());

        $this->values->set('line-height', new Number(1.5));
        self::assertInstanceOf(Number::class, $this->style->getLineHeight());

        $this->values->set('line-height', new Keyword('normal'));
        self::assertInstanceOf(Keyword::class, $this->style->getLineHeight());
    }

    public function testGetZIndexFallsBackToAutoKeyword(): void
    {
        $z = $this->style->getZIndex();
        self::assertInstanceOf(Keyword::class, $z);
        self::assertSame('auto', $z->name);
    }

    // ---- light-dark() + color-scheme integration ----------------------

    public function testLightDarkResolvesToLightUnderDefaultScheme(): void
    {
        $light = new Color(1.0, 1.0, 1.0, 1.0);
        $dark = new Color(0.0, 0.0, 0.0, 1.0);
        $this->values->set('color', new \Phpdftk\Css\Value\LightDark($light, $dark));
        // No color-scheme set → light branch wins.
        self::assertSame(1.0, $this->style->getColor()->r);
    }

    public function testLightDarkResolvesToDarkUnderDarkOnlyScheme(): void
    {
        $light = new Color(1.0, 1.0, 1.0, 1.0);
        $dark = new Color(0.0, 0.0, 0.0, 1.0);
        $this->values->set('color', new \Phpdftk\Css\Value\LightDark($light, $dark));
        $this->values->set('color-scheme', new Keyword('dark'));
        self::assertSame(0.0, $this->style->getColor()->r);
    }

    public function testBorderWidthKeywordsResolveToPixels(): void
    {
        // CSS Backgrounds 3 §4.4 — thin / medium / thick keywords.
        $this->values->set('border-top-width', new Keyword('thin'));
        self::assertSame(1.0, $this->style->getBorderTopWidth()->value);
        $this->values->set('border-top-width', new Keyword('medium'));
        self::assertSame(3.0, $this->style->getBorderTopWidth()->value);
        $this->values->set('border-top-width', new Keyword('thick'));
        self::assertSame(5.0, $this->style->getBorderTopWidth()->value);
    }

    public function testBorderWidthLengthPassesThrough(): void
    {
        $this->values->set('border-top-width', new Length(7.5, LengthUnit::Px));
        self::assertSame(7.5, $this->style->getBorderTopWidth()->value);
    }

    public function testLightDarkStaysLightWhenBothListed(): void
    {
        $light = new Color(1.0, 1.0, 1.0, 1.0);
        $dark = new Color(0.0, 0.0, 0.0, 1.0);
        $this->values->set('color', new \Phpdftk\Css\Value\LightDark($light, $dark));
        // `color-scheme: light dark` → both supported, prefer light (no UA signal).
        $this->values->set('color-scheme', new \Phpdftk\Css\Value\ValueList(
            [new Keyword('light'), new Keyword('dark')],
            \Phpdftk\Css\Value\ListSeparator::Space,
        ));
        self::assertSame(1.0, $this->style->getColor()->r);
    }
}
