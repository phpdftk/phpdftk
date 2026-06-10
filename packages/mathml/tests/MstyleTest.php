<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mstyle;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser tests for the typed Mstyle element. The painter consults
 * displaystyle() and scriptlevel() during walk; these tests pin the
 * accessor behaviour for the absolute / relative / invalid input
 * shapes.
 */
final class MstyleTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMstyleAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mstyle><mi>x</mi></mstyle>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mstyle::class, $el);
    }

    public function testDisplaystyleAttribute(): void
    {
        self::assertTrue(
            $this->parseMstyle('displaystyle="true"')->displaystyle(),
        );
        self::assertFalse(
            $this->parseMstyle('displaystyle="false"')->displaystyle(),
        );
        self::assertNull($this->parseMstyle('')->displaystyle());
        self::assertNull(
            $this->parseMstyle('displaystyle="invalid"')->displaystyle(),
        );
    }

    public function testScriptlevelAbsolute(): void
    {
        self::assertSame(
            ['absolute', 2],
            $this->parseMstyle('scriptlevel="2"')->scriptlevel(),
        );
        self::assertSame(
            ['absolute', 0],
            $this->parseMstyle('scriptlevel="0"')->scriptlevel(),
        );
    }

    public function testScriptlevelRelativePositive(): void
    {
        self::assertSame(
            ['relative', 1],
            $this->parseMstyle('scriptlevel="+1"')->scriptlevel(),
        );
        self::assertSame(
            ['relative', 3],
            $this->parseMstyle('scriptlevel="+3"')->scriptlevel(),
        );
    }

    public function testScriptlevelRelativeNegative(): void
    {
        self::assertSame(
            ['relative', -1],
            $this->parseMstyle('scriptlevel="-1"')->scriptlevel(),
        );
    }

    public function testScriptlevelInvalidReturnsNull(): void
    {
        self::assertNull($this->parseMstyle('scriptlevel="abc"')->scriptlevel());
        self::assertNull($this->parseMstyle('scriptlevel=""')->scriptlevel());
        self::assertNull($this->parseMstyle('')->scriptlevel());
    }

    private function parseMstyle(string $attrs): Mstyle
    {
        $attr = $attrs === '' ? '' : ' ' . $attrs;
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mstyle' . $attr . '><mi>x</mi></mstyle>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mstyle::class, $el);
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
