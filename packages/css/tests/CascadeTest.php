<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the cascade engine using the in-process `FakeElement` fixture
 * (no html-package dependency).
 */
final class CascadeTest extends TestCase
{
    private Parser $parser;
    private Cascade $cascade;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->cascade = new Cascade(PropertyRegistry::default());
    }

    public function testInitialValueWhenNothingMatches(): void
    {
        $el = new FakeElement('div');
        $values = $this->cascade->computeFor([], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        // Default initial is black.
        self::assertSame(0.0, $color->r);
        self::assertSame(0.0, $color->g);
        self::assertSame(0.0, $color->b);
    }

    public function testSingleRuleApplied(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red; }');
        $el = new FakeElement('p');
        $values = $this->cascade->computeFor([$sheet], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSpecificityWins(): void
    {
        // ID (1,0,0) beats class (0,1,0) beats type (0,0,1).
        $sheet = $this->parser->parseStylesheet('
            p { color: red; }
            .lead { color: green; }
            #main { color: blue; }
        ');
        $el = new FakeElement('p', id: 'main', classes: ['lead']);
        $values = $this->cascade->computeFor([$sheet], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        // Blue = #0000FF
        self::assertSame(0.0, $color->r);
        self::assertSame(0.0, $color->g);
        self::assertSame(1.0, $color->b);
    }

    public function testSourceOrderWins(): void
    {
        $sheet = $this->parser->parseStylesheet('
            p { color: red; }
            p { color: blue; }
        ');
        $el = new FakeElement('p');
        $values = $this->cascade->computeFor([$sheet], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
        self::assertSame(1.0, $color->b);
    }

    public function testImportantBeatsHigherSpecificity(): void
    {
        $sheet = $this->parser->parseStylesheet('
            #main { color: blue; }
            p { color: red !important; }
        ');
        $el = new FakeElement('p', id: 'main');
        $values = $this->cascade->computeFor([$sheet], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testAuthorBeatsUaForNormal(): void
    {
        $ua = $this->parser->parseStylesheet('p { color: blue; }', Origin::UserAgent);
        $author = $this->parser->parseStylesheet('p { color: red; }', Origin::Author);
        $el = new FakeElement('p');
        $values = $this->cascade->computeFor([$ua, $author], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r, 'red wins normal Author vs UA');
    }

    public function testUaImportantBeatsAuthorImportant(): void
    {
        $ua = $this->parser->parseStylesheet('p { color: blue !important; }', Origin::UserAgent);
        $author = $this->parser->parseStylesheet('p { color: red !important; }', Origin::Author);
        $el = new FakeElement('p');
        $values = $this->cascade->computeFor([$ua, $author], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->b, 'UA !important beats Author !important');
    }

    public function testInheritanceForColor(): void
    {
        $sheet = $this->parser->parseStylesheet('article { color: green; }');
        $article = new FakeElement('article');
        $p = new FakeElement('p');
        $article->appendFake($p);

        $articleValues = $this->cascade->computeFor([$sheet], $article);
        $pValues = $this->cascade->computeFor([$sheet], $p, $articleValues);

        $articleColor = $articleValues->get('color');
        $pColor = $pValues->get('color');
        self::assertInstanceOf(Color::class, $articleColor);
        self::assertInstanceOf(Color::class, $pColor);
        // Both should be green (0,128,0 ≈ 0.5 g)
        self::assertSame($articleColor->g, $pColor->g, 'color inherits');
    }

    public function testNonInheritedPropertyDoesNotInherit(): void
    {
        // background-color does NOT inherit.
        $sheet = $this->parser->parseStylesheet('article { background-color: red; }');
        $article = new FakeElement('article');
        $p = new FakeElement('p');
        $article->appendFake($p);

        $articleValues = $this->cascade->computeFor([$sheet], $article);
        $pValues = $this->cascade->computeFor([$sheet], $p, $articleValues);

        $bg = $pValues->get('background-color');
        self::assertInstanceOf(Color::class, $bg);
        // Initial background-color is transparent; alpha must be 0.
        self::assertSame(0.0, $bg->a);
    }

    public function testInheritKeyword(): void
    {
        $sheet = $this->parser->parseStylesheet('
            article { background-color: red; }
            p { background-color: inherit; }
        ');
        $article = new FakeElement('article');
        $p = new FakeElement('p');
        $article->appendFake($p);

        $articleValues = $this->cascade->computeFor([$sheet], $article);
        $pValues = $this->cascade->computeFor([$sheet], $p, $articleValues);

        $bg = $pValues->get('background-color');
        self::assertInstanceOf(Color::class, $bg);
        self::assertSame(1.0, $bg->r, '`background-color: inherit` pulls from parent');
    }

    public function testInitialKeyword(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red; color: initial; }');
        $el = new FakeElement('p');
        $values = $this->cascade->computeFor([$sheet], $el);
        $color = $values->get('color');
        // Initial color is black.
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testMultipleProperties(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red; font-size: 20px; margin-left: 10px; }');
        $el = new FakeElement('p');
        $values = $this->cascade->computeFor([$sheet], $el);
        self::assertInstanceOf(Color::class, $values->get('color'));
        $fontSize = $values->get('font-size');
        self::assertInstanceOf(Length::class, $fontSize);
        self::assertSame(20.0, $fontSize->value);
        $margin = $values->get('margin-left');
        self::assertInstanceOf(Length::class, $margin);
        self::assertSame(10.0, $margin->value);
    }

    public function testCascadedValuesFallsBackToInitial(): void
    {
        $values = $this->cascade->computeFor([], new FakeElement('p'));
        self::assertInstanceOf(Keyword::class, $values->get('display'));
        self::assertSame('inline', $values->get('display')->name);
    }

    public function testDescendantSelectorMatches(): void
    {
        $sheet = $this->parser->parseStylesheet('article p { color: red; }');
        $article = new FakeElement('article');
        $p = new FakeElement('p');
        $article->appendFake($p);
        $values = $this->cascade->computeFor([$sheet], $p);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testInlineStyleAttributeBeatsAuthorSelector(): void
    {
        // HTML `style="..."` declarations cascade higher than any normal
        // author selector. The `style="color: red"` should beat the
        // id-based `#main { color: blue }`.
        $sheet = $this->parser->parseStylesheet('#main { color: blue; }');
        $el = new FakeElement('p', id: 'main', attributes: ['style' => 'color: red']);
        $values = $this->cascade->computeFor([$sheet], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
        self::assertSame(0.0, $color->b);
    }

    public function testImportantSelectorStillBeatsInlineStyle(): void
    {
        // !important Author rules outrank style-attribute declarations per
        // CSS Cascade 5 §6.4.4.
        $sheet = $this->parser->parseStylesheet('p { color: blue !important; }');
        $el = new FakeElement('p', attributes: ['style' => 'color: red']);
        $values = $this->cascade->computeFor([$sheet], $el);
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
        self::assertSame(1.0, $color->b);
    }

    public function testVarSubstitutionOnSameElement(): void
    {
        $sheet = $this->parser->parseStylesheet('p { --primary: red; color: var(--primary); }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testVarSubstitutionInheritsFromAncestor(): void
    {
        $sheet = $this->parser->parseStylesheet(':root { --primary: red; } p { color: var(--primary); }');
        $root = new FakeElement('html');
        $p = new FakeElement('p');
        $root->appendFake($p);
        $rootValues = $this->cascade->computeFor([$sheet], $root);
        $pValues = $this->cascade->computeFor([$sheet], $p, $rootValues);
        $color = $pValues->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testVarFallbackUsedWhenUndefined(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: var(--missing, red); }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testVarWithoutFallbackRevertsToInitial(): void
    {
        // Initial color is black; var(--missing) has no fallback, so the
        // property is invalid at computed-value time and falls back to initial.
        $sheet = $this->parser->parseStylesheet('p { color: var(--missing); }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
        self::assertSame(0.0, $color->g);
        self::assertSame(0.0, $color->b);
    }

    public function testCustomPropertiesInheritImplicitly(): void
    {
        // Children that don't declare --primary inherit it.
        $sheet = $this->parser->parseStylesheet(':root { --primary: red; }');
        $root = new FakeElement('html');
        $article = new FakeElement('article');
        $p = new FakeElement('p');
        $root->appendFake($article);
        $article->appendFake($p);

        $rootValues = $this->cascade->computeFor([$sheet], $root);
        $articleValues = $this->cascade->computeFor([$sheet], $article, $rootValues);
        $pValues = $this->cascade->computeFor([$sheet], $p, $articleValues);

        self::assertTrue($pValues->has('--primary'));
        $primary = $pValues->get('--primary');
        self::assertInstanceOf(Color::class, $primary);
        self::assertSame(1.0, $primary->r);
    }

    public function testChildOverridesInheritedCustomProperty(): void
    {
        $sheet = $this->parser->parseStylesheet('
            :root { --primary: red; }
            article { --primary: blue; }
            article p { color: var(--primary); }
        ');
        $root = new FakeElement('html');
        $article = new FakeElement('article');
        $p = new FakeElement('p');
        $root->appendFake($article);
        $article->appendFake($p);

        $rootValues = $this->cascade->computeFor([$sheet], $root);
        $articleValues = $this->cascade->computeFor([$sheet], $article, $rootValues);
        $pValues = $this->cascade->computeFor([$sheet], $p, $articleValues);

        $color = $pValues->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->b, 'p should resolve --primary to blue from article');
    }

    public function testResolveLengthsConvertsPtToPx(): void
    {
        $sheet = $this->parser->parseStylesheet('p { margin-top: 18pt; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $this->cascade->resolveLengths($values, new LengthContext());
        $margin = $values->get('margin-top');
        self::assertInstanceOf(Length::class, $margin);
        self::assertSame(LengthUnit::Px, $margin->unit);
        // 18pt × (96/72) = 24px
        self::assertEqualsWithDelta(24.0, $margin->value, 0.001);
    }

    public function testEmResolvesAgainstParentFontSizeForFontSize(): void
    {
        $sheet = $this->parser->parseStylesheet('p { font-size: 2em; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $this->cascade->resolveLengths($values, new LengthContext(parentFontSize: 16.0));
        $fontSize = $values->get('font-size');
        self::assertInstanceOf(Length::class, $fontSize);
        self::assertSame(32.0, $fontSize->value);
        self::assertSame(LengthUnit::Px, $fontSize->unit);
    }

    public function testEmResolvesAgainstOwnFontSizeForOtherProperties(): void
    {
        // font-size: 20px → margin-top: 1em should be 20px (current's font-size).
        $sheet = $this->parser->parseStylesheet('p { font-size: 20px; margin-top: 1em; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $this->cascade->resolveLengths($values, new LengthContext(parentFontSize: 16.0));
        $margin = $values->get('margin-top');
        self::assertInstanceOf(Length::class, $margin);
        self::assertSame(20.0, $margin->value);
    }

    public function testRemResolvesAgainstRootFontSize(): void
    {
        $sheet = $this->parser->parseStylesheet('p { margin-top: 1.5rem; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $this->cascade->resolveLengths($values, new LengthContext(rootFontSize: 16.0));
        $margin = $values->get('margin-top');
        self::assertInstanceOf(Length::class, $margin);
        self::assertSame(24.0, $margin->value);
    }

    public function testChainedVarSubstitution(): void
    {
        $sheet = $this->parser->parseStylesheet('
            p {
                --primary: red;
                --accent: var(--primary);
                color: var(--accent);
            }
        ');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaPrintRulesApply(): void
    {
        // `@media print { p { color: red } }` — phpdftk renders in print
        // context, so its rules must cascade.
        $sheet = $this->parser->parseStylesheet(
            '@media print { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r, 'red color cascaded from inside @media print');
    }

    public function testMediaAllRulesApply(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@media all { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaScreenRulesDropped(): void
    {
        // `@media screen { ... }` rules must NOT cascade in print context.
        $sheet = $this->parser->parseStylesheet(
            '@media screen { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        // Initial black (0.0) — screen-only rule didn't apply.
        self::assertSame(0.0, $color->r, 'screen-only @media block dropped in print context');
    }

    public function testMediaCommaListMatchesIfPrintMentioned(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@media screen, print { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsRulesAlwaysApply(): void
    {
        // `@supports` always enters at Phase 1 — full evaluation comes later.
        $sheet = $this->parser->parseStylesheet(
            '@supports (display: flex) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testVarLoopFallsBackToInitial(): void
    {
        // Mutually recursive var() references blow the 100-deep cap; cascade
        // marks the value invalid and the property reverts to its initial.
        $sheet = $this->parser->parseStylesheet('
            p {
                --a: var(--b);
                --b: var(--a);
                color: var(--a);
            }
        ');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        // Initial color is black.
        self::assertSame(0.0, $color->r);
    }

    public function testCaretColorRegisteredAndAcceptsAuthorValue(): void
    {
        // `caret-color` is print-irrelevant but registered so author
        // CSS isn't silently dropped at cascade time.
        $sheet = $this->parser->parseStylesheet('p { caret-color: blue; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $caret = $values->get('caret-color');
        self::assertInstanceOf(Color::class, $caret);
        self::assertSame(0.0, $caret->r);
        self::assertSame(0.0, $caret->g);
        self::assertSame(1.0, $caret->b);
    }

    public function testCaretColorDefaultsToAuto(): void
    {
        // Initial value is `auto` per CSS UI 4.
        $values = $this->cascade->computeFor([], new FakeElement('p'));
        $caret = $values->get('caret-color');
        self::assertInstanceOf(Keyword::class, $caret);
        self::assertSame('auto', strtolower($caret->name));
    }

    public function testAccentColorRegisteredAndDoesNotInherit(): void
    {
        // accent-color does NOT inherit per CSS UI 4 — child gets
        // initial `auto` rather than parent's red.
        $sheet = $this->parser->parseStylesheet('p { accent-color: red; }');
        $parentValues = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $childValues = $this->cascade->computeFor(
            [$sheet],
            new FakeElement('span'),
            $parentValues,
        );
        $childAccent = $childValues->get('accent-color');
        self::assertInstanceOf(Keyword::class, $childAccent);
        self::assertSame('auto', strtolower($childAccent->name));
    }

    public function testCaretColorInheritsToDescendants(): void
    {
        // Per CSS UI 4, `caret-color` IS an inheriting property.
        $sheet = $this->parser->parseStylesheet('p { caret-color: green; }');
        $parentValues = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $childValues = $this->cascade->computeFor(
            [$sheet],
            new FakeElement('span'),
            $parentValues,
        );
        $childCaret = $childValues->get('caret-color');
        self::assertInstanceOf(Color::class, $childCaret);
        self::assertSame(0.0, $childCaret->r);
    }

    public function testImageRenderingRegisteredAndAcceptsAuthorValue(): void
    {
        $sheet = $this->parser->parseStylesheet('img { image-rendering: pixelated; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('img'));
        $r = $values->get('image-rendering');
        self::assertInstanceOf(Keyword::class, $r);
        self::assertSame('pixelated', strtolower($r->name));
    }

    public function testFontKerningInheritsToDescendants(): void
    {
        // CSS Fonts 4 §6.5: font-kerning inherits.
        $sheet = $this->parser->parseStylesheet('p { font-kerning: none; }');
        $parentValues = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $childValues = $this->cascade->computeFor(
            [$sheet],
            new FakeElement('span'),
            $parentValues,
        );
        $kerning = $childValues->get('font-kerning');
        self::assertInstanceOf(Keyword::class, $kerning);
        self::assertSame('none', strtolower($kerning->name));
    }

    public function testIsolationDoesNotInherit(): void
    {
        // CSS Compositing 1: isolation does NOT inherit.
        $sheet = $this->parser->parseStylesheet('div { isolation: isolate; }');
        $parentValues = $this->cascade->computeFor([$sheet], new FakeElement('div'));
        $childValues = $this->cascade->computeFor(
            [$sheet],
            new FakeElement('span'),
            $parentValues,
        );
        $isolation = $childValues->get('isolation');
        self::assertInstanceOf(Keyword::class, $isolation);
        self::assertSame('auto', strtolower($isolation->name));
    }

    public function testFontFeatureSettingsRegisteredAndInherits(): void
    {
        // font-feature-settings inherits per CSS Fonts 4.
        $sheet = $this->parser->parseStylesheet('p { font-feature-settings: "smcp"; }');
        $parentValues = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $childValues = $this->cascade->computeFor(
            [$sheet],
            new FakeElement('span'),
            $parentValues,
        );
        $value = $childValues->get('font-feature-settings');
        self::assertNotNull($value);
    }

    public function testMixBlendModeDefaultsToNormal(): void
    {
        // Default value.
        $values = $this->cascade->computeFor([], new FakeElement('div'));
        $blend = $values->get('mix-blend-mode');
        self::assertInstanceOf(Keyword::class, $blend);
        self::assertSame('normal', strtolower($blend->name));
    }
}
