<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata\Tests;

use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\ImageMetadata\SvgParser;
use PHPUnit\Framework\TestCase;

/**
 * Verify the SVG header parser extracts intrinsic dimensions per
 * the cases CSS Images 3 §3 / SVG 2 §6.1 distinguishes:
 * explicit width/height, ratio from viewBox, width or height
 * derived from the other axis + viewBox ratio, and the practical
 * fallback to viewBox dims when no width/height is given.
 */
final class SvgParserTest extends TestCase
{
    public function testExplicitWidthAndHeight(): void
    {
        $info = SvgParser::parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50"></svg>',
        );
        self::assertSame(100, $info->width);
        self::assertSame(50, $info->height);
        self::assertEqualsWithDelta(2.0, $info->intrinsicRatio, 1e-9);
        self::assertSame('svg', $info->format);
    }

    public function testWidthAndHeightWithPxUnits(): void
    {
        $info = SvgParser::parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100px" height="50px"/>',
        );
        self::assertSame(100, $info->width);
        self::assertSame(50, $info->height);
    }

    public function testWidthOnlyPlusViewBoxDerivesHeight(): void
    {
        // width="200" + viewBox 0 0 100 50 → ratio 2:1, so height = 100.
        $info = SvgParser::parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" viewBox="0 0 100 50"></svg>',
        );
        self::assertSame(200, $info->width);
        self::assertSame(100, $info->height);
        self::assertEqualsWithDelta(2.0, $info->intrinsicRatio, 1e-9);
    }

    public function testHeightOnlyPlusViewBoxDerivesWidth(): void
    {
        // height="100" + viewBox 0 0 50 25 → ratio 2:1, so width = 200.
        $info = SvgParser::parse(
            '<svg xmlns="http://www.w3.org/2000/svg" height="100" viewBox="0 0 50 25"></svg>',
        );
        self::assertSame(200, $info->width);
        self::assertSame(100, $info->height);
    }

    public function testViewBoxOnlyFallsBackToViewBoxDimensions(): void
    {
        // No explicit width/height. Fall back to viewBox dims so the
        // box generator and painter agree on the intrinsic size.
        $info = SvgParser::parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>',
        );
        self::assertSame(100, $info->width);
        self::assertSame(100, $info->height);
        self::assertEqualsWithDelta(1.0, $info->intrinsicRatio, 1e-9);
    }

    public function testNonPixelAbsoluteUnitsResolveToPx(): void
    {
        // 72pt = 96px per CSS Values 3 §5.2.
        $info = SvgParser::parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="72pt" height="1in"></svg>',
        );
        self::assertSame(96, $info->width);
        self::assertSame(96, $info->height);
    }

    public function testPercentageAttributesYieldNoIntrinsicSize(): void
    {
        // Percentage width/height are not intrinsic; with no viewBox
        // either, we have nothing to report.
        $info = SvgParser::parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>',
        );
        self::assertSame(0, $info->width);
        self::assertSame(0, $info->height);
        self::assertNull($info->intrinsicRatio);
    }

    public function testImageParserSniffsSvgFromXmlProlog(): void
    {
        // The top-level dispatcher should route to SvgParser when the
        // payload looks like an SVG document.
        $svg = '<?xml version="1.0"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="20"></svg>';
        $info = ImageParser::parseString($svg);
        self::assertSame('svg', $info->format);
        self::assertSame(40, $info->width);
        self::assertSame(20, $info->height);
    }

    public function testImageParserSniffsSvgWithoutProlog(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="40"></svg>';
        $info = ImageParser::parseString($svg);
        self::assertSame('svg', $info->format);
        self::assertSame(80, $info->width);
        self::assertSame(40, $info->height);
    }

    public function testImageParserParsesSvgFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdftk-svg-');
        self::assertNotFalse($path);
        try {
            file_put_contents(
                $path,
                '<?xml version="1.0"?>'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="90"></svg>',
            );
            $info = ImageParser::parse($path);
            self::assertSame('svg', $info->format);
            self::assertSame(160, $info->width);
            self::assertSame(90, $info->height);
        } finally {
            @unlink($path);
        }
    }
}
