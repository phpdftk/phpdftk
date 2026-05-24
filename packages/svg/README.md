# phpdftk/svg

Pure-PHP SVG 2 parser. Produces a typed tree (`SvgDocument`, `SvgPath`, `SvgRect`, etc.) with path-grammar parsing for the `d` attribute and presentation-attribute → typed-property resolution. Optional CSS-inside-SVG via `phpdftk/css`.

Useful outside of phpdftk for sanitizers, format converters, and animation extractors. XML parsing has XXE/XInclude defenses enabled by default.

## Installation

```bash
composer require phpdftk/svg
```

## Status

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Skeleton only; implementation pending.

## License

MIT
