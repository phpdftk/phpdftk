# phpdftk/filters

Encode and decode PDF stream filters: FlateDecode, ASCII85, ASCIIHex, and RunLength. No PDF dependency — usable for any compression/encoding need.

Requires `ext-zlib` for `FlateFilter`.

## Installation

```bash
composer require phpdftk/filters
```

## Usage

```php
use Phpdftk\Filters\FlateFilter;
use Phpdftk\Filters\Ascii85Filter;
use Phpdftk\Filters\AsciiHexFilter;
use Phpdftk\Filters\RunLengthFilter;

// FlateDecode — zlib deflate/inflate
$flate = new FlateFilter();
$compressed   = $flate->encode('Hello World');
$decompressed = $flate->decode($compressed);

// ASCII85 — binary → printable ASCII (PDF default for embedding streams)
$ascii85 = new Ascii85Filter();
$encoded = $ascii85->encode($binaryData);
$decoded = $ascii85->decode($encoded);

// ASCIIHex — binary → hex digits
$hex = new AsciiHexFilter();
$encoded = $hex->encode($binaryData);   // "48656c6c6f>"
$decoded = $hex->decode($encoded);

// RunLength — simple byte-run compression
$rl = new RunLengthFilter();
$encoded = $rl->encode($data);
$decoded = $rl->decode($encoded);
```

## Classes

| Class | Description |
|---|---|
| `FlateFilter` | zlib compress/decompress via `gzcompress`/`gzuncompress` |
| `Ascii85Filter` | Adobe ASCII-85 encode/decode; output terminated with `~>` |
| `AsciiHexFilter` | Hex encode/decode; output terminated with `>` |
| `RunLengthFilter` | PDF RunLengthDecode encode/decode |
| `FilterInterface` | Common `encode(string): string` / `decode(string): string` interface |
