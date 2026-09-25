<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * HTML Standard §15.3.9 "Lists" — the `type` presentational hints that
 * the user-agent stylesheet maps onto `list-style-type`:
 *
 *   ol[type="1"], li[type="1"]        { list-style-type: decimal; }
 *   ol[type=a s], li[type=a s]        { list-style-type: lower-alpha; }
 *   ol[type=A s], li[type=A s]        { list-style-type: upper-alpha; }
 *   ol[type=i s], li[type=i s]        { list-style-type: lower-roman; }
 *   ol[type=I s], li[type=I s]        { list-style-type: upper-roman; }
 *   ul[type=none i], li[type=none i]  { list-style-type: none; }
 *   ul[type=disc i], li[type=disc i]  { list-style-type: disc; }
 *   ul[type=circle i], li[type=circle i] { list-style-type: circle; }
 *   ul[type=square i], li[type=square i] { list-style-type: square; }
 *
 * Three properties the selectors encode and this suite pins:
 *
 *  1. The ORDERED keywords are case-SENSITIVE (`s` flag) — `type=a` and
 *     `type=A` are different counter styles. `type` is on HTML's
 *     case-insensitive attribute list, so without the flag they collapse.
 *  2. The BULLET keywords are ASCII case-INSENSITIVE (`i` flag).
 *  3. A keyword on the wrong element matches NOTHING: `<ol type=circle>`
 *     stays decimal and `<ul type=1>` stays disc. The element type is part
 *     of the selector, so cross-application cannot happen.
 *
 * These run against the REAL shipped UA stylesheet, not a stand-in, so a
 * rule deleted from `RendererOptions` fails here.
 */
