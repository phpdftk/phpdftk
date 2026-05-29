<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\ClipPath;
use Phpdftk\Svg\Image;
use Phpdftk\Svg\Mask;
use Phpdftk\Svg\Parser;
use PHPUnit\Framework\TestCase;

final class ClipMaskImageTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testClipPathDefaultUnits(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><clipPath id="c"/></svg>',
        );
        $clip = $doc->children[0];
        self::assertInstanceOf(ClipPath::class, $clip);
        self::assertSame('userSpaceOnUse', $clip->clipPathUnits());
    }

    public function testClipPathExplicitObjectBoundingBox(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><clipPath clipPathUnits="objectBoundingBox"/></svg>',
        );
        $clip = $doc->children[0];
        self::assertInstanceOf(ClipPath::class, $clip);
        self::assertSame('objectBoundingBox', $clip->clipPathUnits());
    }

    public function testClipPathUnknownUnitsFallsBackToDefault(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><clipPath clipPathUnits="weird"/></svg>',
        );
        $clip = $doc->children[0];
        self::assertInstanceOf(ClipPath::class, $clip);
        self::assertSame('userSpaceOnUse', $clip->clipPathUnits());
    }

    public function testMaskDefaultUnits(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><mask id="m"/></svg>',
        );
        $mask = $doc->children[0];
        self::assertInstanceOf(Mask::class, $mask);
        self::assertSame('objectBoundingBox', $mask->maskUnits());
        self::assertSame('userSpaceOnUse', $mask->maskContentUnits());
    }

    public function testMaskExplicitUnits(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<mask maskUnits="userSpaceOnUse" maskContentUnits="objectBoundingBox"/>'
            . '</svg>',
        );
        $mask = $doc->children[0];
        self::assertInstanceOf(Mask::class, $mask);
        self::assertSame('userSpaceOnUse', $mask->maskUnits());
        self::assertSame('objectBoundingBox', $mask->maskContentUnits());
    }

    public function testMaskGeometryNullableWhenAbsent(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><mask/></svg>',
        );
        $mask = $doc->children[0];
        self::assertInstanceOf(Mask::class, $mask);
        self::assertNull($mask->x());
        self::assertNull($mask->y());
        self::assertNull($mask->width());
        self::assertNull($mask->height());
    }

    public function testMaskGeometryWhenSet(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><mask x="-5" y="-5" width="10" height="10"/></svg>',
        );
        $mask = $doc->children[0];
        self::assertInstanceOf(Mask::class, $mask);
        self::assertSame(-5.0, $mask->x());
        self::assertSame(-5.0, $mask->y());
        self::assertSame(10.0, $mask->width());
        self::assertSame(10.0, $mask->height());
    }

    public function testMaskNegativeWidthAndHeightRejected(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><mask width="-1" height="-1"/></svg>',
        );
        $mask = $doc->children[0];
        self::assertInstanceOf(Mask::class, $mask);
        self::assertNull($mask->width());
        self::assertNull($mask->height());
    }

    public function testImageBasicGeometry(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="10" y="20" width="100" height="50"/></svg>',
        );
        $image = $doc->children[0];
        self::assertInstanceOf(Image::class, $image);
        self::assertSame(10.0, $image->x());
        self::assertSame(20.0, $image->y());
        self::assertSame(100.0, $image->width());
        self::assertSame(50.0, $image->height());
    }

    public function testImageXYDefaultZero(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><image width="1" height="1"/></svg>',
        );
        $image = $doc->children[0];
        self::assertInstanceOf(Image::class, $image);
        self::assertSame(0.0, $image->x());
        self::assertSame(0.0, $image->y());
    }

    public function testImageHrefReturnsRawString(): void
    {
        // Unlike `<use>` we do NOT strip the `#`. The painter / resource
        // loader needs the verbatim URL to decide what's safe.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="photo.png"/></svg>',
        );
        $image = $doc->children[0];
        self::assertInstanceOf(Image::class, $image);
        self::assertSame('photo.png', $image->href());
    }

    public function testImageHrefAcceptsDataUrlVerbatim(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,abc"/></svg>',
        );
        $image = $doc->children[0];
        self::assertInstanceOf(Image::class, $image);
        self::assertSame('data:image/png;base64,abc', $image->href());
    }

    public function testImageFallsBackToXlinkHref(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<image xlink:href="legacy.png"/></svg>',
        );
        $image = $doc->children[0];
        self::assertInstanceOf(Image::class, $image);
        self::assertSame('legacy.png', $image->href());
    }

    public function testImagePreserveAspectRatio(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image preserveAspectRatio="xMidYMid meet" href="x.png"/></svg>',
        );
        $image = $doc->children[0];
        self::assertInstanceOf(Image::class, $image);
        self::assertSame('xMidYMid meet', $image->preserveAspectRatio());
    }
}
