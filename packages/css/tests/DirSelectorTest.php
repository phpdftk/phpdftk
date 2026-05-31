<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Selector\Matcher;
use Phpdftk\Css\Selector\SelectorParser;
use PHPUnit\Framework\TestCase;

// FakeElement lives in MatcherTest.php; pull it in for standalone runs.
require_once __DIR__ . '/MatcherTest.php';

/**
 * CSS Selectors 4 §15.2 — `:dir(ltr)` / `:dir(rtl)` walks
 * ancestor `dir=` attributes to find the closest declared
 * direction; defaults to `ltr` when none is set.
 */
final class DirSelectorTest extends TestCase
{
    private Matcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new Matcher();
    }

    private function match(string $selector, FakeElement $el): bool
    {
        return $this->matcher->listMatches(SelectorParser::parse($selector), $el);
    }

    public function testDirLtrMatchesWhenAncestorDeclaresLtr(): void
    {
        $root = new FakeElement('html', attributes: ['dir' => 'ltr']);
        $p = new FakeElement('p');
        $root->appendFake($p);

        self::assertTrue($this->match(':dir(ltr)', $p));
    }

    public function testDirRtlMatchesWhenAncestorDeclaresRtl(): void
    {
        $root = new FakeElement('html', attributes: ['dir' => 'rtl']);
        $p = new FakeElement('p');
        $root->appendFake($p);

        self::assertTrue($this->match(':dir(rtl)', $p));
        self::assertFalse($this->match(':dir(ltr)', $p));
    }

    public function testClosestAncestorDirWins(): void
    {
        // Outer ltr, inner section rtl, p inside section → rtl.
        $root = new FakeElement('html', attributes: ['dir' => 'ltr']);
        $section = new FakeElement('section', attributes: ['dir' => 'rtl']);
        $p = new FakeElement('p');
        $root->appendFake($section);
        $section->appendFake($p);

        self::assertTrue($this->match(':dir(rtl)', $p));
        self::assertFalse($this->match(':dir(ltr)', $p));
    }

    public function testDirDefaultsToLtrWhenUnset(): void
    {
        // No dir attribute anywhere — defaults to ltr per HTML
        // §3.2.6.4.
        $p = new FakeElement('p');
        self::assertTrue($this->match(':dir(ltr)', $p));
        self::assertFalse($this->match(':dir(rtl)', $p));
    }

    public function testInvalidDirArgumentRejected(): void
    {
        $p = new FakeElement('p', attributes: ['dir' => 'ltr']);
        self::assertFalse($this->match(':dir(fancy)', $p));
    }

    public function testInvalidDirValueFallsThroughToAncestor(): void
    {
        $root = new FakeElement('html', attributes: ['dir' => 'ltr']);
        // Element has a bogus dir; fall through to root's ltr.
        $p = new FakeElement('p', attributes: ['dir' => 'maybe']);
        $root->appendFake($p);

        self::assertTrue($this->match(':dir(ltr)', $p));
    }
}
