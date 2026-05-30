<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Selector\Matcher;
use Phpdftk\Css\Selector\SelectorParser;
use PHPUnit\Framework\TestCase;

// FakeElement lives in MatcherTest.php; pull it in for standalone runs.
require_once __DIR__ . '/MatcherTest.php';

/**
 * CSS Selectors 4 §13.5 — `:scope` matches the scoping element.
 * Without an explicit @scope root, the document's root element is
 * the scope, so `:scope` behaves like `:root`.
 */
final class ScopeSelectorTest extends TestCase
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

    public function testScopeMatchesRootElement(): void
    {
        $root = new FakeElement('html');
        self::assertTrue($this->match(':scope', $root));
    }

    public function testScopeDoesNotMatchNonRoot(): void
    {
        $root = new FakeElement('html');
        $child = new FakeElement('body');
        $root->appendFake($child);
        self::assertFalse($this->match(':scope', $child));
    }

    public function testScopeAndRootEquivalentForTopLevelMatching(): void
    {
        $root = new FakeElement('html');
        self::assertSame(
            $this->match(':scope', $root),
            $this->match(':root', $root),
        );
    }

    public function testScopeInsideHasMatchesAtRoot(): void
    {
        // `html:has(:scope > body)` — at the html root, :scope
        // refers to it, and the > body matches a direct body
        // child.
        $root = new FakeElement('html');
        $body = new FakeElement('body');
        $root->appendFake($body);

        self::assertTrue($this->match('html:has(:scope > body)', $root));
    }
}
