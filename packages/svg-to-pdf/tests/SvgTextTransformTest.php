<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * CSS Text 3 §2.1 with SVG 2 §11 — SVG delegates text styling to CSS
 * wholesale, so `text-transform` applies to `<text>` exactly as it
 * does to an HTML box. The SVG painter never read it.
 */
final class SvgTextTransformTest extends TestCase
{
    private function paint(string $body): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100">'
            . $body . '</svg>',
        );
        (new Translator())->paint($doc, $stream, $page, $writer);
        return implode("\n", $stream->getOperators());
    }

    public function testUppercaseIsApplied(): void
    {
        $ops = $this->paint(
            '<text y="50" style="text-transform: uppercase">Hello, World!</text>',
        );
        self::assertStringContainsString('HELLO, WORLD!', $ops);
        self::assertStringNotContainsString('Hello, World!', $ops);
    }

    public function testCapitalizeIsApplied(): void
    {
        $ops = $this->paint(
            '<text y="50" style="text-transform: capitalize">hello, world!</text>',
        );
        self::assertStringContainsString('Hello, World!', $ops);
    }

    public function testThePresentationAttributeFormWorksToo(): void
    {
        $ops = $this->paint('<text y="50" text-transform="lowercase">HELLO</text>');
        self::assertStringContainsString('hello', $ops);
    }

    public function testNoDeclarationLeavesTheTextAlone(): void
    {
        // Guard: the accessor must return null rather than some
        // default keyword, or every run would be re-cased.
        $ops = $this->paint('<text y="50">Hello, World!</text>');
        self::assertStringContainsString('Hello, World!', $ops);
    }
}
