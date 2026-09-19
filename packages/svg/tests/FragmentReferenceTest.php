<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Shape\Rect;
use PHPUnit\Framework\TestCase;

/**
 * URL Standard §fragment-percent-encode-set — a `url(#…)` reference
 * carries a URL FRAGMENT, and a fragment is percent-DECODED before it
 * is used to find the element it names. `url(#%66%6f%6f)` and
 * `url(#foo)` therefore name the same element.
 *
 * `findById()` stays a literal id lookup; `findByFragment()` is the
 * reference-resolution entry point that does the decode first.
 */
final class FragmentReferenceTest extends TestCase
{
    private function doc(): \Phpdftk\Svg\SvgDocument
    {
        return (new Parser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect id="foo" width="1" height="1"/>'
            . '<rect id="bar.baz" width="1" height="1"/>'
            . '<rect id="a%b" width="1" height="1"/>'
            . '</svg>',
        );
    }

    public function testPercentEncodedFragmentResolvesToTheDecodedId(): void
    {
        $found = $this->doc()->findByFragment('%66%6f%6f');
        self::assertInstanceOf(Rect::class, $found);
        self::assertSame('foo', $found->getAttribute('id'));
    }

    public function testPercentEncodedDotResolves(): void
    {
        $found = $this->doc()->findByFragment('%62%61%72%2e%62%61%7a');
        self::assertInstanceOf(Rect::class, $found);
        self::assertSame('bar.baz', $found->getAttribute('id'));
    }

    public function testUnencodedFragmentStillResolves(): void
    {
        $found = $this->doc()->findByFragment('foo');
        self::assertInstanceOf(Rect::class, $found);
        self::assertSame('foo', $found->getAttribute('id'));
    }

    /**
     * A lone `%` is not a valid escape sequence, so the fragment
     * decodes to itself and the literal id still resolves. Decoding
     * must not destroy ids that merely LOOK encoded.
     */
    public function testLiteralPercentIdStillResolves(): void
    {
        $found = $this->doc()->findByFragment('a%b');
        self::assertInstanceOf(Rect::class, $found);
        self::assertSame('a%b', $found->getAttribute('id'));
    }

    public function testUnknownFragmentResolvesToNull(): void
    {
        self::assertNull($this->doc()->findByFragment('%6e%6f%70%65'));
    }

    public function testEmptyFragmentResolvesToNull(): void
    {
        self::assertNull($this->doc()->findByFragment(''));
    }

    /**
     * `findById` is NOT a fragment resolver — it must keep treating its
     * argument as a literal id, or an element genuinely named
     * `%66%6f%6f` would become unreachable.
     */
    public function testFindByIdDoesNotDecode(): void
    {
        self::assertNull($this->doc()->findById('%66%6f%6f'));
    }

    public function testUseHrefResolvesThroughPercentEncodedFragment(): void
    {
        $doc = (new Parser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><rect id="foo" width="1" height="1"/></defs>'
            . '<use href="#%66%6f%6f"/>'
            . '</svg>',
        );
        $use = $doc->findByTag('use')[0] ?? null;
        self::assertInstanceOf(\Phpdftk\Svg\Use_::class, $use);
        $referent = $use->resolve($doc);
        self::assertInstanceOf(Rect::class, $referent);
        self::assertSame('foo', $referent->getAttribute('id'));
    }
}
