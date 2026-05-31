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
}
