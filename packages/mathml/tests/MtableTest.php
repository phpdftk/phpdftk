<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mtable;
use Phpdftk\Mathml\Mtd;
use Phpdftk\Mathml\Mtr;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser-layer coverage for the table triumvirate:
 * `<mtable>`, `<mtr>`, `<mtd>`.
 *
 * The painter scans `Mtable`'s typed child list for `Mtr` and each
 * `Mtr` for `Mtd` — so the parser MUST produce typed instances for
 * all three.
 */
final class MtableTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMtableAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mtr><mtd><mn>1</mn></mtd></mtr></mtable>'
                . '</math>',
        );
        $table = $this->firstElement($doc->children);
        self::assertInstanceOf(Mtable::class, $table);
    }

    public function testParsesMtrAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mtr><mtd><mn>1</mn></mtd></mtr></mtable>'
                . '</math>',
        );
        $table = $this->firstElement($doc->children);
        $row = $this->firstChildElement($table);
        self::assertInstanceOf(Mtr::class, $row);
    }

    public function testParsesMtdAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable><mtr><mtd><mn>1</mn></mtd></mtr></mtable>'
                . '</math>',
        );
        $table = $this->firstElement($doc->children);
        $row = $this->firstChildElement($table);
        $cell = $this->firstChildElement($row);
        self::assertInstanceOf(Mtd::class, $cell);
        // Cell content reaches the typed leaf — tokens parsed as
        // before.
        $token = $this->firstChildElement($cell);
        self::assertInstanceOf(Mn::class, $token);
    }

    public function testCanonical2x2MatrixStructureRoundTrips(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '<mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        $table = $this->firstElement($doc->children);
        self::assertInstanceOf(Mtable::class, $table);
        $rows = $this->childElements($table);
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertInstanceOf(Mtr::class, $row);
            $cells = $this->childElements($row);
            self::assertCount(2, $cells);
            foreach ($cells as $cell) {
                self::assertInstanceOf(Mtd::class, $cell);
            }
        }
    }

    public function testEmptyMtableParses(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable/>'
                . '</math>',
        );
        $table = $this->firstElement($doc->children);
        self::assertInstanceOf(Mtable::class, $table);
        self::assertSame([], $this->childElements($table));
    }

    public function testLooseMtrOutsideMtableStillParses(): void
    {
        // Author error, but well-formed XML — the parser doesn't
        // validate placement.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtr><mtd><mn>1</mn></mtd></mtr>'
                . '</math>',
        );
        $row = $this->firstElement($doc->children);
        self::assertInstanceOf(Mtr::class, $row);
    }

    public function testRaggedRowsPreserveCellCounts(): void
    {
        // Row 1 has 2 cells, row 2 has 1 cell. Painter handles ragged
        // input by treating missing cells as empty trailing columns.
        // Parser just preserves the structure.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mtable>'
                . '<mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>'
                . '<mtr><mtd><mn>3</mn></mtd></mtr>'
                . '</mtable>'
                . '</math>',
        );
        $table = $this->firstElement($doc->children);
        $rows = $this->childElements($table);
        self::assertCount(2, $this->childElements($rows[0]));
        self::assertCount(1, $this->childElements($rows[1]));
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

    private function firstChildElement(Element $parent): Element
    {
        return $this->firstElement($parent->children);
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
