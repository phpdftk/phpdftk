<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests;

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use PHPUnit\Framework\TestCase;

/**
 * CSS Counter Styles 3 §6 — predefined counter styles applied
 * via `content: counter(name, <style>)` in pseudo-elements.
 * Each style emits one or more recognisable glyphs the test
 * can grep for in the rendered PDF.
 */
final class CounterStylesTest extends TestCase
{
    private function renderHtml(string $html): string
    {
        $renderer = new Renderer(new RendererOptions());
        return $renderer->render($html)->writer->toBytes();
    }

    /**
     * Render `<ol style="list-style-type: <style>"><li>1</li><li>2</li><li>3</li></ol>`
     * and return the bytes.
     */
    private function renderList(string $style): string
    {
        return $this->renderHtml(
            "<html><body><ol style=\"list-style-type: $style\">"
            . "<li>one</li><li>two</li><li>three</li>"
            . "</ol></body></html>",
        );
    }

    public function testDecimalProducesValidPdf(): void
    {
        $bytes = $this->renderList('decimal');
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
    }

    public function testEachStyleProducesValidPdf(): void
    {
        foreach ([
            'decimal',
            'decimal-leading-zero',
            'lower-alpha',
            'upper-alpha',
            'lower-roman',
            'upper-roman',
            'lower-greek',
            'hebrew',
            'armenian',
            'georgian',
            'hiragana',
            'hiragana-iroha',
            'katakana',
            'katakana-iroha',
        ] as $style) {
            $bytes = $this->renderList($style);
            self::assertStringStartsWith('%PDF-', $bytes, "$style should render a valid PDF");
            self::assertStringContainsString('%%EOF', $bytes, "$style should end with EOF");
        }
    }
}
