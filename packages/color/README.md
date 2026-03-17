# phpdftk/color

Color space data models (RGB, CMYK, Gray) and conversion utilities. No PDF dependency — usable in any PHP project.

## Installation

```bash
composer require phpdftk/color
```

## Usage

```php
use Phpdftk\Color\RgbColor;
use Phpdftk\Color\CmykColor;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\ColorConverter;

// Create from 0–255 integers or hex string
$red = RgbColor::fromInt(255, 0, 0);
$blue = RgbColor::fromHex('#0000ff');

// Convert between color spaces
$cmyk = $red->toCmyk();   // CmykColor(0, 1, 1, 0)
$gray = $red->toGray();   // GrayColor(0.299)

// Or use the converter directly
$gray = ColorConverter::rgbToGray($red);
$cmyk = ColorConverter::rgbToCmyk($red);
$rgb  = ColorConverter::cmykToRgb($cmyk);

// Convenience factories
$black = GrayColor::black();
$white = GrayColor::white();
```

## Classes

| Class | Description |
|---|---|
| `RgbColor` | RGB color (0.0–1.0 floats internally); `fromInt()`, `fromHex()`, `toCmyk()`, `toGray()` |
| `CmykColor` | CMYK color (0.0–1.0 floats); `toRgb()`, `toGray()` |
| `GrayColor` | Grayscale (0.0–1.0); `black()`, `white()` factories |
| `ColorConverter` | Static conversion methods between all three color spaces |
| `ColorInterface` | Common interface: `toArray()`, `toCss()` |
