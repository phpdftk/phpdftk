<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\HarnessRunner;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §8.2 — the viewport a STANDALONE outermost `<svg>` paints into.
 *
 * `width` / `height` on the root are `auto`, which resolves to `100%`;
 * for a standalone document the containing window is the viewport, so a
 * root declaring no dimensions fills the page rather than collapsing to
 * the renderer's finite-size fallback. A root that DOES declare fixed
 * dimensions gets exactly those — a `viewBox` is a coordinate system
 * mapped into the viewport, never the viewport itself.
 *
 * This matters for scoring, not just rendering: a sizeless reference
 * that draws almost nothing matches an equally blank test, so the
 * reftest passes while neither side shows anything.
 */
final class SizelessSvgViewportTest extends TestCase
{
    private function render(string $svg): string
    {
        $path = tempnam(sys_get_temp_dir(), 'szsvg') . '.svg';
        file_put_contents($path, $svg);
        try {
            $runner = (new \ReflectionClass(HarnessRunner::class))->newInstanceWithoutConstructor();
            $m = new \ReflectionMethod(HarnessRunner::class, 'renderSvgToPdf');
            $m->setAccessible(true);
            return $m->invoke($runner, $path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function viewport(string $svg): array
    {
        $doc = (new \Phpdftk\Svg\Parser())->parse($svg);
        $m = new \ReflectionMethod(HarnessRunner::class, 'svgRootViewport');
        $m->setAccessible(true);
        /** @var array{0: float, 1: float} $result */
        $result = $m->invoke(null, $doc, 612.0, 792.0);
        return $result;
    }

    public function testASizelessRootTakesThePageAsItsViewport(): void
    {
        self::assertSame([612.0, 792.0], $this->viewport(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="green"/></svg>',
        ));
    }

    public function testPercentageDimensionsAreAutoAndTakeThePage(): void
    {
        self::assertSame([612.0, 792.0], $this->viewport(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="10" height="10"/></svg>',
        ));
    }

    public function testAFixedWidthAndHeightPairIsTheViewport(): void
    {
        self::assertSame([200.0, 150.0], $this->viewport(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150"><rect width="10" height="10"/></svg>',
        ));
    }

    public function testAViewBoxDoesNotOverrideDeclaredDimensions(): void
    {
        // The regression this guards: the viewBox extent was being used
        // as the destination rect, so a 200x200 document declaring
        // `viewBox="0 0 3 3"` rendered 3pt wide.
        self::assertSame([200.0, 200.0], $this->viewport(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 3" width="200" height="200">'
            . '<rect width="1" height="1"/></svg>',
        ));
    }

    public function testAViewBoxAloneKeepsItsExtentAsTheNaturalSize(): void
    {
        self::assertSame([3.0, 3.0], $this->viewport(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 3"><rect width="1" height="1"/></svg>',
        ));
    }

    public function testASingleFixedAxisResolvesTheOtherThroughTheViewBoxRatio(): void
    {
        self::assertSame([200.0, 100.0], $this->viewport(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 2" width="200">'
            . '<rect width="1" height="1"/></svg>',
        ));
    }

    public function testAbsoluteUnitsOnTheRootConvertToCssPixels(): void
    {
        self::assertSame([96.0, 144.0], $this->viewport(
            '<svg xmlns="http://www.w3.org/2000/svg" width="1in" height="1.5in"><rect width="1" height="1"/></svg>',
        ));
    }

    public function testASizelessRootPaintsItsContentRatherThanCollapsing(): void
    {
        // A 100x100 rect in a sizeless root must actually reach the page.
        // Before the viewport fix the root collapsed to a unit square and
        // the rect emitted at a scale that rendered essentially nothing.
        $pdf = $this->render(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="green"/></svg>',
        );
        self::assertStringStartsWith('%PDF-', $pdf);
        $ops = self::inflateContentStreams($pdf);
        // A green fill (0 0.5 0 rg) over a 100-unit square must be emitted.
        self::assertMatchesRegularExpression('/\b0\s+0\.50\d*\s+0\s+rg/', $ops);
        self::assertMatchesRegularExpression('/\b100\s+100\s+re\b/', $ops);
        // The y-flip must use the PAGE height: a sizeless root takes the
        // page as its viewport, not the unit-square fallback.
        self::assertMatchesRegularExpression('/1 0 0 -1 0 792(\.0+)? cm/', $ops);
    }

    public function testDeclaredDimensionsScaleTheViewBoxAndAnchorAtThePageTop(): void
    {
        // `viewBox="0 0 100 100"` inside a 200x200 viewport means one
        // user unit is 2px, and the document's top-left sits at the TOP
        // of the page — not flush with the bottom margin.
        $pdf = $this->render(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="200" height="200">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        $ops = self::inflateContentStreams($pdf);
        self::assertMatchesRegularExpression('/\b2 0 0 -2 0 792(\.0+)? cm/', $ops);
    }

    /** Concatenate every inflated content stream in the PDF. */
    private static function inflateContentStreams(string $pdf): string
    {
        $out = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m) === false) {
            return $out;
        }
        foreach ($m[1] as $raw) {
            $inflated = @gzuncompress($raw);
            $out .= $inflated === false ? $raw : $inflated;
        }
        return $out;
    }
}
