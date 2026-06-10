<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Menclose;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser-layer coverage for `<menclose>`, including the
 * `notation` attribute parsing that the painter dispatches on.
 */
final class MencloseTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMencloseAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<menclose notation="box"><mi>x</mi></menclose>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Menclose::class, $el);
    }

    public function testSingleNotationParsed(): void
    {
        $el = $this->parseMenclose('box');
        self::assertSame(['box'], $el->notations());
    }

    public function testMultipleNotationsParsedInOrder(): void
    {
        $el = $this->parseMenclose('box horizontalstrike');
        self::assertSame(['box', 'horizontalstrike'], $el->notations());
    }

    public function testNotationsDedupedPreservingFirstOccurrence(): void
    {
        $el = $this->parseMenclose('box box updiagonalstrike box');
        self::assertSame(['box', 'updiagonalstrike'], $el->notations());
    }

    public function testNotationsLowercased(): void
    {
        $el = $this->parseMenclose('BOX UpDiagonalStrike');
        self::assertSame(['box', 'updiagonalstrike'], $el->notations());
    }

    public function testNotationsExtraWhitespaceCollapses(): void
    {
        $el = $this->parseMenclose("  box\t\nupdiagonalstrike   ");
        self::assertSame(['box', 'updiagonalstrike'], $el->notations());
    }

    public function testAbsentNotationDefaultsToLongdiv(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<menclose><mi>x</mi></menclose>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Menclose::class, $el);
        self::assertSame(['longdiv'], $el->notations());
    }

    public function testEmptyNotationFallsBackToDefault(): void
    {
        $el = $this->parseMenclose('   ');
        self::assertSame(['longdiv'], $el->notations());
    }

    private function parseMenclose(string $notationAttr): Menclose
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<menclose notation="' . htmlspecialchars($notationAttr, ENT_QUOTES) . '">'
                . '<mi>x</mi>'
                . '</menclose>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Menclose::class, $el);
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
}
