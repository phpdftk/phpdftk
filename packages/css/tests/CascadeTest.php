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

    public function testPercentageFontSizeResolvesAgainstParent(): void
    {
        // CSS Fonts 3 §3.5 — `font-size: <percentage>` resolves
        // against the inherited (parent) font-size. Without this
        // resolution the cascade leaves the Percentage in place and
        // downstream layout falls back to the parent size verbatim,
        // making `font-size: 50%` render the same as the parent's
        // 1in instead of half that.
        $sheet = $this->parser->parseStylesheet('p { font-size: 50%; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $this->cascade->resolveLengths($values, new LengthContext(parentFontSize: 32.0));
        $fontSize = $values->get('font-size');
        self::assertInstanceOf(Length::class, $fontSize);
        self::assertSame(16.0, $fontSize->value);
    }

    public function testPercentageFontSizeZeroResolvesToZero(): void
    {
        // Negative test — `font-size: 0%` (and the equivalent `-0%`)
        // must resolve to a `Length(0px)`, NOT fall through to the
        // parent's size. WPT `font-size-092` pins this.
        $sheet = $this->parser->parseStylesheet('p { font-size: 0%; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $this->cascade->resolveLengths($values, new LengthContext(parentFontSize: 96.0));
        $fontSize = $values->get('font-size');
        self::assertInstanceOf(Length::class, $fontSize);
        self::assertSame(0.0, $fontSize->value);
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

    public function testMediaMinWidthQueryMatchesWhenViewportLarger(): void
    {
        // CSS Media Queries 4 §4.5 — `(min-width: 600px)` matches
        // when the viewport is at least 600px wide.
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: 600px) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaMinWidthQueryDropsWhenViewportTooSmall(): void
    {
        // Negative: viewport (300px) < min-width threshold (600px) → rule drops.
        $cascade = $this->cascade->withViewport(300.0, 400.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: 600px) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r, 'min-width drops when viewport too small');
    }

    public function testMediaMaxWidthQueryMatchesWhenViewportSmaller(): void
    {
        $cascade = $this->cascade->withViewport(400.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (max-width: 600px) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaCalcExpressionEvaluates(): void
    {
        // CSS Values 4 §10 — `calc()` is valid inside `@media`
        // feature query values. `(min-width: calc(400px + 200px))`
        // should evaluate to 600px and match the 800px viewport.
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: calc(400px + 200px)) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaCalcNegativeWidthClampsToZero(): void
    {
        // CSS Values 4 §10 — negative `<length>` in `@media` feature
        // value clamps to zero for width / height. `(min-width:
        // calc(-100px))` clamps to `0px`, so it matches any non-
        // negative viewport. (calc-in-media-queries-002 fixture.)
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: calc(-100px)) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaCalcMixedUnitsResolveCorrectly(): void
    {
        // Mixed-unit sum: `1in - 24px` = 96 - 24 = 72 (CSS px).
        // Viewport at 72px matches `(min-width: calc(1in - 24px))`.
        $cascade = $this->cascade->withViewport(72.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: calc(1in - 24px)) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaMalformedCalcDropsRule(): void
    {
        // Negative-first: a malformed `calc(...)` (e.g. operator-only
        // body, no whitespace around `+`) fails the query — the rule
        // doesn't apply. Without this guard a half-evaluated value
        // would silently shift the layout decision against author
        // intent.
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: calc(+)) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testMediaCalcBareUnitLengthsStillWork(): void
    {
        // Regression guard: the original `(min-width: 600px)` pattern
        // (no calc()) must keep working alongside the new calc()
        // support — the resolveMediaDimensionValue helper handles
        // both shapes.
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: 600px) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testMediaPrintAndFeatureQueryBothMatch(): void
    {
        // Positive: media type + feature combine via `and`.
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media print and (min-width: 600px) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertSame(1.0, $color->r);
    }

    public function testMediaNotPrintInvertsToScreenAndDoesNotMatch(): void
    {
        // Negative: `not print` in a print rendering context must NOT match.
        $sheet = $this->parser->parseStylesheet(
            '@media not print { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertSame(0.0, $color->r);
    }

    public function testMediaOnlyPrintKeywordStillMatches(): void
    {
        // `only print` — `only` is a legacy hide-from-old-browsers
        // keyword, treated as a no-op gate. The query still matches.
        $sheet = $this->parser->parseStylesheet(
            '@media only print { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertSame(1.0, $color->r);
    }

    public function testMediaReservedWordAtTypePositionIsInvalid(): void
    {
        // CSS Media Queries 4 §2.1: `not`, `and`, `only`, `or`, and
        // `layer` are reserved; using one as a media type is invalid
        // syntax. Per §3.1, the whole query becomes `not all` → false.
        // Critically, the leading `not` does NOT flip it: invalid stays
        // invalid (see WPT mq-invalid-media-type-002..004,
        // mq-invalid-media-type-layer-001).
        foreach (['or', 'not', 'only', 'and', 'layer'] as $reserved) {
            foreach (["@media not {$reserved}", "@media {$reserved}"] as $prelude) {
                $sheet = $this->parser->parseStylesheet(
                    "{$prelude} { p { color: red; } }",
                );
                $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
                self::assertSame(
                    0.0,
                    $values->get('color')->r,
                    "{$prelude} must NOT match: reserved word at media-type position is invalid",
                );
            }
        }
    }

    public function testMediaUnknownFeatureEvaluatesFalse(): void
    {
        // Negative: an unrecognised feature (`color-index`) makes
        // the query fail per CSS Media Queries 4 §3.1.
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (color-index: 1024) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertSame(0.0, $color->r);
    }

    public function testMediaOrientationLandscape(): void
    {
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (orientation: landscape) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testMediaOrientationPortrait(): void
    {
        // Negative orientation: landscape viewport, portrait query → drop.
        $cascade = $this->cascade->withViewport(800.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@media (orientation: portrait) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(0.0, $values->get('color')->r);
    }

    public function testMediaFeatureQueryUnknownViewportMatchesPermissively(): void
    {
        // Default Cascade has no viewport configured → feature
        // queries match permissively so print stylesheets that gate
        // on width never silently drop.
        $sheet = $this->parser->parseStylesheet(
            '@media (min-width: 9999px) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        // Without viewport, the feature is treated as matching.
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testSupportsRulesAlwaysApply(): void
    {
        // `@supports (display: flex)` — `display` is a registered
        // property in our cascade, so the condition holds.
        $sheet = $this->parser->parseStylesheet(
            '@supports (display: flex) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsUnknownPropertyDrops(): void
    {
        // Negative: `@supports (mystery-prop: 1)` — the property isn't
        // registered, so the cascade reports "unsupported" and the
        // gated rule drops.
        $sheet = $this->parser->parseStylesheet(
            '@supports (mystery-prop: 1) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testSupportsNotInverts(): void
    {
        // `@supports not (mystery-prop: 1)` — the prop is unsupported,
        // so `not` makes the condition true.
        $sheet = $this->parser->parseStylesheet(
            '@supports not (mystery-prop: 1) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsAndConditionRequiresBothMatching(): void
    {
        // Positive: both display and color are registered, so the
        // condition holds.
        $sheet = $this->parser->parseStylesheet(
            '@supports (display: flex) and (color: red) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsAndConditionDropsWhenEitherFails(): void
    {
        // Negative: one side fails → the whole condition fails.
        $sheet = $this->parser->parseStylesheet(
            '@supports (display: flex) and (mystery-prop: 1) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testSupportsOrCondition(): void
    {
        // `or` — either side passing makes the whole condition hold.
        $sheet = $this->parser->parseStylesheet(
            '@supports (mystery-prop: 1) or (color: red) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsSelectorFunctionEvaluatesParseable(): void
    {
        // CSS Conditional Rules 4 §3 — `selector(<sel>)` returns
        // true when the inner selector parses cleanly. We don't
        // model "supported pseudo-class" granularity beyond
        // parseability — any well-formed selector returns true.
        $sheet = $this->parser->parseStylesheet(
            '@supports selector(:has(p)) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsSelectorFunctionWithMalformedSelectorDrops(): void
    {
        // A genuinely malformed inner selector (`!!!`) fails to
        // parse and the supports condition evaluates false.
        $sheet = $this->parser->parseStylesheet(
            '@supports selector(!!!) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        // Initial color (black) — rule dropped.
        self::assertSame(0.0, $values->get('color')->r);
    }

    public function testSupportsFontFormatWoff2Matches(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@supports font-format(woff2) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsFontFormatUnknownDrops(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@supports font-format(quux) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testSupportsExtraParensAroundSingleSubcondition(): void
    {
        // CSS Conditional Rules 3 §3 — `((color: green))` strips a
        // legal extra-parens wrapper. The body re-enters
        // parseSupportsPrimary as a fresh `(...)` rather than being
        // misread as a malformed `(property: value)` declaration.
        $sheet = $this->parser->parseStylesheet(
            '@supports ((color: green)) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsRejectsInvalidColorValue(): void
    {
        // `color: rainbow` — `rainbow` is not a CSS named colour, so
        // the condition fails and the inner rule must not apply. The
        // previous behaviour accepted on property-name match alone,
        // turning every feature-detection stylesheet into a noop on
        // properties that DO exist but with values that don't.
        $sheet = $this->parser->parseStylesheet(
            '@supports (color: rainbow) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        // Initial color (black). Without the validation, this would
        // be 1.0 (red).
        self::assertSame(0.0, $color->r);
    }

    public function testSupportsAcceptsNamedColorValue(): void
    {
        // Positive: a recognised CSS named colour passes the value
        // check. Mirror of the invalid-value test above.
        $sheet = $this->parser->parseStylesheet(
            '@supports (color: cornflowerblue) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsAcceptsHexColorValue(): void
    {
        // Hex notation is accepted on shape — 3/4/6/8-digit forms.
        $sheet = $this->parser->parseStylesheet(
            '@supports (color: #f8a) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsAcceptsFunctionalColorValue(): void
    {
        // Functional colour notation accepts on shape; the cascade's
        // ValueParser handles argument validation when the rule applies.
        $sheet = $this->parser->parseStylesheet(
            '@supports (color: rgb(255, 0, 0)) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsRejectsInvalidColorValueInConjunction(): void
    {
        // `(color: blue) and (color: rainbow)` — second sub-condition
        // fails, so the whole conjunction fails. Without value
        // validation, both would short-circuit to true.
        $sheet = $this->parser->parseStylesheet(
            '@supports (color: blue) and (color: rainbow) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testSupportsRejectsEmptyValue(): void
    {
        // Empty value (`(color: )`) is not a valid declaration body —
        // CSS requires at least one value token after the colon.
        $sheet = $this->parser->parseStylesheet(
            '@supports (color: ) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testSupportsAcceptsCurrentColorAndTransparent(): void
    {
        // `currentcolor` and `transparent` are CSS-level keywords
        // applicable to every colour-typed property; both must pass.
        $sheet1 = $this->parser->parseStylesheet(
            '@supports (color: currentcolor) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet1], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);

        $sheet2 = $this->parser->parseStylesheet(
            '@supports (background-color: transparent) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet2], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testSupportsNonColorPropertyKeepsCurrentBehaviour(): void
    {
        // Regression guard: value validation is scoped to colour-typed
        // properties for now. A `(display: flex)` check still passes
        // on property-name match — full per-type validation is a
        // larger lift and tightening it shouldn't silently drop
        // valid feature-detection.
        $sheet = $this->parser->parseStylesheet(
            '@supports (display: flex) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsFontTechVariationsMatches(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@supports font-tech(variations) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsFontTechUnknownDrops(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@supports font-tech(color-cbdt) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(0.0, $color->r);
    }

    public function testSupportsCombinedSelectorAndPropertyQueries(): void
    {
        // `selector(...)` composes with `(property: value)` via
        // and / or. Both must evaluate.
        $sheet = $this->parser->parseStylesheet(
            '@supports selector(:has(p)) and (display: grid) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsNotSelectorInverts(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@supports not selector(!!!) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $color = $values->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
    }

    public function testSupportsBooleanFormChecksPropertyExists(): void
    {
        // `(display)` — boolean form checks the property exists.
        $sheet = $this->parser->parseStylesheet(
            '@supports (display) { p { color: red; } }',
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

    public function testPrintColorAdjustInheritsToDescendants(): void
    {
        // CSS Color Adjustment 1: print-color-adjust inherits.
        $sheet = $this->parser->parseStylesheet('p { print-color-adjust: exact; }');
        $parentValues = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $childValues = $this->cascade->computeFor(
            [$sheet],
            new FakeElement('span'),
            $parentValues,
        );
        $value = $childValues->get('print-color-adjust');
        self::assertInstanceOf(Keyword::class, $value);
        self::assertSame('exact', strtolower($value->name));
    }

    public function testColorSchemeRegistered(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color-scheme: light dark; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $value = $values->get('color-scheme');
        self::assertNotNull($value);
    }

    public function testForcedColorAdjustDefaultsToAuto(): void
    {
        $values = $this->cascade->computeFor([], new FakeElement('div'));
        $value = $values->get('forced-color-adjust');
        self::assertInstanceOf(Keyword::class, $value);
        self::assertSame('auto', strtolower($value->name));
    }

    public function testPrintColorAdjustDefaultEconomy(): void
    {
        $values = $this->cascade->computeFor([], new FakeElement('div'));
        $value = $values->get('print-color-adjust');
        self::assertInstanceOf(Keyword::class, $value);
        self::assertSame('economy', strtolower($value->name));
    }
}
