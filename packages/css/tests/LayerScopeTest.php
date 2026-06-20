<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Cascade 5 §3 `@layer` + CSS Cascade 6 §3 `@scope` — both
 * land as pass-through at-rules so author CSS inside their blocks
 * applies instead of being silently dropped.
 *
 * Layer priority ordering (§3.4 — earlier-declared layer loses to
 * later-declared, unlayered wins over layered) lands in a
 * follow-up. For now layers behave like @media-always-true.
 */
final class LayerScopeTest extends TestCase
{
    private Parser $parser;
    private Cascade $cascade;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->cascade = new Cascade(PropertyRegistry::default());
    }

    public function testLayerBlockAppliesItsRules(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@layer reset { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testAnonymousLayerBlockApplies(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@layer { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testNestedLayerNameApplies(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@layer base.reset { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testLayerDeclarationOnlyDoesNotApplyRules(): void
    {
        // `@layer reset, defaults;` is a declaration that orders
        // future layers; it carries no rules itself.
        $sheet = $this->parser->parseStylesheet(
            '@layer reset, defaults; p { color: red; }',
        );
        // The standalone `p { color: red; }` still applies.
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testLayerInsideMediaQuery(): void
    {
        // `@media print { @layer ... { ... } }` — both at-rules
        // pass through.
        $sheet = $this->parser->parseStylesheet(
            '@media print { @layer reset { p { color: red; } } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testMediaQueryNonMatchingDropsNestedLayer(): void
    {
        // @media screen doesn't match; the nested @layer's rules
        // should drop.
        $sheet = $this->parser->parseStylesheet(
            '@media screen { @layer reset { p { color: red; } } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        // Initial color (black).
        self::assertSame(0.0, $values->get('color')->r);
    }

    public function testScopeBlockAppliesItsRules(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@scope (.card) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        // @scope is pass-through for now — rule applies regardless
        // of the scope selector.
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testStartingStyleBlockApplies(): void
    {
        // CSS Transitions 2 §3 — @starting-style declares the
        // entry (from-) state for transitioning properties. For
        // a static print render the starting state IS the
        // rendered state.
        $sheet = $this->parser->parseStylesheet(
            '@starting-style { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testContainerBlockEvaluatesAgainstViewportProxy(): void
    {
        // CSS Containment 3 §4.4 — `@container (min-width: 400px)`
        // evaluates against the viewport as a Phase-1 proxy. Default
        // viewport is null → permissive (applies the rule).
        $sheet = $this->parser->parseStylesheet(
            '@container (min-width: 400px) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testContainerBlockDropsUnsatisfiableQueryAgainstViewport(): void
    {
        // With a known viewport, `(min-width: 9999999px)` evaluates
        // to false → rule drops, color stays at the default.
        $cascade = $this->cascade->withViewport(816.0, 1056.0);
        $sheet = $this->parser->parseStylesheet(
            '@container (min-width: 9999999px) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        // Default `color` initial is `Color::canvasText` (black /
        // r=0). Red would have r=1 if the rule applied.
        self::assertSame(0.0, $values->get('color')->r);
    }

    public function testContainerNamedAppliesPassThroughForUnsupportedFeature(): void
    {
        // `inline-size > 30em` uses the inline-size container
        // feature, which the Phase-1 evaluator doesn't know about —
        // fall through permissively.
        $sheet = $this->parser->parseStylesheet(
            '@container card (inline-size > 30em) { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }

    public function testContainerRangeSyntaxWidthGreaterDropsWhenViewportSmaller(): void
    {
        // MQ5 range syntax — `(width > 400px)` rewritten to
        // `min-width: 400px`. Viewport 300 < 400 → rule drops.
        $cascade = $this->cascade->withViewport(300.0, 600.0);
        $sheet = $this->parser->parseStylesheet(
            '@container (width > 400px) { p { color: red; } }',
        );
        $values = $cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(0.0, $values->get('color')->r);
    }

    public function testContainerRangeSyntaxChainedRespectsBothBounds(): void
    {
        // `(100px < width <= 500px)` → `min-width: 100px AND
        // max-width: 500px`. Viewport 300 satisfies both → rule
        // applies; viewport 600 fails the upper bound → drops.
        $sheet = $this->parser->parseStylesheet(
            '@container (100px < width <= 500px) { p { color: red; } }',
        );
        $matchVp = $this->cascade->withViewport(300.0, 600.0)
            ->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $matchVp->get('color')->r);
        $dropVp = $this->cascade->withViewport(600.0, 600.0)
            ->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(0.0, $dropVp->get('color')->r);
    }

    public function testPositionTryBlockAppliesPassThrough(): void
    {
        $sheet = $this->parser->parseStylesheet(
            '@position-try --fallback { p { color: red; } }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        self::assertSame(1.0, $values->get('color')->r);
    }
}
