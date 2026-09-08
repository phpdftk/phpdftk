<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Value;

use Phpdftk\Svg\Value\TransformOrigin;
use PHPUnit\Framework\TestCase;

final class TransformOriginTest extends TestCase
{
    private const float DELTA = 1.0e-9;

    /**
     * The bounding box used throughout: a 150x150 box offset to (75,75),
     * matching the WPT `svg-origin-relative-length-*` fixtures.
     */
    private const array BBOX = [75.0, 75.0, 150.0, 150.0];

    /**
     * @param array{float, float} $expected
     */
    private function assertResolves(string $raw, array $expected): void
    {
        $origin = TransformOrigin::parse($raw);
        self::assertNotNull($origin, sprintf('"%s" should parse', $raw));
        [$x, $y] = $origin->resolve(...self::BBOX);
        self::assertEqualsWithDelta($expected[0], $x, self::DELTA, 'x');
        self::assertEqualsWithDelta($expected[1], $y, self::DELTA, 'y');
    }

    public function testPercentagesResolveAgainstTheBoundingBoxIncludingItsOffset(): void
    {
        $this->assertResolves('0% 0%', [75.0, 75.0]);
        $this->assertResolves('100% 100%', [225.0, 225.0]);
        $this->assertResolves('50% 50%', [150.0, 150.0]);
    }

    public function testLengthsOffsetFromTheReferenceBoxOrigin(): void
    {
        $this->assertResolves('100px 0', [175.0, 75.0]);
        $this->assertResolves('50 50', [125.0, 125.0]);
    }

    public function testAbsoluteUnitsConvertToUserUnits(): void
    {
        $this->assertResolves('2cm 0', [150.5905511811, 75.0]);
        $this->assertResolves('1in 0', [171.0, 75.0]);
        $this->assertResolves('72pt 0', [171.0, 75.0]);
    }

    public function testSingleComponentLeavesTheOtherAxisAtCenter(): void
    {
        $this->assertResolves('75', [150.0, 150.0]);
        $this->assertResolves('center', [150.0, 150.0]);
        $this->assertResolves('top', [150.0, 75.0]);
    }

    public function testKeywordPairsMayAppearInEitherOrder(): void
    {
        $this->assertResolves('left top', [75.0, 75.0]);
        $this->assertResolves('top left', [75.0, 75.0]);
        $this->assertResolves('bottom right', [225.0, 225.0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProvider(): iterable
    {
        // An axis keyword may not lead the ordered two-value form...
        yield 'y-keyword then percentage' => ['top 100%'];
        yield 'y-keyword then length' => ['bottom 150'];
        // ...and a pair may not name the same axis twice.
        yield 'two y-keywords, same' => ['top top'];
        yield 'two y-keywords, opposed' => ['top bottom'];
        yield 'two x-keywords, same' => ['right right'];
        yield 'two x-keywords, opposed' => ['left right'];
        yield 'x-keyword in the y slot' => ['0 right'];
        yield 'unparseable component' => ['bogus'];
        yield 'too many components' => ['0 0 0 0'];
        yield 'percentage z offset' => ['0 0 50%'];
        yield 'empty' => [''];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProvider')]
    public function testInvalidDeclarationsFallBackToTheInitialValue(string $raw): void
    {
        self::assertNull(TransformOrigin::parse($raw));
    }

    public function testThirdComponentIsAcceptedAndIgnored(): void
    {
        // The Z offset parses (so the declaration stays valid) but this
        // renderer is 2D and drops it.
        $this->assertResolves('0 0 10px', [75.0, 75.0]);
    }
}
