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

    public function testMathbackgroundEmptyAttrIsNull(): void
    {
        $mi = $this->extractMi('<mi mathbackground="">x</mi>');
        self::assertNull($mi->mathbackground());

        $mi = $this->extractMi('<mi mathbackground="   ">x</mi>');
        self::assertNull($mi->mathbackground());
    }

    public function testMathbackgroundDoesNotFallBackToStyleYet(): void
    {
        // MathML Core §3.2.5 mandates this fallback, but it is
        // currently gated by #103 - turning it on regresses ~18
        // WPT fixtures whose intermediate <mspace style="background:
        // red"/> markers are expected to be covered by stretchy
        // operator glyphs / fraction padding / table cells, where
        // renderer metric drift still leaks the red through.
        // When that drift is closed, flip these to assertSame.
        $mi = $this->extractMi('<mi style="background-color: red">x</mi>');
        self::assertNull($mi->mathbackground());

        $mi = $this->extractMi('<mi style="background: green">x</mi>');
        self::assertNull($mi->mathbackground());
    }

    public function testMathbackgroundUnrelatedStylePropertyIsNull(): void
    {
        // Once the #103 fallback lands, we must still not confuse
        // unrelated longhands (`color: red` is the mathcolor hook,
        // not the mathbackground hook). Guarded here so any future
        // implementation keeps the property-name match precise.
        $mi = $this->extractMi('<mi style="color: red">x</mi>');
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
