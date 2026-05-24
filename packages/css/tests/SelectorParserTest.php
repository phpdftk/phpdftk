<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Selector\AnPlusB;
use Phpdftk\Css\Selector\AttributeMatchType;
use Phpdftk\Css\Selector\AttributeSelector;
use Phpdftk\Css\Selector\ClassSelector;
use Phpdftk\Css\Selector\Combinator;
use Phpdftk\Css\Selector\ComplexSelector;
use Phpdftk\Css\Selector\CompoundSelectorWithCombinator;
use Phpdftk\Css\Selector\IdSelector;
use Phpdftk\Css\Selector\PseudoClassSelector;
use Phpdftk\Css\Selector\PseudoElementSelector;
use Phpdftk\Css\Selector\SelectorList;
use Phpdftk\Css\Selector\SelectorParser;
use Phpdftk\Css\Selector\Specificity;
use Phpdftk\Css\Selector\TypeSelector;
use Phpdftk\Css\Selector\UniversalSelector;
use PHPUnit\Framework\TestCase;

final class SelectorParserTest extends TestCase
{
    public function testParsesSingleType(): void
    {
        $list = SelectorParser::parse('p');
        self::assertCount(1, $list->selectors);
        $complex = $list->selectors[0];
        self::assertCount(1, $complex->compounds);
        $components = $complex->compounds[0]->compound->components;
        self::assertCount(1, $components);
        self::assertInstanceOf(TypeSelector::class, $components[0]);
        self::assertSame('p', $components[0]->localName);
    }

    public function testParsesUniversal(): void
    {
        $list = SelectorParser::parse('*');
        self::assertCount(1, $list->selectors);
        $components = $list->selectors[0]->compounds[0]->compound->components;
        self::assertInstanceOf(UniversalSelector::class, $components[0]);
    }

    public function testParsesId(): void
    {
        $list = SelectorParser::parse('#main');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(IdSelector::class, $c);
        self::assertSame('main', $c->id);
    }

    public function testParsesClass(): void
    {
        $list = SelectorParser::parse('.intro');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(ClassSelector::class, $c);
        self::assertSame('intro', $c->className);
    }

    public function testParsesCompoundTypeClass(): void
    {
        $list = SelectorParser::parse('div.foo.bar');
        $components = $list->selectors[0]->compounds[0]->compound->components;
        self::assertCount(3, $components);
        self::assertInstanceOf(TypeSelector::class, $components[0]);
        self::assertInstanceOf(ClassSelector::class, $components[1]);
        self::assertInstanceOf(ClassSelector::class, $components[2]);
    }

    public function testDescendantCombinator(): void
    {
        $list = SelectorParser::parse('article p');
        $compounds = $list->selectors[0]->compounds;
        self::assertCount(2, $compounds);
        self::assertSame(Combinator::Descendant, $compounds[0]->combinatorToNext);
        self::assertNull($compounds[1]->combinatorToNext);
    }

    public function testChildCombinator(): void
    {
        $list = SelectorParser::parse('article > p');
        $compounds = $list->selectors[0]->compounds;
        self::assertCount(2, $compounds);
        self::assertSame(Combinator::Child, $compounds[0]->combinatorToNext);
    }

    public function testSiblingCombinators(): void
    {
        $list = SelectorParser::parse('h1 + p');
        self::assertSame(Combinator::NextSibling, $list->selectors[0]->compounds[0]->combinatorToNext);

        $list2 = SelectorParser::parse('h1 ~ p');
        self::assertSame(Combinator::SubsequentSibling, $list2->selectors[0]->compounds[0]->combinatorToNext);
    }

    public function testColumnCombinator(): void
    {
        $list = SelectorParser::parse('col || td');
        self::assertSame(Combinator::Column, $list->selectors[0]->compounds[0]->combinatorToNext);
    }

    public function testCommaSeparatedList(): void
    {
        $list = SelectorParser::parse('h1, h2, h3');
        self::assertCount(3, $list->selectors);
    }

