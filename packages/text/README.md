# phpdftk/text

Text shaping primitives for layout engines: UAX #14 line breaking, UAX #9 bidirectional algorithm, OpenType GSUB/GPOS shaping. Returns positioned glyph runs ready for a graphics painter.

Requires `ext-intl` for UAX-conformant line breaking and bidi. Consumes `phpdftk/font-parser` for OpenType table access.

## Installation

```bash
composer require phpdftk/text
```

## Status

Phase 1C of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Skeleton only; implementation pending.

## License

MIT
