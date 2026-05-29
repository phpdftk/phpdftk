<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG `<style>` per SVG 2 §6.4. The CSS source lives in the element's text
 * children verbatim; `cssText()` concatenates them so callers don't have
 * to walk the child list themselves.
 *
 * The parser deliberately does **not** parse the CSS. That happens in
 * `Phpdftk\Svg\Css\CssBridge` (which depends on `phpdftk/css` — kept
 * optional so the parser stays usable standalone). Sanitiser-style callers
 * can read the raw text without pulling in a CSS parser.
 */
final class StyleElement extends Element
{
    public function __construct()
    {
        parent::__construct('style');
    }

    /**
     * Concatenated CSS text from all `Text` child nodes. Whitespace is
     * preserved exactly as it appeared in the source so source maps and
     * line numbers stay correct.
     */
    public function cssText(): string
    {
        $out = '';
        foreach ($this->children as $child) {
            if ($child instanceof Text) {
                $out .= $child->data;
            }
        }
        return $out;
    }
}
