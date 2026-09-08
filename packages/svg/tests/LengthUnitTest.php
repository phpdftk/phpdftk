<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Absolute unit suffixes on SVG length attributes convert to user units
 * (CSS Values 4 §6.2 at 96dpi) rather than being discarded — `2cm` is
 * 75.59 user units, not 2.
 */
final class LengthUnitTest extends TestCase
{
    private const float DELTA = 1.0e-6;

    private function rectWidth(string $raw): float
    {
        $doc = (new Parser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="' . $raw . '"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $rect);
        return $rect->width();
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function absoluteUnitProvider(): iterable
    {
        yield 'unitless is user units' => ['2', 2.0];
        yield 'px' => ['2px', 2.0];
        yield 'cm' => ['2cm', 75.5905511811];
        yield 'mm' => ['20mm', 75.5905511811];
        yield 'in' => ['1in', 96.0];
        yield 'pt' => ['72pt', 96.0];
        yield 'pc' => ['6pc', 96.0];
        yield 'uppercase unit' => ['1IN', 96.0];
    }

    #[DataProvider('absoluteUnitProvider')]
    public function testAbsoluteUnitsConvertToUserUnits(string $raw, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->rectWidth($raw), self::DELTA);
    }

    /**
     * Percentages and font-relative units need viewport or font context
     * this accessor does not have, so their numeric part is returned
     * unscaled — the long-standing behaviour, left deliberately intact.
     */
    #[DataProvider('contextualUnitProvider')]
    public function testContextualUnitsAreLeftUnscaled(string $raw, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->rectWidth($raw), self::DELTA);
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function contextualUnitProvider(): iterable
    {
        yield 'percentage' => ['50%', 50.0];
        yield 'em' => ['2em', 2.0];
        yield 'ex' => ['2ex', 2.0];
    }

    public function testUnparseableValueFallsBackToZero(): void
    {
        self::assertSame(0.0, $this->rectWidth('bogus'));
    }
}
