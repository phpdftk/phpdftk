<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Css;

use Phpdftk\Css\Value\Value;
use Phpdftk\Svg\Css\CssBridge;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\Parser;
use Phpdftk\Svg\SvgDocument;
use PHPUnit\Framework\TestCase;

/**
 * Layered-cascade tests for the 3J bridge. The plan's precedence rule:
 * inline `style=""` > `<style>` rules > presentation attributes >
 * registry initial. Each test isolates one rung of that ladder.
 */
final class CssBridgeTest extends TestCase
{
    private Parser $parser;
    private CssBridge $bridge;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->bridge = new CssBridge();
    }

    /**
     * Helper: turn the cascaded value into its CSS string form so we can
     * compare across the `Color` / `Keyword` divide — both `fill: red`
     * sources end up as `#ff0000` here regardless of which Value subclass
     * `phpdftk/css` chose.
     */
    private static function fillCss(?Value $value): string
    {
        return $value === null ? '' : $value->toCss();
    }

    /**
     * Convenience: parse SVG, pull out the child at `$index`, and narrow
     * it to `Element` for PHPStan. Test SVG strings here are inlined
     * (no whitespace between tags) so the index counts elements directly.
     */
    private function child(SvgDocument $doc, int $index): Element
    {
        $node = $doc->children[$index];
        self::assertInstanceOf(Element::class, $node);
        return $node;
    }

    public function testPresentationAttributeWinsWhenNothingElseSetsIt(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="red"/></svg>',
        );
        $rect = $this->child($doc, 0);
        $values = $this->bridge->computeStyle($rect, $doc);
        self::assertSame('#ff0000', self::fillCss($values->get('fill')));
    }

    public function testStyleBlockBeatsPresentationAttribute(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<style>rect { fill: blue; }</style>'
            . '<rect fill="red"/>'
            . '</svg>',
        );
        $rect = $this->child($doc, 1);
        $values = $this->bridge->computeStyle($rect, $doc);
        self::assertSame('#0000ff', self::fillCss($values->get('fill')));
    }

    public function testInlineStyleBeatsStyleBlock(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<style>rect { fill: blue; }</style>'
            . '<rect fill="red" style="fill: green"/>'
            . '</svg>',
        );
        $rect = $this->child($doc, 1);
        $values = $this->bridge->computeStyle($rect, $doc);
        // CSS Color 3 'green' = #008000 (not #00ff00 — that's 'lime')
        self::assertSame('#008000', self::fillCss($values->get('fill')));
    }

    public function testClassSelectorBeatsTagSelector(): void
    {
        // Confirms the selector matcher routes through MatchableSvgElement
        // and class specificity beats type specificity (CSS Selectors §17).
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<style>rect { fill: blue; } .hi { fill: orange; }</style>'
            . '<rect class="hi"/>'
            . '</svg>',
        );
        $rect = $this->child($doc, 1);
        $values = $this->bridge->computeStyle($rect, $doc);
        self::assertSame('#ffa500', self::fillCss($values->get('fill')));
    }

    public function testIdSelectorBeatsClassSelector(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<style>.hi { fill: orange; } #x { fill: purple; }</style>'
            . '<rect id="x" class="hi"/>'
            . '</svg>',
        );
        $rect = $this->child($doc, 1);
        $values = $this->bridge->computeStyle($rect, $doc);
        self::assertSame('#800080', self::fillCss($values->get('fill')));
    }

    public function testMultipleStyleBlocksAccumulateInDocumentOrder(): void
    {
        // Equal-specificity ties: later source-order wins.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<style>rect { fill: red; }</style>'
            . '<style>rect { fill: green; }</style>'
            . '<rect/>'
            . '</svg>',
        );
        $rect = $this->child($doc, 2);
        $values = $this->bridge->computeStyle($rect, $doc);
        // CSS Color 3 'green' = #008000 (not #00ff00 — that's 'lime')
        self::assertSame('#008000', self::fillCss($values->get('fill')));
    }

    public function testAuthorSheetsCollectedAcrossNestedDefs(): void
    {
        // `<style>` inside `<defs>` is still author CSS that applies
        // document-wide per SVG 2 §6.4.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><style>rect { fill: teal; }</style></defs>'
            . '<rect/>'
            . '</svg>',
        );
        $sheets = $this->bridge->collectAuthorSheets($doc);
        self::assertCount(1, $sheets);
        $rect = $this->child($doc, 1);
        $values = $this->bridge->computeStyle($rect, $doc);
        self::assertSame('#008080', self::fillCss($values->get('fill')));
    }

    public function testPresentationAttributeSheetSkipsUnsetAttributes(): void
    {
        // A bare element with no presentation attrs produces an empty
        // sheet — important so we don't synthesise spurious initial
        // values that would beat real author rules.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>',
        );
        $rect = $this->child($doc, 0);
        $sheet = $this->bridge->presentationAttributeSheet($rect);
        self::assertSame([], $sheet->rules);
    }

    public function testMalformedStyleBlockIsSilentlyTolerated(): void
    {
        // A broken `<style>` shouldn't take down the cascade for the rest
        // of the document.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<style>rect {</style>'
            . '<rect fill="red"/>'
            . '</svg>',
        );
        $rect = $this->child($doc, 1);
        $values = $this->bridge->computeStyle($rect, $doc);
        self::assertSame('#ff0000', self::fillCss($values->get('fill')));
    }
}
