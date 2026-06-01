<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Selector\AnPlusB;
use Phpdftk\Css\Selector\Matcher;
use Phpdftk\Css\Selector\SelectorParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Selectors-4 matcher using an in-process synthetic element
 * tree (no html-package dependency, so the matcher's API surface is
 * exercised in isolation).
 */
final class MatcherTest extends TestCase
{
    private Matcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new Matcher();
    }

    public function testMatchesTypeSelector(): void
    {
        $el = new FakeElement('p');
        $list = SelectorParser::parse('p');
        self::assertTrue($this->matcher->listMatches($list, $el));
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('div'), $el),
        );
    }

    public function testMatchesUniversal(): void
    {
        $el = new FakeElement('anything');
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('*'), $el),
        );
    }

    public function testMatchesId(): void
    {
        $el = new FakeElement('p', id: 'main');
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('#main'), $el),
        );
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('#other'), $el),
        );
    }

    public function testMatchesClass(): void
    {
        $el = new FakeElement('p', classes: ['intro', 'lead']);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('.intro'), $el),
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('.lead.intro'), $el),
        );
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('.missing'), $el),
        );
    }

    public function testAttributeExists(): void
    {
        $el = new FakeElement('input', attributes: ['disabled' => '']);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[disabled]'), $el),
        );
    }

    public function testAttributeEquals(): void
    {
        $el = new FakeElement('input', attributes: ['type' => 'email']);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[type="email"]'), $el),
        );
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('[type="password"]'), $el),
        );
    }

    public function testAttributeAllOperators(): void
    {
        $el = new FakeElement('a', attributes: [
            'class' => 'btn primary large',
            'href' => 'https://example.com/page',
            'lang' => 'en-US',
        ]);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[class~="primary"]'), $el),
            'includes',
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[lang|="en"]'), $el),
            'dash match',
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[href^="https"]'), $el),
            'prefix',
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[href$="/page"]'), $el),
            'suffix',
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[href*="example"]'), $el),
            'substring',
        );
    }

    public function testAttributeCaseInsensitive(): void
    {
        // `data-foo` is NOT on HTML's case-insensitive list, so
        // the default match is case-sensitive — `i` flips it.
        $el = new FakeElement('input', attributes: ['data-foo' => 'BAR']);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[data-foo="bar" i]'), $el),
        );
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('[data-foo="bar"]'), $el),
        );
    }

    public function testHtmlListedAttributesAreCaseInsensitiveByDefault(): void
    {
        // CSS Selectors 4 §6.6 + HTML spec — `type` is on the
        // case-insensitive attribute list, so `[type="email"]` on
        // `type="EMAIL"` matches with no `i` modifier required.
        $el = new FakeElement('input', attributes: ['type' => 'EMAIL']);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('[type="email"]'), $el),
        );
        // `s` modifier forces case-sensitive even on the listed attr.
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('[type="email" s]'), $el),
        );
    }

    public function testCompound(): void
    {
        $el = new FakeElement('p', id: 'foo', classes: ['intro']);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('p#foo.intro'), $el),
        );
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('div#foo'), $el),
        );
    }

    public function testDescendantCombinator(): void
    {
        $root = new FakeElement('article');
        $section = new FakeElement('section');
        $p = new FakeElement('p');
        $root->appendFake($section);
        $section->appendFake($p);

        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('article p'), $p),
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('section p'), $p),
        );
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('div p'), $p),
        );
    }

    public function testChildCombinator(): void
    {
        $root = new FakeElement('div');
        $p = new FakeElement('p');
        $root->appendFake($p);

        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('div > p'), $p),
        );
        $section = new FakeElement('section');
        $root->appendFake($section);
        $deep = new FakeElement('p');
        $section->appendFake($deep);
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('div > p'), $deep),
            'child combinator only matches direct',
        );
    }

    public function testSiblingCombinators(): void
    {
        $parent = new FakeElement('div');
        $h1 = new FakeElement('h1');
        $p = new FakeElement('p');
        $section = new FakeElement('section');
        $parent->appendFake($h1);
        $parent->appendFake($p);
        $parent->appendFake($section);

        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('h1 + p'), $p),
            'next sibling',
        );
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('h1 + section'), $section),
            'not directly after',
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('h1 ~ section'), $section),
            'subsequent sibling',
        );
    }

    public function testFirstChildLastChildOnlyChild(): void
    {
        $parent = new FakeElement('ul');
        $a = new FakeElement('li');
        $b = new FakeElement('li');
        $c = new FakeElement('li');
        $parent->appendFake($a);
        $parent->appendFake($b);
        $parent->appendFake($c);

        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':first-child'), $a));
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':first-child'), $b));
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':last-child'), $c));
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':only-child'), $a));

        $solo = new FakeElement('ul');
        $only = new FakeElement('li');
        $solo->appendFake($only);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':only-child'), $only));
    }

    public function testNthChild(): void
    {
        $parent = new FakeElement('ol');
        $items = [];
        for ($i = 0; $i < 5; $i++) {
            $item = new FakeElement('li');
            $parent->appendFake($item);
            $items[] = $item;
        }
        // 1-based indices: odd → 1, 3, 5
        $odd = SelectorParser::parse(':nth-child(odd)');
        self::assertTrue($this->matcher->listMatches($odd, $items[0]));
        self::assertFalse($this->matcher->listMatches($odd, $items[1]));
        self::assertTrue($this->matcher->listMatches($odd, $items[2]));

        $even = SelectorParser::parse(':nth-child(even)');
        self::assertTrue($this->matcher->listMatches($even, $items[1]));
        self::assertTrue($this->matcher->listMatches($even, $items[3]));

        $third = SelectorParser::parse(':nth-child(3)');
        self::assertTrue($this->matcher->listMatches($third, $items[2]));
        self::assertFalse($this->matcher->listMatches($third, $items[0]));
    }

    public function testNthLastChild(): void
    {
        $parent = new FakeElement('ol');
        $items = [];
        for ($i = 0; $i < 4; $i++) {
            $li = new FakeElement('li');
            $parent->appendFake($li);
            $items[] = $li;
        }
        // last (index 4) is nth-last-child(1)
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse(':nth-last-child(1)'), $items[3]),
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse(':nth-last-child(2)'), $items[2]),
        );
    }

    public function testNthOfType(): void
    {
        $parent = new FakeElement('div');
        $p1 = new FakeElement('p');
        $h2 = new FakeElement('h2');
        $p2 = new FakeElement('p');
        $h2b = new FakeElement('h2');
        $p3 = new FakeElement('p');
        $parent->appendFake($p1);
        $parent->appendFake($h2);
        $parent->appendFake($p2);
        $parent->appendFake($h2b);
        $parent->appendFake($p3);

        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('p:first-of-type'), $p1),
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('p:nth-of-type(2)'), $p2),
        );
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('p:last-of-type'), $p3),
        );
    }

    public function testNotIsWhere(): void
    {
        $el = new FakeElement('p', classes: ['intro']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse('p:not(.bar)'), $el));
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse('p:not(.intro)'), $el));
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':is(p, div)'), $el));
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':where(p, div)'), $el));
    }

    public function testHas(): void
    {
        $parent = new FakeElement('section');
        $img = new FakeElement('img');
        $parent->appendFake($img);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse('section:has(img)'), $parent),
        );
        $empty = new FakeElement('section');
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse('section:has(img)'), $empty),
        );
    }

    public function testEmpty(): void
    {
        $solo = new FakeElement('div');
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse(':empty'), $solo),
        );
        $parent = new FakeElement('div');
        $parent->appendFake(new FakeElement('span'));
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse(':empty'), $parent),
        );
    }

    public function testRoot(): void
    {
        $root = new FakeElement('html');
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':root'), $root));

        $child = new FakeElement('div');
        $root->appendFake($child);
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':root'), $child));
    }

    public function testLang(): void
    {
        $root = new FakeElement('html', attributes: ['lang' => 'en-US']);
        $child = new FakeElement('p');
        $root->appendFake($child);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':lang(en)'), $child));
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':lang(en-US)'), $child));
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':lang(fr)'), $child));
    }

    public function testUiStatePseudosReturnFalse(): void
    {
        $el = new FakeElement('input');
        foreach (['hover', 'focus', 'active', 'checked', 'disabled', 'visited'] as $name) {
            self::assertFalse(
                $this->matcher->listMatches(SelectorParser::parse(":$name"), $el),
                ":$name should be false under print medium",
            );
        }
    }

    public function testLinkMatchesAnchorWithHref(): void
    {
        $a = new FakeElement('a', attributes: ['href' => 'https://example.com']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':link'), $a));
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':any-link'), $a));
    }

    public function testLinkRejectsAnchorWithoutHref(): void
    {
        $a = new FakeElement('a');
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':link'), $a));
    }

    public function testLinkRejectsNonLinkElement(): void
    {
        $div = new FakeElement('div', attributes: ['href' => 'whatever']);
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':link'), $div));
    }

    public function testLinkMatchesAreaAndLink(): void
    {
        $area = new FakeElement('area', attributes: ['href' => '#x']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':link'), $area));
        $link = new FakeElement('link', attributes: ['href' => 'style.css']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':link'), $link));
    }

    public function testNthChildIndexesFilteredSubset(): void
    {
        // CSS Selectors 4 §6.4 — `:nth-child(an+b of S)` indexes
        // only siblings matching S. Build [.x][.y][.x][.y][.x] and
        // verify `:nth-child(2 of .x)` picks the 3rd child (the
        // 2nd .x), NOT the 2nd child (which is .y).
        $parent = new FakeElement('div');
        $x1 = new FakeElement('span', classes: ['x']);
        $y1 = new FakeElement('span', classes: ['y']);
        $x2 = new FakeElement('span', classes: ['x']);
        $y2 = new FakeElement('span', classes: ['y']);
        $x3 = new FakeElement('span', classes: ['x']);
        foreach ([$x1, $y1, $x2, $y2, $x3] as $c) {
            $parent->appendFake($c);
        }
        $selector = SelectorParser::parse(':nth-child(2 of .x)');
        self::assertFalse($this->matcher->listMatches($selector, $x1));
        self::assertFalse($this->matcher->listMatches($selector, $y1));
        self::assertTrue($this->matcher->listMatches($selector, $x2));
        self::assertFalse($this->matcher->listMatches($selector, $y2));
        self::assertFalse($this->matcher->listMatches($selector, $x3));
    }

    public function testDisabledMatchesInputWithDisabledAttribute(): void
    {
        $el = new FakeElement('input', attributes: ['disabled' => '']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':disabled'), $el));
        $el2 = new FakeElement('input');
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':disabled'), $el2));
    }

    public function testCheckedMatchesCheckboxRadioOption(): void
    {
        $cb = new FakeElement('input', attributes: ['type' => 'checkbox', 'checked' => '']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':checked'), $cb));
        $rd = new FakeElement('input', attributes: ['type' => 'radio', 'checked' => '']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':checked'), $rd));
        $opt = new FakeElement('option', attributes: ['selected' => '']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':checked'), $opt));
        $txt = new FakeElement('input', attributes: ['type' => 'text', 'checked' => '']);
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':checked'), $txt));
    }

    public function testRequiredOptionalReflectAttribute(): void
    {
        $req = new FakeElement('input', attributes: ['required' => '']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':required'), $req));
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':optional'), $req));
        $opt = new FakeElement('input');
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':required'), $opt));
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':optional'), $opt));
    }

    public function testPlaceholderShownMatchesInputWithEmptyValue(): void
    {
        $el = new FakeElement('input', attributes: [
            'placeholder' => 'Search…',
        ]);
        self::assertTrue(
            $this->matcher->listMatches(SelectorParser::parse(':placeholder-shown'), $el),
        );
    }

    public function testPlaceholderShownRejectsInputWithValue(): void
    {
        $el = new FakeElement('input', attributes: [
            'placeholder' => 'Search…',
            'value' => 'hello',
        ]);
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse(':placeholder-shown'), $el),
        );
    }

    public function testPlaceholderShownRejectsInputWithoutPlaceholder(): void
    {
        $el = new FakeElement('input');
        self::assertFalse(
            $this->matcher->listMatches(SelectorParser::parse(':placeholder-shown'), $el),
        );
    }

    public function testReadOnlyReflectsReadonlyAndDisabled(): void
    {
        $ro = new FakeElement('input', attributes: ['readonly' => '']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':read-only'), $ro));
        $dis = new FakeElement('input', attributes: ['disabled' => '']);
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':read-only'), $dis));
        $rw = new FakeElement('input');
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':read-write'), $rw));
        // Non-form controls are always :read-only.
        $p = new FakeElement('p');
        self::assertTrue($this->matcher->listMatches(SelectorParser::parse(':read-only'), $p));
        self::assertFalse($this->matcher->listMatches(SelectorParser::parse(':read-write'), $p));
    }

    public function testNthLastChildOfFilteredSubset(): void
    {
        $parent = new FakeElement('div');
        $x1 = new FakeElement('span', classes: ['x']);
        $y1 = new FakeElement('span', classes: ['y']);
        $x2 = new FakeElement('span', classes: ['x']);
        $x3 = new FakeElement('span', classes: ['x']);
        foreach ([$x1, $y1, $x2, $x3] as $c) {
            $parent->appendFake($c);
        }
        $selector = SelectorParser::parse(':nth-last-child(1 of .x)');
        // Last `.x` is $x3.
        self::assertFalse($this->matcher->listMatches($selector, $x1));
        self::assertFalse($this->matcher->listMatches($selector, $x2));
        self::assertTrue($this->matcher->listMatches($selector, $x3));
    }
}
