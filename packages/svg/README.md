# phpdftk/svg

Pure-PHP SVG 2 parser. Produces a typed tree (`SvgDocument`, `Shape\Rect`, `GenericElement`, …) with on-demand attribute parsing.

Useful outside of phpdftk for sanitisers, format converters, and animation extractors. XML loading has XXE and XInclude defenses on by default.

## Installation

```bash
composer require phpdftk/svg
```

## Quick example

```php
use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Shape\Rect;

$doc = (new Parser())->parse('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100">
  <rect x="10" y="20" width="30" height="40"/>
</svg>');

echo $doc->widthAttribute();      // "200"
echo $doc->viewBox() ?? 'unset';  // null (no viewBox here)

foreach ($doc->findByTag('rect') as $r) {
    assert($r instanceof Rect);
    [$r->x(), $r->y(), $r->width(), $r->height()];  // [10.0, 20.0, 30.0, 40.0]
}
```

## Security

The parser uses `DOMDocument::loadXML` with `LIBXML_NONET` and explicitly leaves `LIBXML_NOENT` off, so:

* External entities (`<!ENTITY x SYSTEM "file://…">` or `http://…`) are NOT substituted — classic XXE payloads return empty content rather than file or network data.
* No network access during parse (no implicit DTD or entity fetches).
* `XInclude` directives pass through as generic elements; we never invoke `DOMDocument::xinclude()`.

There's a `SecurityTest` suite that asserts these properties — regressions break CI before they ship.

## Status

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Foundation landed (secure XML loader + `SvgDocument` + `Shape\Rect`); typed classes for the other v1 elements (circle, ellipse, line, polyline, polygon, path, g, text, tspan, use, symbol, image, clipPath, mask, defs) plus the `d`-attribute path-grammar parser are next.

## License

MIT
