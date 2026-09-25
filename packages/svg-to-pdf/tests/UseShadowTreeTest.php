<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\SvgDocument;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §5.6 — a `<use>` generates a SHADOW TREE: a clone of the
 * referenced subtree, parented on the `<use>` itself. Inherited
 * properties therefore come from the `<use>`, not from wherever the
 * original sits, and document-tree selectors cannot reach into the
 * clone.
 *
 * The standalone SVG pipeline had neither half. It painted the
 * referent where it stood, so `<use fill="green">` never reached the
 * shapes inside, and a `.container rect` rule that should stop at the
 * shadow boundary styled them anyway.
 */
final class UseShadowTreeTest extends TestCase
{
    private function project(string $body): SvgDocument
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        return $doc;
    }

    private function paint(string $body): string
    {
        $doc = $this->project($body);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    private const string GREEN = '0 0.5019607843 0 rg';
    private const string RED = '1 0 0 rg';

    public function testTheReferentInheritsFromTheUse(): void
    {
        $ops = $this->paint(
            '<defs><rect id="r" width="10" height="10"/></defs>'
            . '<use href="#r" fill="green"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString('0 0 0 rg', $ops);
    }

    public function testADocumentSelectorDoesNotReachIntoTheShadowTree(): void
    {
        // WPT struct/reftests/use-inheritance-001: `.container rect`
        // must NOT match the clone, because the clone's parent is the
        // `<use>`, not the `.container` group.
        $ops = $this->paint(
            '<style>.container rect { fill: red } rect { fill: green }</style>'
            . '<defs><g class="container"><rect id="r" width="10" height="10"/></g></defs>'
            . '<use href="#r"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString(self::RED, $ops);
    }

    public function testATypeSelectorStillMatchesInsideTheShadowTree(): void
    {
        // The other half of §5.6: a rule that names only the element
        // type still applies to a shadow-tree node.
        $ops = $this->paint(
            '<style>rect { fill: green }</style>'
            . '<defs><rect id="r" width="10" height="10"/></defs>'
            . '<use href="#r"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
    }

    public function testTheReferentsOwnValueStillBeatsTheInheritedOne(): void
    {
        $ops = $this->paint(
            '<defs><rect id="r" width="10" height="10" fill="green"/></defs>'
            . '<use href="#r" fill="red"/>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
        self::assertStringNotContainsString(self::RED, $ops);
    }

    public function testTheOriginalIsStillStyledInItsOwnPosition(): void
    {
        // Guard: materialising the clone must not restyle the source.
        // A `<use>` in `<defs>` and a second, direct rendering of the
        // same subtree have to stay independent.
        $doc = $this->project(
            '<g fill="red"><rect id="r" width="10" height="10"/></g>'
            . '<use href="#r" fill="green"/>',
        );
        $rects = $doc->findByTag('rect');
        self::assertNotSame([], $rects);
        $original = $rects[0];
        self::assertNull($original->getAttribute('data-phpdftk-use-instance'));
        $fill = $original->fill();
        self::assertInstanceOf(\Phpdftk\Svg\Value\Paint\SolidColor::class, $fill);
        self::assertSame([1.0, 0.0, 0.0], $fill->color->toArray());
    }

    public function testShadowTreeIdsAreStrippedSoUrlLookupsKeepFindingTheOriginal(): void
    {
        // §5.6 — a shadow node is not addressable by id. Leaving the
        // id on would let a later `url(#r)` resolve to the clone.
        $doc = $this->project(
            '<defs><rect id="r" width="10" height="10"/></defs><use href="#r"/>',
        );
        $found = $doc->findByFragment('r');
        self::assertNotNull($found);
        self::assertNull($found->getAttribute('data-phpdftk-use-instance'));
    }

    public function testACircularUseDoesNotRecurseForever(): void
    {
        // Guard: `<use>` pointing at its own ancestor is invalid, and
        // cloning it naively is unbounded.
        $ops = $this->paint(
            '<g id="g"><use href="#g"/><rect width="10" height="10" fill="green"/></g>',
        );
        self::assertStringContainsString(self::GREEN, $ops);
    }

    public function testAUseWithNoReferentIsLeftAlone(): void
    {
        $doc = $this->project('<use href="#nothing"/>');
        $uses = $doc->findByTag('use');
        self::assertCount(1, $uses);
        self::assertSame([], $uses[0]->children);
    }
}
