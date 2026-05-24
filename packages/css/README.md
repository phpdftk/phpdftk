# phpdftk/css

Pure-PHP CSS implementation. Tokenizes per CSS Syntax Module 3, parses values per CSS Values and Units Module 4, matches selectors per CSS Selectors Module 4, runs the cascade per CSS Cascade and Inheritance Module 5. No external dependencies beyond `phpdftk/color` and `phpdftk/geometry`.

Designed as substrate for `phpdftk/html-to-pdf` and `phpdftk/svg-to-pdf`, but useful standalone for any PHP code that needs to parse stylesheets, match selectors, or compute styles against a DOM.

## Installation

```bash
composer require phpdftk/css
```

## Status

Phase 1A and 1D of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Skeleton only; implementation pending.

## License

MIT
