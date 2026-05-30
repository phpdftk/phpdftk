<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Selector\Matcher;
use Phpdftk\Css\Selector\SelectorParser;
use PHPUnit\Framework\TestCase;

// FakeElement lives at the bottom of MatcherTest.php — pull it in
// so the autoloader sees it even when our test runs in isolation.
require_once __DIR__ . '/MatcherTest.php';

/**
 * CSS Selectors 4 §17 — `:has()` with relative selectors. The
 * leading combinator constrains which neighbour relationship the
 * inner selector matches against:
 *
 *   :has(child)     — any descendant matches (default)
 *   :has(> child)   — only direct children
 *   :has(+ sib)     — the immediately-following sibling
 *   :has(~ sib)     — any subsequent sibling
 *
 * Tests build a tiny tree with `FakeElement` (defined in
 * MatcherTest's fixtures) and verify each combinator.
 */
final class HasCombinatorTest extends TestCase
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

    // -----------------------------------------------------------------------
    // :has(> child)
    // -----------------------------------------------------------------------

    public function testChildCombinatorMatchesDirectChild(): void
    {
        $parent = new FakeElement('section');
        $img = new FakeElement('img');
        $parent->appendFake($img);

        self::assertTrue($this->match('section:has(> img)', $parent));
    }

    public function testChildCombinatorDoesNotMatchDeepDescendant(): void
    {
        // Direct child is a span; img is two levels deep.
        // `:has(> img)` should NOT match because the img isn't a
        // direct child of section.
        $parent = new FakeElement('section');
        $span = new FakeElement('span');
        $img = new FakeElement('img');
        $parent->appendFake($span);
        $span->appendFake($img);

        self::assertFalse($this->match('section:has(> img)', $parent));
        // But the bare descendant form still matches.
        self::assertTrue($this->match('section:has(img)', $parent));
    }

    // -----------------------------------------------------------------------
    // :has(+ sibling)
    // -----------------------------------------------------------------------

    public function testNextSiblingMatchesImmediateNext(): void
    {
        $parent = new FakeElement('div');
        $a = new FakeElement('h1');
        $b = new FakeElement('p');
        $parent->appendFake($a);
        $parent->appendFake($b);

        // h1:has(+ p) — h1 is followed by p → true.
        self::assertTrue($this->match('h1:has(+ p)', $a));
    }

    public function testNextSiblingDoesNotMatchSubsequent(): void
    {
        $parent = new FakeElement('div');
        $a = new FakeElement('h1');
        $b = new FakeElement('section');
        $c = new FakeElement('p');
        $parent->appendFake($a);
        $parent->appendFake($b);
        $parent->appendFake($c);

        // h1's immediate next is section, not p → false.
        self::assertFalse($this->match('h1:has(+ p)', $a));
        // But h1:has(~ p) should match (subsequent sibling).
        self::assertTrue($this->match('h1:has(~ p)', $a));
    }

    public function testNextSiblingNoneAtAll(): void
    {
        $parent = new FakeElement('div');
        $a = new FakeElement('h1');
        $parent->appendFake($a);

        // h1 has no following sibling.
        self::assertFalse($this->match('h1:has(+ p)', $a));
    }

    // -----------------------------------------------------------------------
    // :has(~ subsequent)
    // -----------------------------------------------------------------------

    public function testSubsequentSiblingMatchesAnyAfter(): void
    {
        $parent = new FakeElement('div');
        $a = new FakeElement('h1');
        $b = new FakeElement('section');
        $c = new FakeElement('section');
        $d = new FakeElement('p');
        $parent->appendFake($a);
        $parent->appendFake($b);
        $parent->appendFake($c);
        $parent->appendFake($d);

        self::assertTrue($this->match('h1:has(~ p)', $a));
    }

    public function testSubsequentSiblingDoesNotMatchPredecessors(): void
    {
        // p first, then h1 — h1's subsequent siblings don't include
        // the preceding p.
        $parent = new FakeElement('div');
        $p = new FakeElement('p');
        $h1 = new FakeElement('h1');
        $parent->appendFake($p);
        $parent->appendFake($h1);

        self::assertFalse($this->match('h1:has(~ p)', $h1));
    }

    // -----------------------------------------------------------------------
    // Default (no leading combinator) — descendant
    // -----------------------------------------------------------------------

    public function testNoLeadingCombinatorMatchesDescendant(): void
    {
        // Existing behaviour — preserved.
        $parent = new FakeElement('section');
        $span = new FakeElement('span');
        $img = new FakeElement('img');
        $parent->appendFake($span);
        $span->appendFake($img);

        self::assertTrue($this->match('section:has(img)', $parent));
    }

    // -----------------------------------------------------------------------
    // Multiple branches in :has()
    // -----------------------------------------------------------------------

    public function testHasBranchListAnyMatches(): void
    {
        // `:has(img, > video)` — either an img descendant OR a
        // direct video child. Should match when either branch hits.
        $parent = new FakeElement('section');
        $video = new FakeElement('video');
        $parent->appendFake($video);

        self::assertTrue($this->match('section:has(img, > video)', $parent));
    }
}
