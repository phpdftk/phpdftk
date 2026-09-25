<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §9.3 — `d` is a CSS property as well as a presentation
 * attribute, with the grammar `none | path(<string>)`.
 *
 * Unlike almost every other SVG property, the CSS declaration has to
 * BEAT the attribute of the same name: a presentation attribute is an
 * author-origin declaration of specificity 0 (§6.7), so any rule that
 * names the element out-ranks it. `Path::d()` read the attribute
 * directly and never looked at the cascade, so `style="d: none"` still
 * painted the attribute's shape.
 */
final class CssDPropertyTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testTheAttributeStillWorksOnItsOwn(): void
    {
        $ops = $this->paint('<path d="M 0 0 H 100" stroke="black"/>');
        self::assertStringContainsString('0 0 m', $ops);
        self::assertStringContainsString('100 0 l', $ops);
    }

    public function testInlineStyleDNoneSuppressesTheAttributePath(): void
    {
        $ops = $this->paint(
            '<path d="M 0 0 H 100 V 100 H 0 Z" fill="red" style="d: none"/>',
        );
        self::assertStringNotContainsString('100 0 l', $ops);
        self::assertStringNotContainsString('1 0 0 rg', $ops);
    }

    public function testInlineStylePathFunctionOverridesTheAttribute(): void
    {
        $ops = $this->paint(
            '<path d="M 10 90 h 70" style="d: path(\'M 20 80 h 70\')" stroke="blue"/>',
        );
        self::assertStringContainsString('20 80 m', $ops);
        self::assertStringNotContainsString('10 90 m', $ops);
    }

    public function testStylesheetRuleOverridesTheAttribute(): void
    {
        $ops = $this->paint(
            '<style>#top { d: path(\'M 20 10 h 70\'); }</style>'
            . '<path id="top" d="M 10 20 h 70" stroke="blue"/>',
        );
        self::assertStringContainsString('20 10 m', $ops);
        self::assertStringNotContainsString('10 20 m', $ops);
    }

    public function testDoubleQuotedPathFunctionParses(): void
    {
        $ops = $this->paint(
            '<path style=\'d: path("M 50,50 l 50,0")\' stroke="black"/>',
        );
        self::assertStringContainsString('50 50 m', $ops);
    }

    public function testAnUnparseableDPropertyFallsBackToTheAttribute(): void
    {
        // Guard: an invalid declaration is ignored, and ignoring it
        // means the attribute is still in force — NOT that the element
        // loses its geometry.
        $ops = $this->paint(
            '<path d="M 10 20 h 70" style="d: notafunction(1)" stroke="blue"/>',
        );
        self::assertStringContainsString('10 20 m', $ops);
    }

    public function testAnInlineStyleBeatsAStylesheetRule(): void
    {
        // Guard on projection order: the projector appends the cascaded
        // value to `style`, and the reader must still prefer the
        // author's own inline declaration.
        $ops = $this->paint(
            '<style>path { d: path(\'M 1 1 h 1\'); }</style>'
            . '<path style="d: path(\'M 20 80 h 70\')" stroke="blue"/>',
        );
        self::assertStringContainsString('20 80 m', $ops);
        self::assertStringNotContainsString('1 1 m', $ops);
    }
}