final class ListTypeAttributeTest extends TestCase
{
    private BoxGenerator $generator;
    private CssParser $css;
    private HtmlParser $html;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $this->generator = new BoxGenerator(new Cascade(PropertyRegistry::default()));
    }

    /**
     * Resolve `list-style-type` on the first element with `$id`, rendered
     * against the shipped user-agent stylesheet.
     */
    private function listStyleType(string $bodyHtml, string $id): string
    {
        $ua = $this->css->parseStylesheet(
            (new RendererOptions())->effectiveUserAgentStylesheet(),
            Origin::UserAgent,
        );
        $doc = $this->html->parseDocument('<!doctype html><html><body>' . $bodyHtml . '</body></html>');
        $root = $this->generator->generate($doc, [$ua]);
        $box = $this->findById($root, $id);
        self::assertNotNull($box, "no box generated for #$id");
        $kw = $box->style->get('list-style-type');
        self::assertInstanceOf(Keyword::class, $kw, "list-style-type on #$id is not a keyword");
        return $kw->name;
    }

    private function findById(Box $root, string $id): ?Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->element?->getAttribute('id') === $id) {
                return $node;
            }
            foreach ($node->children as $child) {
                $stack[] = $child;
            }
        }
        return null;
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function supportedProvider(): iterable
    {
        // <ol type>: the five ordered keywords, case-sensitive.
        yield 'ol type=1'      => ['<ol id=t type="1"><li>x</li></ol>', 't', 'decimal'];
        yield 'ol type=a'      => ['<ol id=t type="a"><li>x</li></ol>', 't', 'lower-alpha'];
        yield 'ol type=A'      => ['<ol id=t type="A"><li>x</li></ol>', 't', 'upper-alpha'];
        yield 'ol type=i'      => ['<ol id=t type="i"><li>x</li></ol>', 't', 'lower-roman'];
        yield 'ol type=I'      => ['<ol id=t type="I"><li>x</li></ol>', 't', 'upper-roman'];
        // <ul type>: the four bullet keywords, ASCII case-insensitive.
        yield 'ul type=disc'   => ['<ul id=t type="disc"><li>x</li></ul>', 't', 'disc'];
        yield 'ul type=circle' => ['<ul id=t type="circle"><li>x</li></ul>', 't', 'circle'];
        yield 'ul type=square' => ['<ul id=t type="square"><li>x</li></ul>', 't', 'square'];
        yield 'ul type=none'   => ['<ul id=t type="none"><li>x</li></ul>', 't', 'none'];
        yield 'ul type=SQUARE' => ['<ul id=t type="SQUARE"><li>x</li></ul>', 't', 'square'];
        yield 'ul type=NoNe'   => ['<ul id=t type="NoNe"><li>x</li></ul>', 't', 'none'];
        // <li type>: takes BOTH families.
        yield 'li type=I'      => ['<ul><li id=t type="I">x</li></ul>', 't', 'upper-roman'];
        yield 'li type=a'      => ['<ol><li id=t type="a">x</li></ol>', 't', 'lower-alpha'];
        yield 'li type=CiRcLe' => ['<ol><li id=t type="CiRcLe">x</li></ol>', 't', 'circle'];
        yield 'li type=none'   => ['<ol><li id=t type="none">x</li></ol>', 't', 'none'];
        // A bare <li> with no list ancestor still honours its own type.
        yield 'bare li type=A' => ['<li id=t type="A">x</li>', 't', 'upper-alpha'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('supportedProvider')]
    public function testSupportedTypeKeyword(string $body, string $id, string $expected): void
    {
        self::assertSame($expected, $this->listStyleType($body, $id));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function unsupportedProvider(): iterable
    {
        // Bullet keywords are NOT accepted on <ol> — it stays decimal.
        yield 'ol type=disc'    => ['<ol id=t type="disc"><li>x</li></ol>', 't', 'decimal'];
        yield 'ol type=circle'  => ['<ol id=t type="circle"><li>x</li></ol>', 't', 'decimal'];
        yield 'ol type=square'  => ['<ol id=t type="square"><li>x</li></ol>', 't', 'decimal'];
        yield 'ol type=none'    => ['<ol id=t type="none"><li>x</li></ol>', 't', 'decimal'];
        yield 'ol type=CIRCLE'  => ['<ol id=t type="CIRCLE"><li>x</li></ol>', 't', 'decimal'];
        // Ordered keywords are NOT accepted on <ul> — it stays disc.
        yield 'ul type=1'       => ['<ul id=t type="1"><li>x</li></ul>', 't', 'disc'];
        yield 'ul type=a'       => ['<ul id=t type="a"><li>x</li></ul>', 't', 'disc'];
        yield 'ul type=A'       => ['<ul id=t type="A"><li>x</li></ul>', 't', 'disc'];
        yield 'ul type=decimal' => ['<ul id=t type="decimal"><li>x</li></ul>', 't', 'disc'];
        // Long-form CSS keywords are not the HTML attribute vocabulary.
        yield 'ul type=lower-alpha' => ['<ul id=t type="lower-alpha"><li>x</li></ul>', 't', 'disc'];
        yield 'li type=lower-alpha' => ['<ol><li id=t type="lower-alpha">x</li></ol>', 't', 'decimal'];
        // Ordered keywords are case-SENSITIVE: `type=I` is upper-roman, so
        // the lowercase spelling of a keyword typed in the wrong case must
        // NOT fall through to the other one via the HTML case-insensitive
        // attribute list.
        yield 'li type=1 in ul' => ['<ul><li id=t type="1">x</li></ul>', 't', 'decimal'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsupportedProvider')]
    public function testUnsupportedTypeKeyword(string $body, string $id, string $expected): void
    {
        self::assertSame($expected, $this->listStyleType($body, $id));
    }

    /**
     * Author CSS is a later origin than the UA sheet, so it beats the
     * presentational hint outright.
     */
    public function testAuthorCssBeatsTheTypeHint(): void
    {
        $ua = $this->css->parseStylesheet(
            (new RendererOptions())->effectiveUserAgentStylesheet(),
            Origin::UserAgent,
        );
        $author = $this->css->parseStylesheet('ol { list-style-type: lower-greek; }', Origin::Author);
        $doc = $this->html->parseDocument(
            '<!doctype html><html><body><ol id=t type="A"><li>x</li></ol></body></html>',
        );
        $root = $this->generator->generate($doc, [$ua, $author]);
        $box = $this->findById($root, 't');
        self::assertNotNull($box);
        $kw = $box->style->get('list-style-type');
        self::assertInstanceOf(Keyword::class, $kw);
        self::assertSame('lower-greek', $kw->name);
    }

    /**
     * The `<li type>` hint is a declaration ON the `li`, so it beats the
     * value the `li` would otherwise INHERIT from an author rule on the
     * list — inheritance only supplies a value where no declaration
     * matched. The old presentational-hint code could not express this: it
     * refused to apply whenever the inherited value was not `disc` or
     * `decimal`.
     */
    public function testLiTypeHintBeatsInheritanceFromAnAuthorStyledList(): void
    {
        $ua = $this->css->parseStylesheet(
            (new RendererOptions())->effectiveUserAgentStylesheet(),
            Origin::UserAgent,
        );
        $author = $this->css->parseStylesheet('ol { list-style-type: lower-greek; }', Origin::Author);
        $doc = $this->html->parseDocument(
            '<!doctype html><html><body><ol><li id=t type="A">x</li></ol></body></html>',
        );
        $root = $this->generator->generate($doc, [$ua, $author]);
        $box = $this->findById($root, 't');
        self::assertNotNull($box);
        $kw = $box->style->get('list-style-type');
        self::assertInstanceOf(Keyword::class, $kw);
        self::assertSame('upper-alpha', $kw->name);
    }
}
