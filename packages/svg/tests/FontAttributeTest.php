<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Text\TextElement;
use PHPUnit\Framework\TestCase;

/**
 * CSS Fonts 4 presentation attributes. The accessors live on `Element`
 * (any element can carry them, fonts inherit), so we use `<text>` as a
 * representative carrier.
 */
final class FontAttributeTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    private function textWith(string $attrs): TextElement
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text ' . $attrs . '>X</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        return $text;
    }

    public function testFontFamilyParsesCommaSeparatedList(): void
    {
        self::assertSame(
            ['Helvetica', 'Arial', 'sans-serif'],
            $this->textWith('font-family="Helvetica, Arial, sans-serif"')->fontFamily(),
        );
    }

    public function testFontFamilyStripsDoubleAndSingleQuotes(): void
    {
        // CSS reserves quotes for names containing whitespace.
        self::assertSame(
            ['Comic Sans MS', "Helvetica Neue", 'sans-serif'],
            $this->textWith('font-family="&quot;Comic Sans MS&quot;, \'Helvetica Neue\', sans-serif"')->fontFamily(),
        );
    }

    public function testFontFamilyAbsentReturnsEmptyList(): void
    {
        self::assertSame([], $this->textWith('')->fontFamily());
    }

    public function testFontSizeNumeric(): void
    {
        self::assertSame(14.0, $this->textWith('font-size="14"')->fontSize());
    }

    public function testFontSizeStripsUnit(): void
    {
        self::assertSame(12.0, $this->textWith('font-size="12px"')->fontSize());
    }

    public function testFontSizeNegativeReturnsNull(): void
    {
        self::assertNull($this->textWith('font-size="-1"')->fontSize());
    }

    public function testFontWeightPassesThroughRaw(): void
    {
        self::assertSame('bold', $this->textWith('font-weight="bold"')->fontWeight());
        self::assertSame('700', $this->textWith('font-weight="700"')->fontWeight());
    }

    public function testFontStyleKeywords(): void
    {
        self::assertSame('italic', $this->textWith('font-style="italic"')->fontStyle());
        self::assertSame('oblique', $this->textWith('font-style="oblique"')->fontStyle());
        self::assertSame('normal', $this->textWith('font-style="normal"')->fontStyle());
        self::assertNull($this->textWith('font-style="weird"')->fontStyle());
    }
}
