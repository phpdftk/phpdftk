<?php

declare(strict_types=1);

namespace Phpdftk\Xml\Tests;

use Phpdftk\Xml\HardenedLoader;
use Phpdftk\Xml\TreeWalker;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the generic typed-tree walker. Each test wires the
 * walker against a tiny ad-hoc typed-element class so the walker's
 * own correctness can be verified without coupling to either
 * `phpdftk/svg` or `phpdftk/mathml`.
 */
final class TreeWalkerTest extends TestCase
{
    private TreeWalker $walker;
    private HardenedLoader $loader;

    protected function setUp(): void
    {
        $this->walker = new TreeWalker();
        $this->loader = new HardenedLoader();
    }

    // -----------------------------------------------------------------
    // Negative + edge cases
    // -----------------------------------------------------------------

    public function testSkipsCommentsAndProcessingInstructions(): void
    {
        // Comments and PIs aren't part of the typed tree — the
        // walker must skip them so consumer code never has to.
        $xml = <<<XML
        <root>
          <!-- a comment -->
          <?xml-stylesheet href="x.css" ?>
          <child/>
        </root>
        XML;
        $root = $this->walkInto($xml);
        // Only one element child (the <child/>), plus surrounding
        // whitespace text nodes.
        $elements = array_values(array_filter(
            $root->children,
            static fn($n) => $n instanceof FakeElement,
        ));
        self::assertCount(1, $elements);
        self::assertSame('child', $elements[0]->localName);
    }

    public function testSkipsXmlnsDeclarations(): void
    {
        // xmlns / xmlns:foo are namespace declarations, not author
        // attributes; the walker must drop them so the consumer's
        // attribute map stays clean.
        $xml = '<root xmlns="urn:test" xmlns:x="urn:x" data-real="1"/>';
        $root = $this->walkInto($xml);
        self::assertArrayNotHasKey('xmlns', $root->attributes);
        self::assertArrayNotHasKey('xmlns:x', $root->attributes);
        self::assertSame('1', $root->attributes['data-real'] ?? null);
    }

    public function testHandlesPathologicallyDeepTree(): void
    {
        // Construct a 50-deep nested tree. Walker must not blow the
        // stack at this depth (PHP's default xdebug.max_nesting_level
        // is 512; we stay well below that).
        $xml = '<r>';
        for ($i = 0; $i < 50; $i++) {
            $xml .= "<n$i>";
        }
        for ($i = 49; $i >= 0; $i--) {
            $xml .= "</n$i>";
        }
        $xml .= '</r>';

        $root = $this->walkInto($xml);
        // Walk the chain manually to confirm depth.
        $cursor = $root;
        for ($i = 0; $i < 50; $i++) {
            $children = array_values(array_filter(
                $cursor->children,
                static fn($n) => $n instanceof FakeElement,
            ));
            self::assertCount(1, $children, "depth $i missing child");
            $cursor = $children[0];
        }
    }

    public function testHandlesEmptyRootElement(): void
    {
        $root = $this->walkInto('<root/>');
        self::assertSame('root', $root->localName);
        self::assertSame([], $root->children);
        self::assertSame([], $root->attributes);
    }

    public function testRejectsNonElementSrc(): void
    {
        // The walker expects a DOMElement; passing a DOMDocument or
        // DOMText directly would be a programming error. Type system
        // catches it at compile-time on a real call site; we just
        // confirm a wrong type triggers a hard error rather than a
        // silent miss.
        $dom = $this->loader->load('<r/>');
        $this->expectException(\TypeError::class);
        $this->walker->walk(
            $dom, // DOMDocument, not DOMElement
            new FakeElement('r'),
            fn(string $name) => new FakeElement($name),
            fn(string $data) => new FakeText($data),
            fn(FakeElement $el, string $n, string $v) => $el->attributes[$n] = $v,
            fn(FakeElement $p, FakeNode $c) => $p->children[] = $c,
        );
    }

    // -----------------------------------------------------------------
    // Positive cases
    // -----------------------------------------------------------------

