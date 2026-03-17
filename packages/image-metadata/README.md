# phpdftk/image-metadata

Parse image file headers to extract dimensions, color space, and bit depth — without loading the full image into memory. Supports JPEG, PNG, GIF, TIFF, and WebP. No PDF dependency.

## Installation

```bash
composer require phpdftk/image-metadata
```

## Usage

```php
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\ImageMetadata\ImageInfo;

// Auto-detect format from file signature and parse metadata
$info = ImageParser::parseFile('/path/to/image.jpg');
$info = ImageParser::parseData($rawBinaryString);

echo $info->width;       // e.g. 1920
echo $info->height;      // e.g. 1080
echo $info->colorSpace;  // e.g. 'DeviceRGB', 'DeviceCMYK', 'DeviceGray'
echo $info->bitsPerComponent; // e.g. 8
echo $info->format;      // 'jpeg', 'png', 'gif', 'tiff', 'webp'

// Check color space
if ($info->colorSpace === 'DeviceCMYK') {
    // 4-channel CMYK image
}
```

## Supported Formats

| Format | Parser | Details extracted |
|---|---|---|
| JPEG | `JpegParser` | Reads SOF0/SOF1/SOF2 markers for dimensions and component count |
| PNG | `PngParser` | Reads IHDR chunk for dimensions, bit depth, color type |
| GIF | `GifParser` | Reads logical screen descriptor from GIF87a/GIF89a header |
| TIFF | `TiffParser` | Reads IFD tags (256/257/258/277) for dimensions, bits, samples |
| WebP | `WebpParser` | Reads VP8/VP8L/VP8X chunk for dimensions |

## Classes

| Class | Description |
|---|---|
| `ImageParser` | Facade — detects format by magic bytes, delegates to the right parser |
| `ImageInfo` | Value object: `width`, `height`, `colorSpace`, `bitsPerComponent`, `format` |
| `JpegParser` | JPEG SOF marker parser |
| `PngParser` | PNG IHDR chunk parser |
| `GifParser` | GIF logical screen descriptor parser |
| `TiffParser` | TIFF IFD parser (little-endian and big-endian) |
| `WebpParser` | WebP RIFF chunk parser |
