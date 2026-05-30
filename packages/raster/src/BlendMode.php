<?php

declare(strict_types=1);

namespace Phpdftk\Raster;

/**
 * CSS Compositing and Blending 1 + 2 blend modes.
 *
 * Some modes have direct PDF equivalents (PDF 32000-2 Table 136 —
 * `Normal`, `Multiply`, `Screen`, `Overlay`, `Darken`, `Lighten`,
 * `ColorDodge`, `ColorBurn`, `HardLight`, `SoftLight`, `Difference`,
 * `Exclusion`, `Hue`, `Saturation`, `Color`, `Luminosity`). The
 * translator emits these as PDF native ExtGState `/BM` values and
 * never needs to raster.
 *
 * The CSS-only modes (`PlusDarker`, `PlusLighter` — Apple Core
 * Animation legacy still referenced from CSS Compositing 2) require
 * raster. Phase 4C.2 implements the per-mode pixel math for raster
 * fallback.
 *
 * `name()` returns the CSS keyword exactly as it appears in the
 * spec (`mix-blend-mode: <name>`). `pdfNativeName()` returns the
 * PDF `/BM` name, or null if the mode has no direct PDF equivalent
 * and must be rasterised.
 */
enum BlendMode: string
{
    case Normal = 'normal';
    case Multiply = 'multiply';
    case Screen = 'screen';
    case Overlay = 'overlay';
    case Darken = 'darken';
    case Lighten = 'lighten';
    case ColorDodge = 'color-dodge';
    case ColorBurn = 'color-burn';
    case HardLight = 'hard-light';
    case SoftLight = 'soft-light';
    case Difference = 'difference';
    case Exclusion = 'exclusion';
    case Hue = 'hue';
    case Saturation = 'saturation';
    case Color = 'color';
    case Luminosity = 'luminosity';
    /**
     * CSS Compositing 2 — Apple Core Animation legacy; raster-only.
     */
    case PlusDarker = 'plus-darker';
    /**
     * CSS Compositing 2 — Apple Core Animation legacy; raster-only.
     */
    case PlusLighter = 'plus-lighter';

    /**
     * PDF 32000-2 Table 136 `/BM` name for this mode, or null if
     * the mode has no PDF-native form and must be rasterised. The
     * translator checks this before deciding whether to lift the
     * subtree into a {@see RasterSurface}.
     */
    public function pdfNativeName(): ?string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Multiply => 'Multiply',
            self::Screen => 'Screen',
            self::Overlay => 'Overlay',
            self::Darken => 'Darken',
            self::Lighten => 'Lighten',
            self::ColorDodge => 'ColorDodge',
            self::ColorBurn => 'ColorBurn',
            self::HardLight => 'HardLight',
            self::SoftLight => 'SoftLight',
            self::Difference => 'Difference',
            self::Exclusion => 'Exclusion',
            self::Hue => 'Hue',
            self::Saturation => 'Saturation',
            self::Color => 'Color',
            self::Luminosity => 'Luminosity',
            self::PlusDarker, self::PlusLighter => null,
        };
    }

    /**
     * True when the translator must rasterise the subtree to apply
     * this mode. Equivalent to `pdfNativeName() === null`.
     */
    public function requiresRaster(): bool
    {
        return $this->pdfNativeName() === null;
    }
}
