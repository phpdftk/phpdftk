<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Color 5 §4 — relative color syntax. `rgb(from <color> R G B
 * [/ A])`, `hsl(from <color> H S L [/ A])`, `lab(from ...)`,
 * `lch(from ...)`, `oklab(from ...)`, `oklch(from ...)`,
 * `hwb(from ...)`, `color(from <color> <space> R G B [/ A])`.
 *
 * Inside the function the component identifiers `r`, `g`, `b`
 * (or `h`, `s`, `l`, etc.) refer to the source color's components
 * after conversion to the target color space. The component slots
 * can also be replaced by literal values, percentages, or calc()
 * expressions.
 *
 * Stored as the parsed declaration; the actual conversion runs
 * when the 4E color engine ships.
 */
final readonly class RelativeColor extends Value
{
    public function __construct(
        public ColorSpace $space,
        // Source is a concrete `Color` for literal/named/function-form
        // origins (`rgb(from red …)`), or a `Keyword` for `currentcolor`
        // / `transparent` whose value depends on the using element and
        // must be resolved at paint time. CSS Color 5 §4.
        public Color|Keyword $source,
        public Value $component1,
        public Value $component2,
        public Value $component3,
        public Value $alpha,
    ) {}

    public function toCss(): string
    {
        // We can't perfectly reconstruct the original keyword
        // (rgb vs hsl vs ...) without tracking the syntactic
        // entry point, so emit color(<space> from ...) which is
        // the canonical CSS Color 5 form.
        $alpha = $this->alpha instanceof Number && $this->alpha->value === 1.0
            ? ''
            : ' / ' . $this->alpha->toCss();
        return sprintf(
            'color(from %s %s %s %s %s%s)',
            $this->source->toCss(),
            self::spaceName($this->space),
            $this->component1->toCss(),
            $this->component2->toCss(),
            $this->component3->toCss(),
            $alpha,
        );
    }

    private static function spaceName(ColorSpace $space): string
    {
        return match ($space) {
            ColorSpace::sRGB => 'srgb',
            ColorSpace::sRGBLinear => 'srgb-linear',
            ColorSpace::DisplayP3 => 'display-p3',
            ColorSpace::DisplayP3Linear => 'display-p3-linear',
            ColorSpace::A98RGB => 'a98-rgb',
            ColorSpace::A98RGBLinear => 'a98-rgb-linear',
            ColorSpace::ProPhotoRGB => 'prophoto-rgb',
            ColorSpace::ProPhotoRGBLinear => 'prophoto-rgb-linear',
            ColorSpace::Rec2020 => 'rec2020',
            ColorSpace::Rec2020Linear => 'rec2020-linear',
            ColorSpace::Lab => 'lab',
            ColorSpace::Lch => 'lch',
            ColorSpace::OKLab => 'oklab',
            ColorSpace::OKLCH => 'oklch',
            ColorSpace::XYZ, ColorSpace::XYZD65 => 'xyz-d65',
            ColorSpace::XYZD50 => 'xyz-d50',
            ColorSpace::HWB => 'hwb',
        };
    }
}
