# phpdftk/html

Pure-PHP WHATWG HTML5 parser. Hand-rolled tokenizer + tree-construction state machine targeting 100% of the html5lib-tests suite. Exposes a DOM (Document, Element, Text, Attr, DocumentFragment, ShadowRoot) and a Serializer for round-tripping back to HTML5 syntax.

**Declarative Shadow DOM** (`<template shadowrootmode>`) is supported.

**JavaScript is not.** `<script>` tags are parsed but never executed; event-handler attributes (`onclick`, `onload`, etc.) are preserved as attributes but have no behavior. Use a headless browser if you need scripted DOM.

## Installation

```bash
composer require phpdftk/html
```

## Status

Phase 1B of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Skeleton only; implementation pending.

## License

MIT
