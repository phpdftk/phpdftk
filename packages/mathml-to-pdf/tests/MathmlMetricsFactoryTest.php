<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\MathmlMetricsFactory;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for {@see MathmlMetricsFactory::fromMathFont()}
 * against the WPT synthetic math fonts under
 * vendor-data/wpt/fonts/math/.
 *
 * The factory must:
 *   - decompress WOFF fonts via WoffParser
 *   - parse the underlying OpenType CFF
 *   - throw when no MATH table is present
 *   - produce a populated metrics object when one is
 *
 * Tests skip themselves when the WPT submodule isn't checked out.
 */
final class MathmlMetricsFactoryTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testLoadsMetricsFromWoffMathFont(): void
    {
        $path = $this->wpt('fraction-rulethickness10000.woff');
        $metrics = MathmlMetricsFactory::fromMathFont($path);
        self::assertTrue($metrics->isMathFontActive());
    }

    public function testFractionRuleThicknessFlowsThroughMetrics(): void
    {
        // The WPT font sets fractionRuleThickness = 10000 with
        // unitsPerEm = 1000 -> 10 em (extremely thick).
        $path = $this->wpt('fraction-rulethickness10000.woff');
        $metrics = MathmlMetricsFactory::fromMathFont($path);
        self::assertEqualsWithDelta(10.0, $metrics->fractionRuleThicknessEm(), 0.0001);
    }

    public function testAxisHeightFlowsThroughMetrics(): void
    {
        $path = $this->wpt('axisheight5000-verticalarrow14000.woff');
        $metrics = MathmlMetricsFactory::fromMathFont($path);
        // axisHeight = 5000 in a 1000-unitsPerEm font -> 5.0 em.
        self::assertEqualsWithDelta(5.0, $metrics->axisHeightEm(), 0.0001);
    }

    public function testThrowsOnNonMathFont(): void
    {
        // The shared NotoSansMongolian fixture has no MATH table.
        // The path is OTF (not WOFF) so the factory goes directly
        // through OpenTypeParser.
        $path = dirname(__DIR__, 3) . '/tests/fixtures/fonts/NotoSansMongolian-Regular.otf';
        if (!is_file($path)) {
            self::markTestSkipped("Shared non-math fixture not available: $path");
        }
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no MATH table/');
        MathmlMetricsFactory::fromMathFont($path);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        MathmlMetricsFactory::fromMathFont('/nonexistent/font.otf');
    }

    private function wpt(string $name): string
    {
        $path = self::WPT_MATH_FONTS_DIR . '/' . $name;
        if (!is_file($path)) {
            self::markTestSkipped(
                "WPT math font fixture not available: $path. "
                . "Run `git submodule update --init vendor-data/wpt`.",
            );
        }
        return $path;
    }
}
