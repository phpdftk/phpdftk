<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Css;

use Phpdftk\Svg\Css\MatchableSvgElement;
use Phpdftk\Svg\GenericElement;
use Phpdftk\Svg\Text;
use PHPUnit\Framework\TestCase;

/**
 * Covers the SVG → CSS-matcher adapter: name/namespace/id/class/attribute
 * access plus the Selectors traversal and nth-child indexing, including
 * the element-filtering of interleaved {@see Text} data nodes and the
 * no-parent (root) fallbacks.
 *
 * Tree under test (whitespace Text nodes interleaved):
 *   g#root .container .box
 *     "\n"           (Text — invisible to the matcher)
 *     rect#first .box .red  fill=green
 *     circle .dot
 *     "\n"           (Text)
 *     rect .box
 */
final class MatchableSvgElementTest extends TestCase
{
    private GenericElement $g;
    private GenericElement $rect1;
    private GenericElement $circle;
    private GenericElement $rect2;

    protected function setUp(): void
    {
        $this->g = new GenericElement('g');
        $this->g->setAttribute('id', 'root');
        $this->g->setAttribute('class', 'container box');

        $this->rect1 = new GenericElement('rect');
        $this->rect1->setAttribute('id', 'First'); // mixed case → lower-cased id
        $this->rect1->setAttribute('class', 'box red');
        $this->rect1->setAttribute('fill', 'green');

        $this->circle = new GenericElement('circle');
        $this->circle->setAttribute('class', 'dot');

        $this->rect2 = new GenericElement('rect');
        $this->rect2->setAttribute('class', 'box');

        $this->g->appendChild(new Text("\n  "));
        $this->g->appendChild($this->rect1);
        $this->g->appendChild($this->circle);
        $this->g->appendChild(new Text("\n  "));
        $this->g->appendChild($this->rect2);
    }

    private function wrap(GenericElement $e): MatchableSvgElement
    {
        return new MatchableSvgElement($e);
    }

    // ---- Name / namespace / id / class ----------------------------------

    public function testLocalNameAndNamespace(): void
    {
        self::assertSame('rect', $this->wrap($this->rect1)->localName());
        self::assertSame(MatchableSvgElement::SVG_NS, $this->wrap($this->circle)->namespaceUri());
    }

    public function testElementIdIsLowerCasedOrNull(): void
    {
        self::assertSame('first', $this->wrap($this->rect1)->elementId());
        // No id attribute → null.
        self::assertNull($this->wrap($this->circle)->elementId());
        // Whitespace-only id → null (not an empty string).
        $blank = new GenericElement('rect');
        $blank->setAttribute('id', '   ');
        self::assertNull($this->wrap($blank)->elementId());
    }

    public function testClasses(): void
    {
        self::assertSame(['box', 'red'], $this->wrap($this->rect1)->classes());
        self::assertSame(['dot'], $this->wrap($this->circle)->classes());
        // No class attribute → empty list.
        self::assertSame([], $this->wrap(new GenericElement('rect'))->classes());
    }

    // ---- Attributes ------------------------------------------------------

    public function testAttributeAccess(): void
    {
        $m = $this->wrap($this->rect1);
        self::assertTrue($m->hasAttribute('fill'));
        self::assertSame('green', $m->getAttributeValue('fill'));
        self::assertFalse($m->hasAttribute('stroke'));
        self::assertNull($m->getAttributeValue('stroke'));
        self::assertSame(
            ['id' => 'First', 'class' => 'box red', 'fill' => 'green'],
            $m->allAttributes(),
        );
    }

    // ---- Traversal (Text nodes filtered out) -----------------------------

    public function testParentElement(): void
    {
        $parent = $this->wrap($this->rect1)->parentElement();
        self::assertInstanceOf(MatchableSvgElement::class, $parent);
        self::assertSame('g', $parent->localName());
        // Root has no parent.
        self::assertNull($this->wrap($this->g)->parentElement());
    }

    public function testElementChildrenSkipsTextNodes(): void
    {
        $children = $this->wrap($this->g)->elementChildren();
        self::assertCount(3, $children); // the two Text nodes are filtered
        self::assertSame(['rect', 'circle', 'rect'], array_map(static fn($c) => $c->localName(), $children));
        // A childless element yields no element children.
        self::assertSame([], $this->wrap($this->circle)->elementChildren());
    }

    public function testSiblingTraversalSkipsTextNodes(): void
    {
        self::assertNull($this->wrap($this->rect1)->previousElementSibling()); // first element
        self::assertSame('circle', $this->wrap($this->rect1)->nextElementSibling()?->localName());
        self::assertSame('rect', $this->wrap($this->circle)->previousElementSibling()?->localName());
        self::assertSame('rect', $this->wrap($this->circle)->nextElementSibling()?->localName());
        self::assertSame('circle', $this->wrap($this->rect2)->previousElementSibling()?->localName());
        self::assertNull($this->wrap($this->rect2)->nextElementSibling()); // last element
    }

    public function testRootHasNoSiblings(): void
    {
        self::assertNull($this->wrap($this->g)->previousElementSibling());
        self::assertNull($this->wrap($this->g)->nextElementSibling());
    }

    // ---- nth-child indexing (1-based) ------------------------------------

    public function testIndexAmongSiblings(): void
    {
        // Element positions 1..3 (Text nodes don't count).
        self::assertSame(1, $this->wrap($this->rect1)->indexAmongSiblings());
        self::assertSame(2, $this->wrap($this->circle)->indexAmongSiblings());
        self::assertSame(3, $this->wrap($this->rect2)->indexAmongSiblings());
        // nth-last-child.
        self::assertSame(3, $this->wrap($this->rect1)->indexAmongSiblingsFromEnd());
        self::assertSame(2, $this->wrap($this->circle)->indexAmongSiblingsFromEnd());
        self::assertSame(1, $this->wrap($this->rect2)->indexAmongSiblingsFromEnd());
    }

    public function testIndexAmongTypeSiblings(): void
    {
        // Two rects, one circle → nth-of-type.
        self::assertSame(1, $this->wrap($this->rect1)->indexAmongTypeSiblings());
        self::assertSame(2, $this->wrap($this->rect2)->indexAmongTypeSiblings());
        self::assertSame(1, $this->wrap($this->circle)->indexAmongTypeSiblings());
        // nth-last-of-type.
        self::assertSame(2, $this->wrap($this->rect1)->indexAmongTypeSiblingsFromEnd());
        self::assertSame(1, $this->wrap($this->rect2)->indexAmongTypeSiblingsFromEnd());
        self::assertSame(1, $this->wrap($this->circle)->indexAmongTypeSiblingsFromEnd());
    }

    public function testRootIndicesFallBackToOne(): void
    {
        // No parent → all index queries return 1 (a lone :first/:only child).
        $m = $this->wrap($this->g);
        self::assertSame(1, $m->indexAmongSiblings());
        self::assertSame(1, $m->indexAmongSiblingsFromEnd());
        self::assertSame(1, $m->indexAmongTypeSiblings());
        self::assertSame(1, $m->indexAmongTypeSiblingsFromEnd());
    }
}
