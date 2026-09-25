<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * CSS Variables 1 §3 + SVG 2 §6.7 — `var()` inside a geometry
 * PRESENTATION ATTRIBUTE.
 *
 * A presentation attribute is an ordinary CSS declaration, so
 * `width="var(--length)"` is legal. The painter reads geometry
 * straight off the attribute though, where the raw `var(...)` text
 * parses as nothing and the shape collapsed to zero. The projector
 * writes the substituted value back over the attribute.
 *
 * The negative half matters as much as the positive one: a
 * substituted value still has to BE valid for the property. A
 * presentation attribute may be a unitless number, but the CSS
 * `width` property may not, so `--length: 100` is invalid at
 * computed-value time and the shape must NOT render — which is
 * exactly what WPT's css-var-on-length-attributes-02 and -04 pin.
 */
final class GeometryVarSubstitutionTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    // ---------------------------------------------------------------
    // Negative cases.
    // ---------------------------------------------------------------

    /** A unitless substitution is invalid for `width` and paints nothing. */
    public function testAUnitlessSubstitutionIsInvalidAndPaintsNothing(): void
    {
        $ops = $this->paint(
            '<style>rect { --length: 100; }</style>'
            . '<rect width="var(--length)" height="var(--length)" fill="#00ff00"/>',
        );
        self::assertStringNotContainsString('re', $ops);
    }

    /** A unitless FALLBACK is just as invalid. */
    public function testAUnitlessFallbackIsInvalidToo(): void
    {
        $ops = $this->paint(
            '<rect width="var(--length,100)" height="var(--length,100)" fill="#00ff00"/>',
        );
        self::assertStringNotContainsString('re', $ops);
    }

    /** An undefined variable with no fallback paints nothing. */
    public function testAnUndefinedVariableWithNoFallbackPaintsNothing(): void
    {
        $ops = $this->paint('<rect width="var(--nope)" height="var(--nope)" fill="#ff0000"/>');
        self::assertStringNotContainsString('re', $ops);
    }

    /** An attribute with no `var()` is left exactly as the author wrote it. */
    public function testAttributesWithoutVarAreUntouched(): void
    {
        $ops = $this->paint('<rect width="40" height="30" fill="#00ff00"/>');
        self::assertStringContainsString('0 0 40 30 re', $ops);
    }

    /**
     * A unitless attribute the author wrote DIRECTLY is still valid SVG
     * — only a `var()` substitution has to survive computed-value time.
     */
    public function testADirectUnitlessAttributeStillWorks(): void
    {
        $ops = $this->paint('<rect width="100" height="100" fill="#00ff00"/>');
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }

    // ---------------------------------------------------------------
    // Positive cases.
    // ---------------------------------------------------------------

    public function testVarWithUnitsResolves(): void
    {
        $ops = $this->paint(
            '<style>rect { --length: 100px; }</style>'
            . '<rect width="var(--length)" height="var(--length)" fill="#00ff00"/>',
        );
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }

    public function testVarFallbackWithUnitsResolves(): void
    {
        $ops = $this->paint(
            '<rect width="var(--length,60px)" height="var(--length,40px)" fill="#00ff00"/>',
        );
        self::assertStringContainsString('0 0 60 40 re', $ops);
    }

    /** A percentage substitution resolves against the viewport. */
    public function testAPercentageSubstitutionResolvesAgainstTheViewport(): void
    {
        $ops = $this->paint(
            '<style>rect { --w: 50%; }</style>'
            . '<rect width="var(--w)" height="var(--w)" fill="#00ff00"/>',
        );
        self::assertStringContainsString('0 0 100 100 re', $ops);
    }

    /** The variable may be inherited from an ancestor. */
    public function testAnInheritedVariableResolves(): void
    {
        $ops = $this->paint(
            '<g style="--length: 70px"><rect width="var(--length)" height="var(--length)"'
            . ' fill="#00ff00"/></g>',
        );
        self::assertStringContainsString('0 0 70 70 re', $ops);
    }

    /** `x` / `y` substitute too, not just the sizes. */
    public function testPositionAttributesSubstituteAsWell(): void
    {
        $ops = $this->paint(
            '<style>rect { --o: 15px; }</style>'
            . '<rect x="var(--o)" y="var(--o)" width="10" height="10" fill="#00ff00"/>',
        );
        self::assertStringContainsString('15 15 10 10 re', $ops);
    }
}
