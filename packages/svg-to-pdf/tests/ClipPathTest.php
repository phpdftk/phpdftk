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
}
