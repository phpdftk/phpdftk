<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\HarnessRunner;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §8.2 — `width` / `height` on the outermost `<svg>` are `auto`,
 * which resolves to `100%`. For a STANDALONE document the viewport is
 * the window, so a root declaring no dimensions fills the page rather
 * than collapsing to the renderer's finite-size fallback.
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

    private function hasIntrinsicSize(string $svg): bool
    {
        $doc = (new \Phpdftk\Svg\Parser())->parse($svg);
        $m = new \ReflectionMethod(HarnessRunner::class, 'svgRootHasIntrinsicSize');
        $m->setAccessible(true);
        return (bool) $m->invoke(null, $doc);
    }

    public function testASizelessRootIsNotTreatedAsHavingAnIntrinsicSize(): void
    {
        self::assertFalse($this->hasIntrinsicSize(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="green"/></svg>',
        ));
    }

    public function testAFixedWidthAndHeightPairIsAnIntrinsicSize(): void
    {
        self::assertTrue($this->hasIntrinsicSize(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="10" height="10"/></svg>',
        ));
    }

    public function testAViewBoxAloneSuppliesAnIntrinsicRatio(): void
    {
        self::assertTrue($this->hasIntrinsicSize(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 3"><rect width="1" height="1"/></svg>',
        ));
    }

    public function testPercentageDimensionsAreAutoAndNeedAViewport(): void
    {
        self::assertFalse($this->hasIntrinsicSize(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="10" height="10"/></svg>',
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
