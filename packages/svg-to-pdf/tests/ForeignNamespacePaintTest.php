<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §5.7 — a foreign-namespace element inside an SVG document is
 * private data: neither it nor its DESCENDANTS render. The painter's
 * generic "unknown element → recurse into children" fallback meant an
 * `<svg>` wrapped in an XHTML `<div>` painted anyway.
 */
final class ForeignNamespacePaintTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:h="http://www.w3.org/1999/xhtml"'
            . ' width="400" height="400">' . $body . '</svg>',
        );
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testContentInsideAForeignElementIsNotPainted(): void
    {
        $ops = $this->paint(
            '<rect width="100" height="100" fill="green"/>'
            . '<h:div style="display: contents"><svg width="300" height="300">'
            . '<rect x="5" y="5" width="100" height="100" fill="red"/></svg></h:div>',
        );
        self::assertStringContainsString('0 0 100 100 re', $ops);
        self::assertStringNotContainsString('5 5 100 100 re', $ops);
        self::assertStringNotContainsString('1 0 0 rg', $ops);
    }

    public function testSvgSiblingsOfAForeignElementStillPaint(): void
    {
        // Guard: skipping the subtree must not swallow what follows it.
        $ops = $this->paint(
            '<h:div/><rect width="10" height="10" fill="green"/>',
        );
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }
}
