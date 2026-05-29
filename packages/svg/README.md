# phpdftk/svg

Pure-PHP SVG 2 parser. Produces a typed tree (`SvgDocument`, `Group`, `Path`, `Shape\Rect`, `Shape\Circle`, `Shape\Ellipse`, `Shape\Line`, `Shape\Polyline`, `Shape\Polygon`, `GenericElement`, …) with on-demand attribute parsing, including the `transform` attribute (`Value\Transform`) and the `d` path-data grammar (`Path\PathData`).

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

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: secure XML loader, `SvgDocument`, `Group`, all five basic shapes, the `transform` attribute (`Value\Transform`), and `<path>` with the full SVG 2 §9.3.9 `d`-grammar (M/L/H/V/C/S/Q/T/A/Z, implicit lineto, single-digit arc flags). Next: presentation attributes + paint values, `<text>`/`<tspan>`, `<defs>`/`<use>`/`<symbol>`, `<clipPath>`/`<mask>`/`<image>`, gradients, and CSS-inside-SVG.

## License

MIT