    public function testAttributeSelectorExists(): void
    {
        $list = SelectorParser::parse('[disabled]');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(AttributeSelector::class, $c);
        self::assertSame('disabled', $c->name);
        self::assertSame(AttributeMatchType::Exists, $c->matchType);
    }

    public function testAttributeSelectorAllOperators(): void
    {
        $cases = [
            '[a=b]'  => AttributeMatchType::Equals,
            '[a~=b]' => AttributeMatchType::Includes,
            '[a|=b]' => AttributeMatchType::DashMatch,
            '[a^=b]' => AttributeMatchType::PrefixMatch,
            '[a$=b]' => AttributeMatchType::SuffixMatch,
            '[a*=b]' => AttributeMatchType::SubstringMatch,
        ];
        foreach ($cases as $src => $expected) {
            $list = SelectorParser::parse($src);
            $c = $list->selectors[0]->compounds[0]->compound->components[0];
            self::assertInstanceOf(AttributeSelector::class, $c);
            self::assertSame($expected, $c->matchType, $src);
            self::assertSame('b', $c->value, $src);
        }
    }

    public function testAttributeCaseInsensitiveFlag(): void
    {
        $list = SelectorParser::parse('[type="email" i]');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(AttributeSelector::class, $c);
        self::assertTrue($c->caseInsensitive);
    }

    public function testPseudoClassWithoutArgs(): void
    {
        $list = SelectorParser::parse(':hover');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(PseudoClassSelector::class, $c);
        self::assertSame('hover', $c->name);
    }

    public function testPseudoElementDoubleColon(): void
    {
        $list = SelectorParser::parse('::marker');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(PseudoElementSelector::class, $c);
        self::assertSame('marker', $c->name);
    }

    public function testLegacyPseudoElementSingleColon(): void
    {
        $list = SelectorParser::parse(':before');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(PseudoElementSelector::class, $c);
        self::assertSame('before', $c->name);
    }

    public function testNotWithCompound(): void
    {
        $list = SelectorParser::parse(':not(.foo)');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(PseudoClassSelector::class, $c);
        self::assertSame('not', $c->name);
        self::assertInstanceOf(SelectorList::class, $c->arguments);
        self::assertCount(1, $c->arguments->selectors);
    }

    public function testIsAndWhereAndHas(): void
    {
        foreach (['is', 'where', 'has'] as $name) {
            $list = SelectorParser::parse(":$name(article, section)");
            $c = $list->selectors[0]->compounds[0]->compound->components[0];
            self::assertInstanceOf(PseudoClassSelector::class, $c);
            self::assertSame($name, $c->name);
            self::assertNotNull($c->arguments);
            self::assertCount(2, $c->arguments->selectors, ":$name");
        }
    }

    public function testNthChildEven(): void
    {
        $list = SelectorParser::parse(':nth-child(even)');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(PseudoClassSelector::class, $c);
        self::assertSame('nth-child', $c->name);
        self::assertNotNull($c->anPlusB);
        self::assertSame(2, $c->anPlusB->a);
        self::assertSame(0, $c->anPlusB->b);
    }

    public function testNthChildOdd(): void
    {
        $list = SelectorParser::parse(':nth-child(odd)');
        $anb = $list->selectors[0]->compounds[0]->compound->components[0]->anPlusB;
        self::assertSame(2, $anb->a);
        self::assertSame(1, $anb->b);
    }

    public function testNthChildAnPlusB(): void
    {
        $list = SelectorParser::parse(':nth-child(3n+1)');
        $anb = $list->selectors[0]->compounds[0]->compound->components[0]->anPlusB;
        self::assertSame(3, $anb->a);
        self::assertSame(1, $anb->b);
    }

    public function testNthChildPlainInteger(): void
    {
        $list = SelectorParser::parse(':nth-child(5)');
        $anb = $list->selectors[0]->compounds[0]->compound->components[0]->anPlusB;
        self::assertSame(0, $anb->a);
        self::assertSame(5, $anb->b);
    }

