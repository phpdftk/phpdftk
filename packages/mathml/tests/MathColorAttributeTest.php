<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser tests for the mathcolor / mathbackground accessors on
 * Element.
 */
final class MathColorAttributeTest extends TestCase
{
    public function testMathcolorReturnsTrimmedString(): void
    {
        $mi = $this->extractMi('<mi mathcolor="  red  ">x</mi>');
        self::assertSame('red', $mi->mathcolor());
    }

    public function testMathcolorAbsentIsNull(): void
    {
        $mi = $this->extractMi('<mi>x</mi>');
        self::assertNull($mi->mathcolor());
    }

    public function testMathcolorEmptyIsNull(): void
    {
        $mi = $this->extractMi('<mi mathcolor="">x</mi>');
        self::assertNull($mi->mathcolor());

        $mi = $this->extractMi('<mi mathcolor="   ">x</mi>');
        self::assertNull($mi->mathcolor());
    }

    public function testMathbackgroundReturnsTrimmedString(): void
    {
        $mi = $this->extractMi('<mi mathbackground="yellow">x</mi>');
        self::assertSame('yellow', $mi->mathbackground());
    }

    public function testMathbackgroundAbsentIsNull(): void
    {
        $mi = $this->extractMi('<mi>x</mi>');
        self::assertNull($mi->mathbackground());
    }

    public function testHexColorPassesThrough(): void
    {
        $mi = $this->extractMi('<mi mathcolor="#ff0000">x</mi>');
        self::assertSame('#ff0000', $mi->mathcolor());
    }

    public function testRgbFunctionPassesThrough(): void
    {
        $mi = $this->extractMi(
            '<mi mathcolor="rgb(255, 0, 0)">x</mi>',
        );
        self::assertSame('rgb(255, 0, 0)', $mi->mathcolor());
    }

    private function extractMi(string $miXml): Mi
    {
        $doc = (new Parser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $miXml . '</math>',
        );
        foreach ($doc->children as $child) {
            if ($child instanceof Mi) {
                return $child;
            }
        }
        self::fail('no <mi> in document');
    }
}
