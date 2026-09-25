<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §5.5 / §5.6.2 — a `<use>` that references a `<symbol>` (or a
 * nested `<svg>`) generates an instance that ESTABLISHES A VIEWPORT,
 * and the `<use>`'s own `width` / `height` override the referenced
 * element's. §8.2 then clips the instance to that viewport unless
 * `overflow` says otherwise.
 *
 * The painter used to paint a `<symbol>` referent's children bare — no
 * viewport, no clip — and only honoured a `<use>` size override when
 * the referent was a nested `<svg>` AND both dimensions were given.
 */
final class SymbolViewportTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="500" height="500">'
            . $body . '</svg>',
        );
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testSymbolInstanceClipsToItsOwnWidthAndHeight(): void
    {
        $ops = $this->paint(
            '<defs><symbol id="s" width="200" height="200">'
            . '<rect width="400" height="400" fill="green"/></symbol></defs>'
            . '<use href="#s"/>',
        );
        self::assertStringContainsString("0 0 200 200 re\nW", $ops);
    }

    public function testUseWidthAndHeightOverrideTheSymbols(): void
    {
        $ops = $this->paint(
            '<defs><symbol id="s" width="200" height="200">'
            . '<rect width="400" height="400" fill="green"/></symbol></defs>'
            . '<use href="#s" width="100" height="100"/>',
        );
        self::assertStringContainsString("0 0 100 100 re\nW", $ops);
        self::assertStringNotContainsString('0 0 200 200 re', $ops);
    }

    public function testASingleUseDimensionOverridesOnlyThatAxis(): void
    {
        // The override used to be all-or-nothing, so `<use width="90">`
        // on a 10x10 symbol fell back to 10x10 instead of 90x10.
        $ops = $this->paint(
            '<defs><symbol id="s" width="10" height="10">'
            . '<rect width="100%" height="100%" fill="green"/></symbol></defs>'
            . '<use href="#s" width="90"/>',
        );
        self::assertStringContainsString("0 0 90 10 re\nW", $ops);
    }

    public function testASymbolWithNoDimensionsFillsTheEnclosingViewport(): void
    {
        // §5.6.2 — the generated instance's width/height default to
        // 100 %, which resolves against the viewport the `<use>` is in.
        $ops = $this->paint(
            '<defs><symbol id="s"><rect width="100%" height="100%" fill="green"/></symbol></defs>'
            . '<use href="#s"/>',
        );
        self::assertStringContainsString("0 0 500 500 re\nW", $ops);
    }

    public function testInlineCssWidthDoesNotSizeTheInstance(): void
    {
        // w3c/svgwg#1059: `width`/`height` as CSS properties do not
        // apply here — the presentation ATTRIBUTE is what sizes the
        // instance.
        $ops = $this->paint(
            '<defs><symbol id="s" width="100" height="100" style="width:200px; height:200px">'
            . '<rect width="400" height="400" fill="green"/></symbol></defs>'
            . '<use href="#s"/>',
        );
        self::assertStringContainsString("0 0 100 100 re\nW", $ops);
        self::assertStringNotContainsString('0 0 200 200 re', $ops);
    }

    public function testSymbolViewBoxStillMapsIntoTheViewport(): void
    {
        $ops = $this->paint(
            '<defs><symbol id="s" width="100" height="100" viewBox="0 0 50 50">'
            . '<rect width="50" height="50" fill="green"/></symbol></defs>'
            . '<use href="#s"/>',
        );
        self::assertStringContainsString('2 0 0 2 0 0 cm', $ops);
    }

    public function testOverflowVisibleSuppressesTheViewportClip(): void
    {
        $ops = $this->paint(
            '<svg width="1" height="1" overflow="visible">'
            . '<rect width="100" height="100" fill="green"/></svg>',
        );
        self::assertStringNotContainsString("0 0 1 1 re\nW", $ops);
    }

    public function testNestedSvgClipsByDefault(): void
    {
        // Guard: `overflow` defaults to `hidden` on a viewport element
        // (SVG 2 §8.2 UA stylesheet). Dropping the clip unconditionally
        // would let every nested `<svg>` bleed.
        $ops = $this->paint(
            '<svg width="1" height="1"><rect width="100" height="100" fill="green"/></svg>',
        );
        self::assertStringContainsString("0 0 1 1 re\nW", $ops);
    }

    public function testOverflowHiddenIsStillHonouredWhenSpelledOut(): void
    {
        $ops = $this->paint(
            '<svg width="1" height="1" overflow="hidden">'
            . '<rect width="100" height="100" fill="green"/></svg>',
        );
        self::assertStringContainsString("0 0 1 1 re\nW", $ops);
    }

    public function testOverflowOnASymbolInstanceIsHonoured(): void
    {
        $ops = $this->paint(
            '<defs><symbol id="s" width="10" height="10" overflow="visible">'
            . '<rect width="100" height="100" fill="green"/></symbol></defs>'
            . '<use href="#s"/>',
        );
        self::assertStringNotContainsString("0 0 10 10 re\nW", $ops);
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }
}
