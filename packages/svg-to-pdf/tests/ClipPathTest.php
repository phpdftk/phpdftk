<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * `clip-path="url(#id)"` resolves the referenced `<clipPath>` and emits
 * its geometry as a PDF clipping region (`W` / `W*` + `n`) inside the
 * `q`/`Q` wrap that already isolates per-element graphics state.
 */
final class ClipPathTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    private function paint(string $svg): string
    {
        $doc = $this->svgParser->parse($svg);
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testElementWithoutClipPathRendersNormally(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
        );
        self::assertStringNotContainsString("\nW\n", $ops);
        self::assertStringNotContainsString("\nW*\n", $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testRectClipPathEmitsRectAndWThenN(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="userSpaceOnUse">'
            . '<rect x="5" y="5" width="20" height="20"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="40" height="40" fill="red" clip-path="url(#clip)"/>'
            . '</svg>',
        );
        // Clip rectangle path emitted first, then W (nonzero default), then n.
        self::assertStringContainsString('5 5 20 20 re', $ops);
        $lines = explode("\n", $ops);
        $wIndex = array_search('W', $lines, true);
        $nIndex = array_search('n', $lines, true);
        self::assertNotFalse($wIndex);
        self::assertNotFalse($nIndex);
        self::assertSame($nIndex, $wIndex + 1);
        // The element's actual rect paint follows.
        self::assertStringContainsString('0 0 40 40 re', $ops);
    }

    public function testClipRuleEvenoddEmitsWStar(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="userSpaceOnUse" clip-rule="evenodd">'
            . '<rect width="10" height="10"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="20" height="20" fill="red" clip-path="url(#clip)"/>'
            . '</svg>',
        );
        $lines = explode("\n", $ops);
        self::assertContains('W*', $lines);
    }

    public function testMissingClipPathFallsBackToNoClip(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" clip-path="url(#nope)"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString("\nW", $ops);
        // Element still paints (unclipped).
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testClipPathNoneIsTreatedAsAbsent(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" clip-path="none"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString("\nW", $ops);
    }

    public function testMalformedClipPathReferenceIsIgnored(): void
    {
        // No `url(#…)` form → fall through to no clip.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" clip-path="not-a-url"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString("\nW", $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testCircleAndPolygonClipsConstructTheirGeometry(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="circ" clipPathUnits="userSpaceOnUse">'
            . '<circle cx="50" cy="50" r="25"/>'
            . '</clipPath>'
            . '<clipPath id="poly" clipPathUnits="userSpaceOnUse">'
            . '<polygon points="0,0 100,0 50,80"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="100" height="100" fill="red" clip-path="url(#circ)"/>'
            . '<rect width="100" height="100" fill="blue" clip-path="url(#poly)"/>'
            . '</svg>',
        );
        // Circle clip: 4 cubic Béziers + closePath. Only the
        // referenced clip path constructs — `poly` is referenced by
        // the second rect, so only it contributes its polygon
        // operators (no cubics).
        self::assertSame(4, substr_count($ops, ' c'));
        // Polygon: m + l + l + h for the closed triangle in the clip.
        self::assertStringContainsString('0 0 m', $ops);
        self::assertStringContainsString('100 0 l', $ops);
        self::assertStringContainsString('50 80 l', $ops);
    }

    public function testObjectBoundingBoxModeAppliesBboxCmAndItsInverse(): void
    {
        // bbox = (10, 20, 50, 30); clipPath child rect at (0, 0) - (1, 1)
        // gets reified to user-space (10, 20) - (60, 50) by the bbox cm.
        // Painter applies the cm before constructing the clip path and
        // its inverse after, so the element's own coordinates aren't
        // disturbed.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="objectBoundingBox">'
            . '<rect width="1" height="1"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect x="10" y="20" width="50" height="30" fill="red" '
            . 'clip-path="url(#clip)"/>'
            . '</svg>',
        );
        // bbox cm: 50 0 0 30 10 20.
        self::assertStringContainsString('50 0 0 30 10 20 cm', $ops);
        // Inverse cm: 0.02 0 0 0.033… -0.2 -0.666… (1/50, 1/30, -10/50, -20/30).
        // The exact serialization rounds to 6 decimal places — match the
        // prefix that's stable across PHP versions.
        self::assertMatchesRegularExpression(
            '!0\.02 0 0 0\.0333333333 -0\.2 -0\.6666666667 cm!',
            $ops,
        );
        // The clip rect was emitted in bbox space.
        self::assertStringContainsString('0 0 1 1 re', $ops);
        // The painted rect uses its own user-space coords.
        self::assertStringContainsString('10 20 50 30 re', $ops);
    }

    public function testTransformAndClipPathCoexistOnSameElement(): void
    {
        // Both wrap into the same q/Q pair; transform applies first
        // so the clip is constructed in the transformed space.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="userSpaceOnUse">'
            . '<rect width="10" height="10"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="20" height="20" fill="red" '
            . 'transform="translate(5, 5)" clip-path="url(#clip)"/>'
            . '</svg>',
        );
        $lines = explode("\n", $ops);
        $qCount = count(array_filter($lines, static fn(string $l): bool => $l === 'q'));
        $bigQCount = count(array_filter($lines, static fn(string $l): bool => $l === 'Q'));
        self::assertSame(1, $qCount);
        self::assertSame(1, $bigQCount);
        self::assertStringContainsString('1 0 0 1 5 5 cm', $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops); // clip
        self::assertStringContainsString('0 0 20 20 re', $ops); // body
    }

    public function testClipPathDoesNotPaintItsChildrenStandalone(): void
    {
        // The clipPath itself never paints — the `<defs>` skip + the
        // ClipPath element type both contribute. Only the referenced
        // rect should appear, not a fill of the clip rect.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<clipPath id="clip" clipPathUnits="userSpaceOnUse">'
            . '<rect width="10" height="10" fill="red"/>'
            . '</clipPath>'
            . '<rect width="20" height="20" fill="blue" clip-path="url(#clip)"/>'
            . '</svg>',
        );
        // Only the clip's W + the body's fill should land.
        // Clip rect is 0 0 10 10; body rect is 0 0 20 20.
        $lines = explode("\n", $ops);
        $fillCount = count(array_filter($lines, static fn(string $l): bool => $l === 'f'));
        // Exactly one `f` from the body fill — the clip's `n` discards
        // its constructed path without filling.
        self::assertSame(1, $fillCount);
    }

    public function testClipPathTransformWrapsChildPathConstruction(): void
    {
        // `transform="translate(10, 5)"` on `<clipPath>` shifts its
        // children's coords before they contribute to the clip region.
        // The painter emits the transform cm, then the inverse cm
        // afterwards so the body paints in its original user space.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="userSpaceOnUse" '
            . 'transform="translate(10, 5)">'
            . '<rect width="20" height="20"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="40" height="40" fill="red" clip-path="url(#clip)"/>'
            . '</svg>',
        );
        // Forward translate, then the constructed rect, then the
        // inverse translate before W.
        self::assertStringContainsString('1 0 0 1 10 5 cm', $ops);
        self::assertStringContainsString('0 0 20 20 re', $ops);
        self::assertStringContainsString('1 0 0 1 -10 -5 cm', $ops);
        $lines = explode("\n", $ops);
        self::assertContains('W', $lines);
    }

    public function testBasicShapeClipPathClipsToTheObjectBoundingBox(): void
    {
        // CSS Masking 1 §6 — `clip-path: <basic-shape>` on an SVG
        // graphics element. An SVG element has no CSS layout box, so the
        // default `border-box` reference reduces to its fill box: here
        // the rect's own (30, 30, 100, 100) geometry, giving a circle of
        // radius 50 centred at (80, 80).
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect x="30" y="30" width="100" height="100" fill="green" '
            . 'style="clip-path: circle(50%)"/>'
            . '</svg>',
        );
        self::assertStringContainsString("\nW", $ops);
        // The outline starts at (cx + r, cy).
        self::assertStringContainsString('130 80 m', $ops);
    }

    public function testStrokeBoxReferenceGrowsByHalfTheStrokeWidth(): void
    {
        // An 80x80 rect at (60, 60) with a 20-wide stroke has a stroke
        // box of (50, 50, 100, 100), so `circle(50%) stroke-box` is a
        // radius-50 circle centred at (100, 100).
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect x="60" y="60" width="80" height="80" fill="blue" '
            . 'stroke="blue" stroke-width="20" clip-path="circle(50%) stroke-box"/>'
            . '</svg>',
        );
        self::assertStringContainsString('150 100 m', $ops);
    }

    public function testEmptyClipPathClipsEverythingAway(): void
    {
        // SVG 2 §14.4.1 — a `<clipPath>` with no geometry clips the whole
        // element away ("clipPath element without content make the clipped
        // element disappear"). `W` with no current path is undefined in
        // PDF and consumers no-op it, which would leave the element fully
        // visible, so the empty region has to be spelled out.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<clipPath id="empty"></clipPath>'
            . '<rect width="100" height="100" fill="green" clip-path="url(#empty)"/>'
            . '</svg>',
        );
        self::assertStringContainsString("0 0 0 0 re\nW\nn", $ops);
    }

    public function testClipPathOfNonAreaEnclosingChildrenAlsoClipsEverythingAway(): void
    {
        // A `<line>` encloses no area, so it contributes nothing to the
        // clip region (SVG 2 §14.4.1) — same outcome as an empty element.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<clipPath id="line"><line x1="0" y1="0" x2="10" y2="10"/></clipPath>'
            . '<rect width="100" height="100" fill="green" clip-path="url(#line)"/>'
            . '</svg>',
        );
        self::assertStringContainsString("0 0 0 0 re\nW\nn", $ops);
    }

    public function testBasicShapeOnAContainerLeavesItUnclipped(): void
    {
        // A container's bounding box is the union of its children's
        // (SVG 2 §7.10.2), but `BoundingBox::compute` reads a child's
        // geometry attributes without a viewport, so a percentage-sized
        // child would union into a bogus box. Until that is fixed the
        // container reports no bbox and the shape resolves to no clip —
        // an unclipped container is closer to right than one clipped to
        // a wrong rectangle.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<g clip-path="circle(50%) stroke-box">'
            . '<rect x="0" y="60" width="80" height="80" fill="blue" '
            . 'stroke="blue" stroke-width="20" transform="translate(60,0)"/>'
            . '</g>'
            . '</svg>',
        );
        self::assertStringNotContainsString("\nW", $ops);
    }

    public function testViewBoxReferenceIsTheViewportAtTheUserSpaceOrigin(): void
    {
        // `view-box` measures against the nearest SVG viewport, anchored
        // at the user-space origin — not against the element's own bbox.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            . '<rect x="20" y="20" width="135" height="135" fill="blue" '
            . 'clip-path="circle(25% at calc(50% - 10px) calc(50% - 10px)) view-box"/>'
            . '</svg>',
        );
        // radius = 25% of 200, centre (90, 90) → start at (140, 90).
        self::assertStringContainsString('140 90 m', $ops);
    }

    public function testUnknownClipPathKeywordLeavesTheElementUnclipped(): void
    {
        // Neither a basic shape nor a `<geometry-box>`: CSS Masking 1
        // §6.1 leaves the element unclipped rather than clipping it to
        // its own box.
        foreach (['not-a-url', 'inherit-ish', 'circle'] as $value) {
            $ops = $this->paint(
                '<svg xmlns="http://www.w3.org/2000/svg">'
                . '<rect width="10" height="10" clip-path="' . $value . '"/>'
                . '</svg>',
            );
            self::assertStringNotContainsString("\nW", $ops, $value);
        }
    }

    public function testDegeneratePolygonShapeLeavesTheElementUnclipped(): void
    {
        // Fewer than three vertices encloses nothing; clipping to it
        // would erase the element.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" clip-path="polygon(0% 0%, 100% 0%)"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString("\nW", $ops);
    }

    public function testBasicShapeOnAnElementWithNoBoundingBoxLeavesItUnclipped(): void
    {
        // A zero-extent rect has no object bounding box to measure
        // against, so the shape can't be resolved and no clip is emitted.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="0" height="0" clip-path="circle(50%)"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString("\nW", $ops);
    }

    public function testBboxModeKeepsThePathObjectFreeOfMatrixOperators(): void
    {
        // ISO 32000-2 §8.2 — a path object admits only path-construction
        // operators between the first construction operator and the
        // painting operator. The bbox / transform inverses used to be
        // emitted BETWEEN the child path and `W`, which makes the stream
        // malformed: consumers abandoned the path and the clip either
        // vanished or never applied. Undoing the matrices after `W n` is
        // equivalent (the region `W` records is already in device space)
        // and keeps the path object well-formed.
        $ops = explode("\n", $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="objectBoundingBox" '
            . 'transform="scale(0.5)">'
            . '<rect width="2" height="2"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect x="10" y="20" width="50" height="30" fill="red" '
            . 'clip-path="url(#clip)"/>'
            . '</svg>',
        ));
        $ops = array_values(array_map('trim', $ops));
        $reIndex = array_search('0 0 2 2 re', $ops, true);
        self::assertIsInt($reIndex, 'clipPath child rect is emitted');
        self::assertSame('W', $ops[$reIndex + 1] ?? null, 'W directly follows the path');
        self::assertSame('n', $ops[$reIndex + 2] ?? null, 'n closes the path object');
        // The inverses land after the path object, not inside it.
        self::assertSame('2 0 0 2 0 0 cm', $ops[$reIndex + 3] ?? null);
    }

    public function testClipPathTransformAndBboxModeCompose(): void
    {
        // bbox cm AND clipPath transform cm both apply. Order matters:
        // bbox first (outer), then transform (inner). The inverses
        // restore CTM in reverse before W fires. Note that SVG 2
        // defaults `clipPathUnits` to `userSpaceOnUse`, so this test
        // sets the bbox mode explicitly.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="objectBoundingBox" '
            . 'transform="scale(0.5)">'
            . '<rect width="2" height="2"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect x="10" y="20" width="50" height="30" fill="red" '
            . 'clip-path="url(#clip)"/>'
            . '</svg>',
        );
        // Bbox cm = 50 0 0 30 10 20.
        self::assertStringContainsString('50 0 0 30 10 20 cm', $ops);
        // Transform cm = 0.5 0 0 0.5 0 0.
        self::assertStringContainsString('0.5 0 0 0.5 0 0 cm', $ops);
        // Inverse transform follows = 2 0 0 2 0 0.
        self::assertStringContainsString('2 0 0 2 0 0 cm', $ops);
        // Inverse bbox cm = 0.02 0 0 0.033… -0.2 -0.666… .
        self::assertMatchesRegularExpression(
            '!0\.02 0 0 0\.0333333333 -0\.2 -0\.6666666667 cm!',
            $ops,
        );
    }

    public function testClipPathWithoutTransformEmitsNoCmAroundChildren(): void
    {
        // Regression guard: in userSpaceOnUse mode with no clipPath
        // transform, there should be no leftover cm operators around
        // the path construction (the 3R+3 behaviour stays intact).
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="clip" clipPathUnits="userSpaceOnUse">'
            . '<rect width="10" height="10"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="20" height="20" fill="red" clip-path="url(#clip)"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString(' cm', $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }
    /**
     * CSS Masking 1 §6.1 / SVG 2 §14.4 — `clip-path` ON a `<clipPath>`
     * element further restricts the clipping region it describes: the
     * effective region is the INTERSECTION of the clipPath's own child
     * geometry and the region its `clip-path` reference describes.
     * Previously the attribute was ignored on the clipPath element, so
     * the referencing element was clipped to the inner geometry alone.
     */
    public function testClipPathOnClipPathElementIntersectsBothRegions(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="outer"><rect x="50" y="50" width="100" height="100"/></clipPath>'
            . '<clipPath id="inner" clip-path="url(#outer)">'
            . '<rect width="200" height="200"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="200" height="200" fill="green" clip-path="url(#inner)"/>'
            . '</svg>',
        );
        $lines = explode("\n", $ops);
        // Both regions are constructed and frozen inside the one q/Q
        // wrap, so the PDF clip is their intersection.
        self::assertSame(2, count(array_keys($lines, 'W', true)));
        $outer = array_search('50 50 100 100 re', $lines, true);
        $inner = array_search('0 0 200 200 re', $lines, true);
        self::assertNotFalse($outer);
        self::assertNotFalse($inner);
        // Referenced region is emitted before this clipPath's own
        // geometry, and both precede the fill.
        self::assertLessThan($inner, $outer);
        self::assertLessThan(array_search('f', $lines, true), $inner);
    }

    /**
     * A `clip-path` cycle on `<clipPath>` elements must terminate rather
     * than recursing forever. A clipPath referencing itself is an
     * invalid reference, so per SVG 2 it is dropped and only the
     * clipPath's own geometry clips.
     */
    public function testClipPathSelfReferenceIsDroppedAndDoesNotRecurse(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="loop" clip-path="url(#loop)">'
            . '<rect width="10" height="10"/>'
            . '</clipPath>'
            . '</defs>'
            . '<rect width="20" height="20" fill="red" clip-path="url(#loop)"/>'
            . '</svg>',
        );
        $lines = explode("\n", $ops);
        self::assertSame(1, count(array_keys($lines, 'W', true)));
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    /**
     * A two-clipPath cycle (A references B, B references A) terminates
     * and still yields the intersection of both geometries.
     */
    public function testClipPathMutualCycleTerminates(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<clipPath id="a" clip-path="url(#b)"><rect width="10" height="10"/></clipPath>'
            . '<clipPath id="b" clip-path="url(#a)"><rect width="20" height="20"/></clipPath>'
            . '</defs>'
            . '<rect width="40" height="40" fill="red" clip-path="url(#a)"/>'
            . '</svg>',
        );
        $lines = explode("\n", $ops);
        self::assertSame(2, count(array_keys($lines, 'W', true)));
        self::assertStringContainsString('0 0 10 10 re', $ops);
        self::assertStringContainsString('0 0 20 20 re', $ops);
    }
}
