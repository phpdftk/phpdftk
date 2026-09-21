<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * CSS Values 4 §6.1 — `vw` / `vh` / `vmin` / `vmax` in SVG geometry
 * attributes.
 *
 * They resolve against the INITIAL containing block, which for an SVG
 * document is its own outermost viewport — unlike `%`, which resolves
 * against the nearest `<svg>` viewport. Before this they fell through
 * to the unit-stripped number, so `width="50vw"` painted 50 user units.
 */
final class ViewportUnitLengthTest extends TestCase
{
    private function paintOps(string $svg): string
    {
        $doc = (new SvgParser())->parse($svg);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testAnUnknownUnitStillFallsBackToTheBareNumber(): void
    {
        // `vb` is a writing-mode-relative unit we do not resolve; it
        // must not be mistaken for `vh` / `vw`.
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="50vb" height="10" fill="green"/></svg>',
        );
        self::assertStringContainsString('0 0 50 10 re', $ops);
    }

    public function testAPercentageStillResolvesAgainstTheNearestViewportNotTheRoot(): void
    {
        // The nested viewport is 40x40; `50%` there is 20, while `50vw`
        // would be 100. Getting these confused is the failure mode this
        // guards.
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<svg x="0" y="0" width="40" height="40">'
            . '<rect width="50%" height="50%" fill="green"/>'
            . '</svg></svg>',
        );
        self::assertStringContainsString('0 0 20 20 re', $ops);
    }

    public function testViewportUnitsIgnoreANestedSvgViewport(): void
    {
        // Same nested 40x40 box, but `vw` / `vh` must still resolve
        // against the OUTERMOST 200x100 viewport.
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<svg x="0" y="0" width="40" height="40">'
            . '<rect width="10vw" height="10vh" fill="green"/>'
            . '</svg></svg>',
        );
        self::assertStringContainsString('0 0 20 10 re', $ops);
    }

    public function testVwAndVhResolveAgainstTheOutermostViewport(): void
    {
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="50vw" height="50vh" fill="green"/></svg>',
        );
        self::assertStringContainsString('0 0 100 50 re', $ops);
    }

    public function testVminAndVmaxUseTheShorterAndLongerAxis(): void
    {
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">'
            . '<rect width="10vmax" height="10vmin" fill="green"/></svg>',
        );
        self::assertStringContainsString('0 0 20 10 re', $ops);
    }

    public function testAnEmbeddedSvgResolvesViewportUnitsAgainstItsImageBox(): void
    {
        // SVG 2 §8.6 — the referenced resource is its own document, so
        // its initial containing block is the `<image>` viewport it was
        // handed, not the host document's viewport.
        $uri = 'data:image/svg+xml,' . rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="50vw" height="50vh" fill="green"/></svg>',
        );
        $ops = $this->paintOps(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">'
            . sprintf('<image width="200" height="200" href="%s"/>', $uri)
            . '</svg>',
        );
        // 50vw of the 200-wide image box, not of the 1000-wide host.
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }
}
