<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Mlabeledtr;
use Phpdftk\Mathml\MathmlDocument;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser tests for newly-added attributes / elements:
 *
 *   - <math displaystyle / scriptlevel> root attribute accessors.
 *   - mathsize on any token element (small / normal / big / length).
 *   - <mlabeledtr> typed-element dispatch.
 */
final class MathRootAndMathsizeTest extends TestCase
{
    // ---- <math> root attributes ----

    public function testMathDisplaystyleTrue(): void
    {
        $doc = $this->parse('<math displaystyle="true"></math>');
        self::assertTrue($doc->displaystyle());
    }

    public function testMathDisplaystyleFalse(): void
    {
        $doc = $this->parse('<math displaystyle="false"></math>');
        self::assertFalse($doc->displaystyle());
    }

    public function testMathDisplaystyleAbsentIsNull(): void
    {
        $doc = $this->parse('<math></math>');
        self::assertNull($doc->displaystyle());
    }

    public function testMathScriptlevelInteger(): void
    {
        $doc = $this->parse('<math scriptlevel="2"></math>');
        self::assertSame(2, $doc->scriptlevel());
    }

    public function testMathScriptlevelRelativeIsRejected(): void
    {
        // Relative forms don't make sense at the root - no parent.
        // The accessor returns null so the painter applies the
        // default (0).
        $doc = $this->parse('<math scriptlevel="+1"></math>');
        self::assertNull($doc->scriptlevel());
    }

    public function testMathScriptlevelMalformedIsNull(): void
    {
        $doc = $this->parse('<math scriptlevel="banana"></math>');
        self::assertNull($doc->scriptlevel());
    }

    // ---- mathsize on any element ----

    public function testMathsizeKeywords(): void
    {
        $mi = $this->extractMi('<math><mi mathsize="small">x</mi></math>');
        self::assertSame(['scale', 0.7], $mi->mathsize());

        $mi = $this->extractMi('<math><mi mathsize="normal">x</mi></math>');
        self::assertSame(['scale', 1.0], $mi->mathsize());

        $mi = $this->extractMi('<math><mi mathsize="big">x</mi></math>');
        self::assertSame(['scale', 1.4], $mi->mathsize());
    }

    public function testMathsizeEmLength(): void
    {
        $mi = $this->extractMi('<math><mi mathsize="1.5em">x</mi></math>');
        self::assertSame(['scale', 1.5], $mi->mathsize());
    }

    public function testMathsizePtLength(): void
    {
        $mi = $this->extractMi('<math><mi mathsize="18pt">x</mi></math>');
        self::assertSame(['absolute', 18.0], $mi->mathsize());
    }

    public function testMathsizeUnitlessIsScale(): void
    {
        $mi = $this->extractMi('<math><mi mathsize="2">x</mi></math>');
        self::assertSame(['scale', 2.0], $mi->mathsize());
    }

    public function testMathsizeMalformedIsNull(): void
    {
        $mi = $this->extractMi('<math><mi mathsize="banana">x</mi></math>');
        self::assertNull($mi->mathsize());

        $mi = $this->extractMi('<math><mi mathsize="">x</mi></math>');
        self::assertNull($mi->mathsize());

        $mi = $this->extractMi('<math><mi mathsize="-1em">x</mi></math>');
        self::assertNull($mi->mathsize());
    }

    public function testMathsizeAbsent(): void
    {
        $mi = $this->extractMi('<math><mi>x</mi></math>');
        self::assertNull($mi->mathsize());
    }

    // ---- <mlabeledtr> ----

    public function testMlabeledtrElementType(): void
    {
        $doc = $this->parse(
            '<math><mtable>'
            . '<mlabeledtr>'
            . '<mtd><mtext>(1)</mtext></mtd>'
            . '<mtd><mn>1</mn></mtd>'
            . '</mlabeledtr>'
            . '</mtable></math>',
        );
        $found = false;
        foreach ($doc->children as $mathChild) {
            foreach ($mathChild->children as $tableChild) {
                if ($tableChild instanceof Mlabeledtr) {
                    $found = true;
                    break 2;
                }
            }
        }
        self::assertTrue($found, '<mlabeledtr> should be typed');
    }

    private function parse(string $xml): MathmlDocument
    {
        // Inject the MathML namespace into the root element. The
        // test inputs all start with `<math` so we splice xmlns in
        // right after the opening tag name.
        $withNs = preg_replace(
            '/^<math(\s|>)/',
            '<math xmlns="http://www.w3.org/1998/Math/MathML"$1',
            $xml,
            1,
        );
        return (new Parser())->parse($withNs);
    }

    private function extractMi(string $xml): Mi
    {
        $doc = $this->parse($xml);
        foreach ($doc->children as $child) {
            if ($child instanceof Mi) {
                return $child;
            }
        }
        self::fail('no <mi> in document');
    }
}