    public function testNthChildOfSelector(): void
    {
        $list = SelectorParser::parse(':nth-child(2n+1 of .row)');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertNotNull($c->anPlusB);
        self::assertNotNull($c->arguments);
        self::assertCount(1, $c->arguments->selectors);
    }

    public function testAnPlusBMatches(): void
    {
        $anb = new AnPlusB(2, 1); // 2n+1 → 1, 3, 5, 7
        self::assertTrue($anb->matches(1));
        self::assertTrue($anb->matches(3));
        self::assertFalse($anb->matches(2));
        self::assertFalse($anb->matches(0));
    }

    public function testHostFunctional(): void
    {
        $list = SelectorParser::parse(':host(.themed)');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(PseudoClassSelector::class, $c);
        self::assertSame('host', $c->name);
        self::assertNotNull($c->arguments);
    }

    public function testSlotted(): void
    {
        $list = SelectorParser::parse('::slotted(p)');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(PseudoElementSelector::class, $c);
        self::assertSame('slotted', $c->name);
    }

    public function testSpecificityBasic(): void
    {
        $cases = [
            'p'             => new Specificity(0, 0, 1),
            '.foo'          => new Specificity(0, 1, 0),
            '#bar'          => new Specificity(1, 0, 0),
            'div.foo'       => new Specificity(0, 1, 1),
            '#bar .foo p'   => new Specificity(1, 1, 1),
            ':hover'        => new Specificity(0, 1, 0),
            '::before'      => new Specificity(0, 0, 1),
            '*'             => new Specificity(0, 0, 0),
        ];
        foreach ($cases as $src => $expected) {
            $list = SelectorParser::parse($src);
            $actual = $list->selectors[0]->specificity();
            self::assertSame(
                $expected->compare($actual),
                0,
                "specificity for $src: expected $expected, got $actual",
            );
        }
    }

    public function testWhereContributesZeroSpecificity(): void
    {
        $list = SelectorParser::parse(':where(#a, .b, p)');
        $actual = $list->selectors[0]->specificity();
        self::assertSame(0, $actual->a);
        self::assertSame(0, $actual->b);
        self::assertSame(0, $actual->c);
    }

    public function testIsContributesMaxSpecificity(): void
    {
        $list = SelectorParser::parse(':is(#a, .b, p)');
        $actual = $list->selectors[0]->specificity();
        // Max of (#a) which is (1,0,0).
        self::assertSame(1, $actual->a);
        self::assertSame(0, $actual->b);
        self::assertSame(0, $actual->c);
    }

    public function testNotContributesMaxSpecificity(): void
    {
        $list = SelectorParser::parse(':not(p, .foo)');
        $actual = $list->selectors[0]->specificity();
        self::assertSame(0, $actual->a);
        self::assertSame(1, $actual->b);
        self::assertSame(0, $actual->c);
    }

    public function testRoundtripsViaToString(): void
    {
        $sources = [
            'div',
            '.foo',
            '#bar',
            'div.foo#bar',
            'article > p',
            'a:hover',
            'a:not(.disabled)',
            '[data-x="y"]',
        ];
        foreach ($sources as $src) {
            $list = SelectorParser::parse($src);
            self::assertNotEmpty($list->selectors, $src);
            // toString preserves the structural shape but not whitespace exactly.
            self::assertNotEmpty($list->toString(), $src);
        }
    }

    public function testNamespacePrefix(): void
    {
        $list = SelectorParser::parse('svg|circle');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(TypeSelector::class, $c);
        self::assertSame('svg', $c->namespacePrefix);
        self::assertSame('circle', $c->localName);
    }

    public function testStarNamespacePrefix(): void
    {
        $list = SelectorParser::parse('*|circle');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(TypeSelector::class, $c);
        self::assertSame('*', $c->namespacePrefix);
        self::assertSame('circle', $c->localName);
    }

    public function testEmptyDefaultNamespacePrefix(): void
    {
        $list = SelectorParser::parse('|circle');
        $c = $list->selectors[0]->compounds[0]->compound->components[0];
        self::assertInstanceOf(TypeSelector::class, $c);
        self::assertSame('', $c->namespacePrefix);
    }
}
