<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Text;

/**
 * Seam that lets an embedding document hand its own already-registered
 * fonts to SVG text painting.
 *
 * Standalone SVG rendering has no font machinery beyond the 14 standard
 * PDF fonts (see {@see FontResolver}). But when an SVG is embedded in an
 * HTML document — inline `<svg>`, or an SVG `background-image` — that
 * document has already parsed its `@font-face` rules, matched families
 * per CSS Fonts 4 §6, and embedded the chosen faces in the PDF. SVG text
 * inherits the same `font-family` cascade as the surrounding HTML
 * (SVG 2 §11.5 defers font selection wholesale to CSS Fonts), so it must
 * resolve to the same face — otherwise `<div>Hi</div>` and
 * `<svg><text>Hi</text></svg>` in one document render in different
 * typefaces.
 *
 * Implementations return `null` when they cannot satisfy the request;
 * the translator then falls back to its built-in standard-14 resolver.
 */
interface DocumentFontProvider
{
    /**
     * @param list<string> $families Ordered `font-family` list, as
     *                               authored (unquoted, untrimmed of case).
     * @param string|null  $weight   Raw CSS `font-weight` (`bold`, `600`, …).
     * @param string|null  $style    Raw CSS `font-style` (`italic`, …).
     */
    public function resolveDocumentFont(array $families, ?string $weight, ?string $style): ?DocumentFont;
}
