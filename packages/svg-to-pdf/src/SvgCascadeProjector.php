<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Svg\Css\CssBridge;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\SvgDocument;

/**
 * Project author CSS (cascaded values from `<style>` blocks inside
 * the SVG) into each element's inline `style` attribute so the
 * existing `Element::presentationOrStyle()` fallback picks them up
 * during paint.
 *
 * Why a projector and not direct cascade reads at paint time:
 *
 *   - The painter is shape-by-shape with deeply tree-recursive
 *     paths. Re-resolving the cascade at every accessor call would
 *     dominate the render budget.
 *   - The projection runs once per SvgDocument before paint, so
 *     each element only pays the cascade cost once regardless of
 *     how many accessors read it.
 *   - The projection writes back to the same `style` attribute
 *     that author-supplied inline declarations live in, so
 *     accessors don't need to know whether a value came from
 *     `<rect style="...">` or `<style>.s { ... }`. Same code path.
 *
 * Per-property allowlist:
 *
 *   The projection only touches properties the painter actually
 *   reads through {@see Element::presentationOrStyle()}.
 *   Projecting the full computed-style set would inject browser-
 *   computed defaults (`stroke: none` everywhere, etc.) and shift
 *   the paint behaviour for properties our renderer hasn't wired
 *   yet, which would regress unrelated fixtures.
 *
 *   Adding a new accessor means adding the property here too.
 */
final class SvgCascadeProjector
{
    /**
     * Properties projected into inline `style`. Add to this list
     * when a new {@see Element} accessor starts reading via
     * `presentationOrStyle`.
     */
    private const array PROJECTED = [
        'fill',
        'stroke',
        // Inherited, and the referent of `currentColor`. Projected on
        // the loose `has()` test precisely BECAUSE it inherits: the
        // declaration usually sits on an ancestor, so `wasDeclared()`
        // on this element would be false.
        'color',
        'fill-rule',
        'stroke-width',
        'stroke-linecap',
        'stroke-linejoin',
        'stroke-miterlimit',
        'stroke-dasharray',
        'stroke-dashoffset',
        // SVG 2 §9.6 — `path-length` is the CSS spelling of the
        // `pathLength` presentation attribute. Projected like any
        // other property so a stylesheet rule out-ranks the
        // attribute, which sits at specificity 0 per §6.7.
        'path-length',
        'fill-opacity',
        'stroke-opacity',
        'opacity',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'clip-path',
        'mask',
        'stop-color',
        'stop-opacity',
        'text-shadow',
    ];

    /**
     * SVG 2 §10.1 geometry properties. Split out from
     * {@see PROJECTED} because they are projected on a STRICTER test:
     * only when a declaration actually won the cascade
     * (`wasDeclared()`), never from an inherited or initial value.
     *
     * `width` / `height` are registered CSS properties whose initial
     * value is `auto`; projecting those on the looser `has()` test
     * would stamp `width: auto` onto shapes the author never sized and
     * change what the painter reads.
     */
    private const array PROJECTED_GEOMETRY = [
        'x',
        'y',
        'width',
        'height',
        'cx',
        'cy',
        'r',
        'rx',
        'ry',
    ];

    /**
     * CSS Transforms 1 — `transform`, `transform-origin` and
     * `transform-box` are ordinary CSS properties on SVG elements, so a
     * `<style>` rule has to reach the painter the same way a
     * presentation attribute does.
     *
     * Projected on the STRICT `wasDeclared()` test, like the geometry
     * properties and for the same reason: all three are registered with
     * non-empty initial values, and `transform-origin`'s registered
     * initial is the CSS box default `50% 50%`. An SVG element has no
     * CSS layout box and pivots on the user-space origin instead, so
     * stamping the registry initial onto every element would silently
     * relocate every rotation and scale in the document.
     *
     * @var list<string>
     */
    private const array PROJECTED_TRANSFORM = [
        'transform',
        'transform-origin',
        'transform-box',
    ];

    /**
     * Properties whose CSS declaration OUT-RANKS the presentation
     * attribute of the same name, so the projection must run even when
     * the element carries that attribute.
     *
     * Only `d` today (SVG 2 §9.3). The accessor reads the `style`
     * declaration first and the attribute second, which is what makes
     * the ordering work; every other property in this file relies on
     * the reverse.
     *
     * @var list<string>
     */
    private const array PROJECTED_OVER_ATTRIBUTE = [
        'd',
    ];

    public function __construct(
        private readonly CssBridge $bridge = new CssBridge(),
    ) {}

    /**
     * Walk `$document` and project the cascade into every element's
     * `style` attribute. Mutates the document in place. Safe to
     * call more than once - the second pass overwrites the
     * previous projection (it's idempotent for the same document
     * state).
     */
    public function project(SvgDocument $document): void
    {
        $this->walk($document, null);
    }

    /**
     * Recursive walker. Each call computes the cascade for
     * `$element` (using `$parentValues` for inheritance), writes
     * the relevant properties back as a `style` attribute
     * declaration, then recurses into children with the new
     * cascade as their parent values.
     */
    private function walk(Element $element, ?CascadedValues $parentValues): void
    {
        $document = $element instanceof SvgDocument
            ? $element
            : $this->findDocument($element);
        if ($document === null) {
            return;
        }
        $values = $this->bridge->computeStyle(
            $element,
            $document,
            $parentValues,
        );

        $declarations = [];
        foreach ([...self::PROJECTED_GEOMETRY, ...self::PROJECTED_TRANSFORM] as $property) {
            // A presentation attribute on the element is the author's
            // own value and still reaches the painter first, so leave
            // it alone. Otherwise project only a value a declaration
            // won — see PROJECTED_GEOMETRY / PROJECTED_TRANSFORM.
            if ($element->getAttribute($property) !== null) {
                continue;
            }
            if (!$values->wasDeclared($property)) {
                continue;
            }
            $value = $values->get($property);
            if ($value === null) {
                continue;
            }
            $declarations[] = $property . ': ' . $value->toCss();
        }
        foreach (self::PROJECTED_OVER_ATTRIBUTE as $property) {
            if (!$values->wasDeclared($property)) {
                continue;
            }
            $value = $values->get($property);
            if ($value === null) {
                continue;
            }
            $declarations[] = $property . ': ' . $value->toCss();
        }
        foreach (self::PROJECTED as $property) {
            // The author intent on the element itself (presentation
            // attribute, inline style) already feeds the painter
            // through `presentationOrStyle`. Skip properties where
            // the element has its own source so we never overwrite
            // the author's per-element value with the cascaded one.
            if ($element->getAttribute($property) !== null) {
                continue;
            }
            if (!$values->has($property)) {
                continue;
            }
            $value = $values->get($property);
            if ($value === null) {
                continue;
            }
            $declarations[] = $property . ': ' . $value->toCss();
        }

        if ($declarations !== []) {
            $existing = $element->getAttribute('style') ?? '';
            $projection = '/* svg-cascade-projector */ '
                . implode('; ', $declarations);
            $element->setAttribute(
                'style',
                $existing === ''
                    ? $projection
                    : $existing . '; ' . $projection,
            );
        }

        foreach ($element->children as $child) {
            if ($child instanceof Element) {
                $this->walk($child, $values);
            }
        }
    }

    /**
     * Find the {@see SvgDocument} root for `$element` by walking
     * up parent links. Falls back to null when the element is
     * detached - we skip projection on those.
     */
    private function findDocument(Element $element): ?SvgDocument
    {
        $node = $element;
        while ($node !== null) {
            if ($node instanceof SvgDocument) {
                return $node;
            }
            $node = $node->parent ?? null;
        }
        return null;
    }
}
