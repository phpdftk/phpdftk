<?php

declare(strict_types=1);

namespace Phpdftk\Raster\Tests;

use Phpdftk\Raster\BlendMode;
use PHPUnit\Framework\TestCase;

/**
 * Sanity test for the BlendMode enum. The interesting bit is the
 * `pdfNativeName()` / `requiresRaster()` discriminator — the
 * translator uses it to decide whether to keep a subtree in vector
 * PDF primitives or raise it into the raster compositor.
 */
final class BlendModeTest extends TestCase
{
    public function testCssNamesMatchSpec(): void
    {
        // CSS Compositing 1 §3.5 — the canonical mix-blend-mode
        // keyword for each blend mode.
        self::assertSame('normal', BlendMode::Normal->value);
        self::assertSame('multiply', BlendMode::Multiply->value);
        self::assertSame('color-dodge', BlendMode::ColorDodge->value);
        self::assertSame('hard-light', BlendMode::HardLight->value);
        self::assertSame('plus-darker', BlendMode::PlusDarker->value);
    }

    public function testPdfNativeBlendModesReturnPdfName(): void
    {
        self::assertSame('Normal', BlendMode::Normal->pdfNativeName());
        self::assertSame('Multiply', BlendMode::Multiply->pdfNativeName());
        self::assertSame('ColorDodge', BlendMode::ColorDodge->pdfNativeName());
        self::assertSame('HardLight', BlendMode::HardLight->pdfNativeName());
        self::assertSame('Luminosity', BlendMode::Luminosity->pdfNativeName());
    }

    public function testCssOnlyBlendModesReturnNull(): void
    {
        // CSS Compositing 2 — Apple Core Animation legacy. No PDF
        // equivalent; must rasterise.
        self::assertNull(BlendMode::PlusDarker->pdfNativeName());
        self::assertNull(BlendMode::PlusLighter->pdfNativeName());
    }

    public function testRequiresRasterMatchesNullPdfNativeName(): void
    {
        foreach (BlendMode::cases() as $mode) {
            self::assertSame(
                $mode->pdfNativeName() === null,
                $mode->requiresRaster(),
                "requiresRaster mismatch for {$mode->value}",
            );
        }
    }

    public function testAllSixteenPdfNativeModesArePresent(): void
    {
        // PDF 32000-2 Table 136 — sixteen native blend modes.
        $pdfNative = [];
        foreach (BlendMode::cases() as $mode) {
            $name = $mode->pdfNativeName();
            if ($name !== null) {
                $pdfNative[] = $name;
            }
        }
        sort($pdfNative);
        self::assertSame([
            'Color',
            'ColorBurn',
            'ColorDodge',
            'Darken',
            'Difference',
            'Exclusion',
            'HardLight',
            'Hue',
            'Lighten',
            'Luminosity',
            'Multiply',
            'Normal',
            'Overlay',
            'Saturation',
            'Screen',
            'SoftLight',
        ], $pdfNative);
    }
}
