<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Layout;

use Phpdftk\Css\Value\Keyword;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Layout\FontFace;
use Phpdftk\HtmlToPdf\Layout\FontResolver;
use PHPUnit\Framework\TestCase;

final class FontResolverTest extends TestCase
{
    public function testResolverPicksExactWeightFaceFromFamily(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        // We don't have a real bold fixture; clone the regular face under
        // weight=700 so the matching algorithm has two distinct entries.
        $bold = (new OpenTypeParser($fontPath))->parse();
        $resolver = new FontResolver(
            fontMap: [],
            defaultFont: $regular,
            faceMap: [
                'inter' => [
                    new FontFace($regular, weight: 400, style: 'normal'),
                    new FontFace($bold, weight: 700, style: 'normal'),
                ],
            ],
        );
        $match = $resolver->resolveMatch(new Keyword('Inter'), weight: 700, style: 'normal');
        self::assertNotNull($match);
        self::assertSame(700, $match->face->weight);
        self::assertTrue($match->matchesWeight, 'bold request → bold face matches weight axis');
        self::assertTrue($match->matchesStyle);

        $matchNormal = $resolver->resolveMatch(new Keyword('Inter'), weight: 400, style: 'normal');
        self::assertNotNull($matchNormal);
        self::assertSame(400, $matchNormal->face->weight);
    }

    public function testResolverFallsBackToClosestWeightWhenExactMissing(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $light = (new OpenTypeParser($fontPath))->parse();
        $bold = (new OpenTypeParser($fontPath))->parse();
        $resolver = new FontResolver(
            fontMap: [],
            defaultFont: $light,
            faceMap: [
                'inter' => [
                    new FontFace($light, weight: 300, style: 'normal'),
                    new FontFace($bold, weight: 700, style: 'normal'),
                ],
            ],
        );
        // Request 500 (in the 400-500 special-case range): no exact match,
        // no face >= 500 in the in-range bucket, so the resolver scans
        // *down* and picks the 300 face per CSS Fonts 4 §6.4.
        $match = $resolver->resolveMatch(new Keyword('Inter'), weight: 500, style: 'normal');
        self::assertNotNull($match);
        self::assertSame(300, $match->face->weight, '500 → 300 (down) in 400-500 range when no upper match');
        // Request 600 (above 500): scans upward first → 700.
        $match700 = $resolver->resolveMatch(new Keyword('Inter'), weight: 600, style: 'normal');
        self::assertNotNull($match700);
        self::assertSame(700, $match700->face->weight);
    }

    public function testResolverPicksItalicFaceWhenRequestedItalic(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        $italic = (new OpenTypeParser($fontPath))->parse();
        $resolver = new FontResolver(
            fontMap: [],
            defaultFont: $regular,
            faceMap: [
                'inter' => [
                    new FontFace($regular, weight: 400, style: 'normal'),
                    new FontFace($italic, weight: 400, style: 'italic'),
                ],
            ],
        );
        $match = $resolver->resolveMatch(new Keyword('Inter'), weight: 400, style: 'italic');
        self::assertNotNull($match);
        self::assertSame('italic', $match->face->style);
        self::assertTrue($match->matchesStyle);
    }

    public function testResolverFallsBackToFontMapWhenNoFaceMapEntry(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $regular = (new OpenTypeParser($fontPath))->parse();
        $resolver = new FontResolver(
            fontMap: ['arial' => $regular],
            defaultFont: null,
            faceMap: [],
        );
        // Single-face fallback synthesises a 400-normal FontFace.
        $match = $resolver->resolveMatch(new Keyword('Arial'), weight: 700, style: 'normal');
        self::assertNotNull($match);
        self::assertSame(400, $match->face->weight);
        // Bold request against a 400-normal fallback face: matchesWeight
        // is false → painter will apply synthetic fake-bold.
        self::assertFalse($match->matchesWeight);
    }

    public function testFontFaceRejectsOutOfRangeWeight(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $data = (new OpenTypeParser($fontPath))->parse();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('~1-1000~');
        new FontFace($data, weight: 1500);
    }

    public function testFontFaceRejectsUnknownStyle(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $data = (new OpenTypeParser($fontPath))->parse();
        $this->expectException(\InvalidArgumentException::class);
        new FontFace($data, style: 'condensed');
    }

    public function testFontFaceRejectsOutOfRangeStretch(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $data = (new OpenTypeParser($fontPath))->parse();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('~50-200~');
        new FontFace($data, stretch: 400.0);
    }

    public function testResolveMatchPicksClosestStretch(): void
    {
        $fontPath = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';
        if (!is_file($fontPath)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        $data = (new OpenTypeParser($fontPath))->parse();
        // Same family, three stretch variants — all 400-normal but
        // different stretches. The matcher should pick the one
        // whose stretch is closest to the request.
        $faceMap = [
            'flex' => [
                new FontFace($data, 400, 'normal', 75.0),   // condensed
                new FontFace($data, 400, 'normal', 100.0),  // normal
                new FontFace($data, 400, 'normal', 125.0),  // expanded
            ],
        ];
        $resolver = new FontResolver(
            fontMap: [],
            defaultFont: null,
            faceMap: $faceMap,
        );
        $request = new Keyword('flex');
        $match = $resolver->resolveMatch($request, 400, 'normal', stretch: 70.0);
        self::assertNotNull($match);
        self::assertSame(75.0, $match->face->stretch);
        $match = $resolver->resolveMatch($request, 400, 'normal', stretch: 140.0);
        self::assertNotNull($match);
        self::assertSame(125.0, $match->face->stretch);
    }
}
