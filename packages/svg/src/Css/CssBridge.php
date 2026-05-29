<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Css;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Selector\SelectorParser;
use Phpdftk\Css\Sheet\Declaration;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Sheet\StyleRule;
use Phpdftk\Css\Sheet\Stylesheet;
use Phpdftk\Css\ValueParser;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\StyleElement;
use Phpdftk\Svg\SvgDocument;

/**
 * Bridge between an SVG element tree and the `phpdftk/css` cascade.
 *
 * `computeStyle()` resolves the effective CSS values for one element by
 * layering, in increasing precedence:
 *
 *  1. SVG presentation attributes — author origin, specificity 0,0,0,0
 *     per SVG 2 §6.7. Synthesised into a one-rule stylesheet with a `*`
 *     selector so it matches but doesn't out-rank anything.
 *  2. `<style>` element CSS from the document — author origin, normal
 *     selector specificity per Selectors 4.
 *  3. The element's inline `style=""` attribute — author origin,
 *     specificity `(1024, 0, 0)` per CSS Cascade 5 §6.4.4. The `Cascade`
 *     reads this directly from `MatchableElement::getAttributeValue`
 *     so we don't need a manual overlay.
 *
 * The bridge depends on `phpdftk/css`. It lives in its own namespace so
 * loading `Phpdftk\Svg\Element` doesn't transitively pull the CSS
 * dependency — callers who don't need the cascade keep working without
 * `phpdftk/css` installed.
 */
final class CssBridge
{
    /**
     * The subset of SVG presentation attributes we synthesise into CSS
     * declarations. Matches the typed accessors landed in 3E and 3F; the
     * painter is free to extend the list when it adds support for more.
     *
     * @var list<string>
     */
    private const array PRESENTATION_ATTRIBUTES = [
        'fill', 'stroke',
        'fill-opacity', 'stroke-opacity', 'opacity',
        'fill-rule',
        'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset',
        'font-family', 'font-size', 'font-weight', 'font-style',
        'color', 'display', 'visibility',
    ];

    public function __construct(
        private readonly Cascade $cascade = new Cascade(),
        private readonly CssParser $cssParser = new CssParser(),
        private readonly ValueParser $valueParser = new ValueParser(),
    ) {}

    /**
     * Compute the cascade for `$element`. `$parentValues` is the
     * already-computed result for the element's parent — pass `null` for
     * the root.
     */
    public function computeStyle(
        Element $element,
        SvgDocument $document,
        ?CascadedValues $parentValues = null,
    ): CascadedValues {
        $sheets = [
            $this->presentationAttributeSheet($element),
            ...$this->collectAuthorSheets($document),
        ];
        return $this->cascade->computeFor(
            $sheets,
            new MatchableSvgElement($element),
            $parentValues,
        );
    }

    /**
     * Walk the document and parse every `<style>` element's body into a
     * Stylesheet. Returned in document order so later sheets shadow
     * earlier ones on specificity ties, matching how browsers resolve
     * multiple `<style>` blocks.
     *
     * @return list<Stylesheet>
     */
    public function collectAuthorSheets(SvgDocument $document): array
    {
        $sheets = [];
        foreach ($document->findByTag('style') as $node) {
            if (!$node instanceof StyleElement) {
                continue;
            }
            $css = $node->cssText();
            if (trim($css) === '') {
                continue;
            }
            $sheets[] = $this->cssParser->parseStylesheet($css, Origin::Author);
        }
        return $sheets;
    }

    /**
     * Build a single-rule Stylesheet whose declarations are the
     * presentation attributes carried by `$element`. The rule's selector
     * is `*` so it matches the element (and would match every other
     * element too, but `computeStyle` only ever passes this sheet for
     * one specific element).
     */
    public function presentationAttributeSheet(Element $element): Stylesheet
    {
        $declarations = [];
        foreach (self::PRESENTATION_ATTRIBUTES as $name) {
            $raw = $element->getAttribute($name);
            if ($raw === null || trim($raw) === '') {
                continue;
            }
            try {
                $value = $this->valueParser->parseFromString($raw);
            } catch (\Throwable) {
                continue;
            }
            $declarations[] = new Declaration($name, $value, important: false);
        }
        if ($declarations === []) {
            return new Stylesheet([], Origin::Author);
        }
        $rule = new StyleRule(SelectorParser::parse('*'), $declarations);
        return new Stylesheet([$rule], Origin::Author);
    }
}
