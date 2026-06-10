<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mtable;
use Phpdftk\Mathml\Mtd;
use Phpdftk\Mathml\Mtr;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser-layer coverage for the layout attributes on `<mtable>`,
 * `<mtr>`, `<mtd>` that drive cell positioning:
 * `columnalign`, `rowalign`, `columnspacing`, `rowspacing`.
 *
 * Each accessor is exercised across happy-path values, unknown
 * tokens (which must fall back rather than crash), case folding,
 * mixed units, and the "absent attribute returns empty list" rule
 * that lets the painter apply its own default.
 */
final class MtableAttributesTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testMtableColumnAlignPositionalList(): void
    {
        $table = $this->mtable('columnalign="left center right"');
        self::assertSame(['left', 'center', 'right'], $table->columnAlign());
    }

    public function testMtableColumnAlignAbsentReturnsEmptyList(): void
    {
        $table = $this->mtable('');
        self::assertSame([], $table->columnAlign());
    }

    public function testMtableColumnAlignFoldsCaseAndCoercesUnknown(): void
    {
        // 'middle' isn't a valid value - it folds to the fallback
        // 'center' rather than crash.
        $table = $this->mtable('columnalign="LEFT middle right"');
        self::assertSame(['left', 'center', 'right'], $table->columnAlign());
    }

    public function testMtableRowAlignValues(): void
    {
        $table = $this->mtable('rowalign="top baseline bottom"');
        self::assertSame(['top', 'baseline', 'bottom'], $table->rowAlign());
    }

    public function testMtableColumnSpacingDefaultsForUnknownUnits(): void
    {
        // 'cm' is a unit we don't translate - falls back to default.
        $table = $this->mtable('columnspacing="1em 2ex 5cm"');
        $spacing = $table->columnSpacingEm();
        self::assertCount(3, $spacing);
        self::assertSame(1.0, $spacing[0]);
        self::assertSame(1.0, $spacing[1]);  // 2ex = 1em
        self::assertSame(0.8, $spacing[2]);  // unknown unit fallback
    }

    public function testMtableRowSpacingHandlesPxAndPt(): void
    {
        $table = $this->mtable('rowspacing="16px 12pt"');
        $spacing = $table->rowSpacingEm();
        self::assertSame(1.0, $spacing[0]);  // 16px / 16
        self::assertSame(1.0, $spacing[1]);  // 12pt / 12
    }

    public function testMtrPerRowColumnAlignOverride(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable columnalign="center">'
                . '<mtr columnalign="left right"><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '<mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr>'
                . '</mtable></math>',
        );
        $rows = $this->childElements($this->firstElement($doc->children));
        self::assertInstanceOf(Mtr::class, $rows[0]);
        self::assertSame(['left', 'right'], $rows[0]->columnAlign());
        self::assertSame([], $rows[1]->columnAlign());
    }

    public function testMtdPerCellColumnAlignOverride(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mtr>'
                . '<mtd columnalign="right"><mn>1</mn></mtd>'
                . '<mtd><mn>2</mn></mtd>'
                . '</mtr></mtable></math>',
        );
        $cells = $this->childElements(
            $this->childElements($this->firstElement($doc->children))[0],
        );
        self::assertInstanceOf(Mtd::class, $cells[0]);
        self::assertSame('right', $cells[0]->columnAlign());
        self::assertNull($cells[1]->columnAlign());
    }

    public function testMtdRowAlignAbsentReturnsNull(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mtr><mtd><mn>1</mn></mtd></mtr></mtable></math>',
        );
        $cell = $this->childElements(
            $this->childElements($this->firstElement($doc->children))[0],
        )[0];
        self::assertInstanceOf(Mtd::class, $cell);
        self::assertNull($cell->rowAlign());
    }

    private function mtable(string $attrs): Mtable
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mtable' . ($attrs === '' ? '' : ' ' . $attrs) . '>'
            . '<mtr><mtd><mn>1</mn></mtd></mtr>'
            . '</mtable></math>';
        $doc = $this->parser->parse($xml);
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mtable::class, $el);
        return $el;
    }

    /**
     * @param list<\Phpdftk\Mathml\Node> $nodes
     */
    private function firstElement(array $nodes): Element
    {
        foreach ($nodes as $n) {
            if ($n instanceof Element) {
                return $n;
            }
        }
        self::fail('No element child found.');
    }

    /** @return list<Element> */
    private function childElements(Element $parent): array
    {
        return array_values(array_filter(
            $parent->children,
            static fn($c) => $c instanceof Element,
        ));
    }
}
