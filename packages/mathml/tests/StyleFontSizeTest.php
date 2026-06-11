<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser tests for {@see Element::styleFontSizePt()}. The accessor
 * resolves a CSS `font-size` declaration in the element's `style`
 * attribute to PDF user-space points relative to a supplied parent
 * font size. Used by the MathmlToPdf painter and fed by the
 * wpt-harness DOM settler's per-element cascade projection (#107).
 */
final class StyleFontSizeTest extends TestCase
{
    public function testPxAndPtMapOneToOne(): void
    {
        // px / pt resolve at 1:1 to match html-to-pdf's CSS
        // convention - parent size doesn't affect them.
        self::assertEqualsWithDelta(
            15.0,
            $this->parse('<mi style="font-size: 15px">x</mi>')
                ->styleFontSizePt(12.0),
            0.001,
        );
        self::assertEqualsWithDelta(
            18.0,
            $this->parse('<mi style="font-size: 18pt">x</mi>')
                ->styleFontSizePt(12.0),
            0.001,
        );
    }

    public function testEmScalesWithParentFontSize(): void
    {
        // 1.5em at parentFontSize 12 -> 18pt
        self::assertEqualsWithDelta(
            18.0,
            $this->parse('<mi style="font-size: 1.5em">x</mi>')
                ->styleFontSizePt(12.0),
            0.001,
        );
        // 15em at parentFontSize 1 (the frac-bar test pattern with
        // <math style="font-size: 1px">) -> 15pt
        self::assertEqualsWithDelta(
            15.0,
            $this->parse('<mi style="font-size: 15em">x</mi>')
                ->styleFontSizePt(1.0),
            0.001,
        );
    }

    public function testUnitlessScalesAsEm(): void
    {
        // Unitless values treated as em multipliers per CSS spec
        // legacy behaviour - matches what browsers compute.
        self::assertEqualsWithDelta(
            24.0,
            $this->parse('<mi style="font-size: 2">x</mi>')
                ->styleFontSizePt(12.0),
            0.001,
        );
    }

    public function testPercentScalesWithParent(): void
    {
        // 150% at parentFontSize 12 -> 18pt
        self::assertEqualsWithDelta(
            18.0,
            $this->parse('<mi style="font-size: 150%">x</mi>')
                ->styleFontSizePt(12.0),
            0.001,
        );
    }

    public function testAbsentStyleReturnsNull(): void
    {
        self::assertNull(
            $this->parse('<mi>x</mi>')->styleFontSizePt(12.0),
        );
        self::assertNull(
            $this->parse('<mi style="color: red">x</mi>')
                ->styleFontSizePt(12.0),
        );
    }

    public function testUnparseableReturnsNull(): void
    {
        self::assertNull(
            $this->parse('<mi style="font-size: banana">x</mi>')
                ->styleFontSizePt(12.0),
        );
        self::assertNull(
            $this->parse('<mi style="font-size: -1em">x</mi>')
                ->styleFontSizePt(12.0),
        );
    }

    public function testSettlerProjectionMarkerStrippedBeforeMatch(): void
    {
        // The DOM settler prefixes its projections with a
        // /* phpdftk-settle-dom */ marker. extractStyleProperty
        // strips block comments before tokenising, so the
        // accessor should ignore the marker and match the actual
        // declarations.
        $mi = $this->parse(
            '<mi style="color: red; /* phpdftk-settle-dom */ font-size: 15px">x</mi>',
        );
        self::assertEqualsWithDelta(15.0, $mi->styleFontSizePt(12.0), 0.001);
    }

    public function testAuthorInlineAndProjectedDeclarationsCoexist(): void
    {
        // The test exposes the settler's idiom: the author's
        // original em-relative declaration stays first, followed by
        // the computed projection. extractStyleProperty returns the
        // FIRST match, so the painter sees the author's intent.
        $mi = $this->parse(
            '<mi style="font-size: 15em; /* phpdftk-settle-dom */ font-size: 15px">x</mi>',
        );
        // 15em at parent 1.0 -> 15pt; same as 15px coincidentally
        // (this is the frac-bar test pattern).
        self::assertEqualsWithDelta(15.0, $mi->styleFontSizePt(1.0), 0.001);
    }

    private function parse(string $miXml): Mi
    {
        $doc = (new Parser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $miXml
            . '</math>',
        );
        foreach ($doc->children as $child) {
            if ($child instanceof Mi) {
                return $child;
            }
        }
        self::fail('no <mi> in document');
    }
}