    public function testCopiesAttributesVerbatim(): void
    {
        $root = $this->walkInto('<r a="one" b="two" c=""/>');
        self::assertSame('one', $root->attributes['a']);
        self::assertSame('two', $root->attributes['b']);
        self::assertSame('', $root->attributes['c']);
    }

    public function testCopiesPrefixedAttributesWithQualifiedName(): void
    {
        // xlink:href vs href must remain distinct — the walker uses
        // the qualified node name so they don't collapse.
        $xml = '<r xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'href="local" xlink:href="external"/>';
        $root = $this->walkInto($xml);
        self::assertSame('local', $root->attributes['href']);
        self::assertSame('external', $root->attributes['xlink:href']);
    }

    public function testBuildsNestedElementChain(): void
    {
        $root = $this->walkInto(
            '<r><a><b><c>leaf</c></b></a></r>',
        );
        $a = $root->children[0];
        self::assertInstanceOf(FakeElement::class, $a);
        self::assertSame('a', $a->localName);
        $b = $a->children[0];
        self::assertInstanceOf(FakeElement::class, $b);
        self::assertSame('b', $b->localName);
        $c = $b->children[0];
        self::assertInstanceOf(FakeElement::class, $c);
        self::assertSame('c', $c->localName);
        // <c>leaf</c> — c has a single text child.
        self::assertCount(1, $c->children);
        self::assertInstanceOf(FakeText::class, $c->children[0]);
        self::assertSame('leaf', $c->children[0]->data);
    }

    public function testPreservesInterleavedTextAndElements(): void
    {
        $root = $this->walkInto('<r>before<a/>middle<b/>after</r>');
        self::assertCount(5, $root->children);
        self::assertInstanceOf(FakeText::class, $root->children[0]);
        self::assertSame('before', $root->children[0]->data);
        self::assertInstanceOf(FakeElement::class, $root->children[1]);
        self::assertSame('a', $root->children[1]->localName);
        self::assertInstanceOf(FakeText::class, $root->children[2]);
        self::assertSame('middle', $root->children[2]->data);
        self::assertInstanceOf(FakeElement::class, $root->children[3]);
        self::assertSame('b', $root->children[3]->localName);
        self::assertInstanceOf(FakeText::class, $root->children[4]);
        self::assertSame('after', $root->children[4]->data);
    }

    public function testElementFactoryReceivesRawLocalName(): void
    {
        $seen = [];
        $dom = $this->loader->load('<r><Alpha/><BETA/><gamma/></r>');
        $root = new FakeElement($dom->documentElement->localName);
        $this->walker->walk(
            $dom->documentElement,
            $root,
            function (string $name) use (&$seen) {
                $seen[] = $name;
                return new FakeElement($name);
            },
            fn(string $data) => new FakeText($data),
            fn(FakeElement $el, string $n, string $v) => $el->attributes[$n] = $v,
            fn(FakeElement $p, FakeNode $c) => $p->children[] = $c,
        );
        // Source casing is preserved — case-sensitive XML local
        // names round-trip as the author wrote them.
        self::assertSame(['Alpha', 'BETA', 'gamma'], $seen);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function walkInto(string $xml): FakeElement
    {
        $dom = $this->loader->load($xml);
        $root = new FakeElement($dom->documentElement->localName);
        $this->walker->walk(
            $dom->documentElement,
            $root,
            fn(string $name) => new FakeElement($name),
            fn(string $data) => new FakeText($data),
            fn(FakeElement $el, string $n, string $v) => $el->attributes[$n] = $v,
            fn(FakeElement $p, FakeNode $c) => $p->children[] = $c,
        );
        return $root;
    }
}

abstract class FakeNode {}

final class FakeText extends FakeNode
{
    public function __construct(public string $data) {}
}

final class FakeElement extends FakeNode
{
    /** @var array<string, string> */
    public array $attributes = [];

    /** @var list<FakeNode> */
    public array $children = [];

    public function __construct(public readonly string $localName) {}
}
