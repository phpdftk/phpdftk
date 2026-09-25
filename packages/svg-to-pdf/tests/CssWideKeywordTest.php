<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * CSS Cascade 5 §7.3 — `inherit` / `initial` / `unset` / `revert` are
 * not values, they are instructions to the cascade. The projector
 * skipped any property the element declared for itself, so
 * `fill="inherit"` reached the painter as the literal string
 * `inherit`, failed to parse as a paint, and fell through to the
 * black default.
 */
final class CssWideKeywordTest extends TestCase
{
    private function paint(string $body): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        (new Translator())->paint($doc, $stream, $page, $writer);
        return implode("\n", $stream->getOperators());
    }

    public function testFillInheritTakesTheParentsFill(): void
    {
        $ops = $this->paint(
            '<g fill="#008000"><rect width="10" height="10" fill="inherit"/></g>',
        );
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
        self::assertStringNotContainsString('0 0 0 rg', $ops);
    }

    public function testFillInheritResolvesThroughCurrentColorAtTheChild(): void
    {
        // WPT painting/currentColor-override-pserver-fill: the parent's
        // `fill` is `currentcolor`, and `currentColor` is a used value
        // resolved against the CHILD's own `color`.
        $ops = $this->paint(
            '<g color="red" fill="currentcolor">'
            . '<rect color="limegreen" fill="inherit" width="10" height="10"/></g>',
        );
        self::assertStringContainsString('0.1960784314 0.8039215686 0.1960784314 rg', $ops);
    }

    public function testInitialResetsToTheInitialValue(): void
    {
        $ops = $this->paint(
            '<g fill="#008000"><rect width="10" height="10" fill="initial"/></g>',
        );
        self::assertStringContainsString('0 0 0 rg', $ops);
        self::assertStringNotContainsString('0 0.5019607843 0 rg', $ops);
    }

    public function testAnOrdinaryAttributeStillBeatsTheCascade(): void
    {
        // Guard: the skip rule is only lifted for the CSS-wide
        // keywords. A real value on the element must keep winning.
        $ops = $this->paint(
            '<style>rect { fill: red }</style>'
            . '<rect width="10" height="10" fill="#008000"/>',
        );
        self::assertStringContainsString('0 0.5019607843 0 rg', $ops);
        self::assertStringNotContainsString('1 0 0 rg', $ops);
    }
}
