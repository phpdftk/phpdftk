<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\WptHarness\Rasteriser;
use PHPUnit\Framework\TestCase;

/**
 * The rasteriser is the only place in the pipeline that turns a
 * continuous coordinate into a device pixel — the renderer emits
 * full-precision reals (`ContentStream::num()` formats at `%.10f`)
 * and snaps nothing. Every rounding question therefore has exactly
 * one answer, it is given here, and these tests pin it.
 */
final class RasteriserTest extends TestCase
{
    protected function setUp(): void
    {
        if (!(new Rasteriser())->isAvailable()) {
            self::markTestSkipped('Ghostscript (`gs`) not installed');
        }
        exec('convert -version >/dev/null 2>&1', $_, $status);
        if ($status !== 0) {
            self::markTestSkipped('ImageMagick `convert` not installed');
        }
    }

    /**
     * A path fill and a shading handed the SAME coordinate must land
     * on the same device column.
     *
     * Ghostscript's default half-pixel fill adjustment makes
     * `re ... f` paint every pixel the fill touches, while shadings,
     * image XObjects and glyphs resolve at the pixel centre. An edge
     * in the upper half of a pixel then belongs to different columns
     * depending on which paint path drew it: a gradient stop sits one
     * column right of the rectangle drawn to the identical edge. That
     * is a whole-colour seam, and it fails an exact-match reftest
     * whose two sides reach the same picture by different paint paths
     * — which is most of them, a gradient against the solid blocks of
     * its reference being the standard shape of a WPT gradient test.
     */
    public function testPathFillAndShadingRoundAFractionalEdgeTheSameWay(): void
    {
        // 100.75 sits three quarters of the way into device column
        // 100 at one pixel per point, where the two rules disagree.
        $png = $this->rasterise(new Rasteriser(72), self::edgeDocument('100.75px'));

        self::assertSame(
            self::firstBlueColumn($png, 10),
            self::firstBlueColumn($png, 30),
            'the gradient stop and the rectangle edge chose different device columns',
        );
    }

    /**
     * The negative half of the same rule: an edge in the LOWER half
     * of a pixel keeps that pixel on both paths, so agreeing must not
     * mean shifting every edge one column right.
     */
    public function testAnEdgeInTheLowerHalfOfAPixelKeepsThatPixelOnBothPaths(): void
    {
        $png = $this->rasterise(new Rasteriser(72), self::edgeDocument('100.25px'));

        self::assertSame(100, self::firstBlueColumn($png, 10));
        self::assertSame(100, self::firstBlueColumn($png, 30));
    }

    /**
     * An integer CSS edge is already unambiguous — it is a whole
     * device pixel at one pixel per point — and must stay put.
     */
    public function testAnIntegerEdgeIsUnmovedOnBothPaths(): void
    {
        $png = $this->rasterise(new Rasteriser(72), self::edgeDocument('100px'));

        self::assertSame(100, self::firstBlueColumn($png, 10));
        self::assertSame(100, self::firstBlueColumn($png, 30));
    }

    /**
     * Zeroing the fill adjustment costs dropout protection for fills
     * thinner than a device pixel, so pin where that floor is: at one
     * pixel per point a 1 CSS px rule is exactly one device pixel and
     * always covers exactly one pixel centre, whatever its phase.
     */
    public function testAOneCssPixelRuleSurvivesAtEveryFractionalOffset(): void
    {
        $bands = [];
        foreach (['0px', '0.25px', '0.5px', '0.75px'] as $offset) {
            $bands[] = '<div><i style="left:calc(20px + ' . $offset . ')"></i></div>';
        }
        $png = $this->rasterise(
            new Rasteriser(72),
            '<!doctype html><style>body{margin:0}'
            . 'div{width:60px;height:10px;position:relative;background:#fff}'
            . 'i{position:absolute;top:0;width:1px;height:10px;background:#00f;display:block}'
            . '</style>' . implode('', $bands),
        );

        foreach ([5, 15, 25, 35] as $index => $row) {
            self::assertNotSame(
                -1,
                self::firstBlueColumn($png, $row),
                "the 1px rule at sub-pixel offset #$index vanished",
            );
        }
    }

    /**
     * Row 10 falls in a band painted by a gradient with a hard stop
     * at `$edge`; row 30 falls in a band painted by a rectangle whose
     * left edge is at `$edge`. Both bands are red left of it and blue
     * from it rightwards, so the leftmost blue column is the device
     * column each paint path assigned to the identical coordinate.
     */
    private static function edgeDocument(string $edge): string
    {
        return '<!doctype html><style>body{margin:0}'
            . 'div{width:200px;height:20px;position:relative}'
            . "#shading{background:linear-gradient(to right,#f00 0,#f00 $edge,#00f $edge,#00f 200px)}"
            . '#fill{background:#f00}'
            . "#fill i{position:absolute;left:$edge;top:0;width:99px;height:20px;"
            . 'background:#00f;display:block}'
            . '</style><div id="shading"></div><div id="fill"><i></i></div>';
    }

    private function rasterise(Rasteriser $rasteriser, string $html): string
    {
        $pdf = (new Renderer(new RendererOptions()))->render($html)->writer->toBytes();
        $pdfPath = tempnam(sys_get_temp_dir(), 'rasteriser_test_') . '.pdf';
        file_put_contents($pdfPath, $pdf);
        try {
            return $rasteriser->rasterise($pdfPath);
        } finally {
            @unlink($pdfPath);
        }
    }

    /** Leftmost pure-blue device column on row `$row`, or -1. */
    private static function firstBlueColumn(string $png, int $row): int
    {
        $out = [];
        exec(
            sprintf(
                'convert %s -crop x1+0+%d +repage txt: 2>/dev/null',
                escapeshellarg($png),
                $row,
            ),
            $out,
        );
        foreach ($out as $line) {
            if (stripos($line, '#0000FF') !== false
                && preg_match('~^(\d+),~', $line, $m) === 1) {
                return (int) $m[1];
            }
        }
        return -1;
    }
}
