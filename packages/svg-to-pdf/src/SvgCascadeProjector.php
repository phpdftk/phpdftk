<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Svg\Css\CssBridge;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\GenericElement;
use Phpdftk\Svg\Node;
use Phpdftk\Svg\Use_;
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
        // SVG 2 §8.2 — the UA stylesheet sets `overflow: hidden` on
        // viewport elements, so the painter's "no value" branch CLIPS.
        // CSS's registered initial is `visible`, and projecting that
        // onto every element would switch clipping off document-wide;
        // hence the strict `wasDeclared()` test, same as the geometry
        // properties.
        'overflow',
        // SVG 2 §8.9 — `display: none` removes an element from the
        // rendering tree. Declared-only for the same reason: `display`
        // has a non-empty registered initial, and the painter only
        // cares whether a declaration said `none`.
        'display',
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

    /**
     * Marker attribute on a materialised `<use>` instance. Spelled out
     * rather than imported from `html-to-pdf`, which writes the same
     * one: the dependency runs the other way, and the painter already
     * reads this literal.
     */
    public const string USE_INSTANCE_ATTRIBUTE = 'data-phpdftk-use-instance';

    /** Nesting cap on `<use>` -> `<use>` expansion. */
    private const int MAX_USE_DEPTH = 8;

    /**
     * Total element budget for shadow-tree cloning, so a document that
     * fans out exponentially through nested `<use>` can't exhaust
     * memory.
     */
    private const int USE_NODE_BUDGET = 20000;

    private int $useBudget = self::USE_NODE_BUDGET;

    /**
     * Clone per `<use>`, built in a first pass BEFORE anything is
     * styled — a clone taken after the referenced subtree had been
     * projected would carry that projection as its own inline style
     * and out-rank what it should inherit from the `<use>`.
     *
     * @var \SplObjectStorage<Element, Element>|null
     */
    private ?\SplObjectStorage $useShadowTrees = null;

    /**
     * Referents currently being expanded, so `<use>` -> `<use>` cycles
     * terminate.
     *
     * @var \SplObjectStorage<Element, true>|null
     */
    private ?\SplObjectStorage $useChain = null;

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
        // SVG 2 §5.6 — PHASE 1: clone every `<use>` referent while the
        // tree is still unstyled. The clones stay DETACHED until the
        // walk reaches their `<use>`, which is what buys the shadow
        // boundary for free: a document-tree selector like
        // `.container rect` cannot match inside a subtree whose root
        // has no parent, while a bare `rect` rule still does — exactly
        // the split §5.6 and Selectors 4 describe.
        $this->useBudget = self::USE_NODE_BUDGET;
        $this->useShadowTrees = new \SplObjectStorage();
        $this->useChain = new \SplObjectStorage();
        $this->collectUseShadowTrees($document, $document, 0);
        $this->useChain = null;
        try {
            $this->walk($document, null);
        } finally {
            $this->useShadowTrees = null;
        }
    }

    /**
     * PHASE 1 — build the clone for every `<use>` in `$scope`.
     */
    private function collectUseShadowTrees(Element $scope, SvgDocument $document, int $depth): void
    {
        if ($depth >= self::MAX_USE_DEPTH) {
            return;
        }
        foreach ($scope->children as $child) {
            if (!$child instanceof Element) {
                continue;
            }
            $this->collectUseShadowTrees($child, $document, $depth);
            if (!$child instanceof Use_) {
                continue;
            }
            $referent = $this->useReferentToClone($child, $document);
            if ($referent === null) {
                continue;
            }
            $this->useBudget -= self::countElements($referent);
            $clone = self::cloneElement($referent);
            $clone->setAttribute(self::USE_INSTANCE_ATTRIBUTE, '1');
            self::stripIds($clone);
            // The clone's parent is a synthetic SHADOW ROOT rather than
            // nothing at all. Leaving it parentless would make it match
            // `:root` — and WPT's struct/reftests/use-inheritance-001
            // pins that it must not ("it is considered to have no
            // parent, but it is not a root element"). The placeholder's
            // local name is deliberately unspellable as a CSS type
            // selector, so no document rule can reach through it, which
            // is the boundary §5.6 asks for.
            $shadowRoot = new GenericElement('#shadow-root');
            $shadowRoot->appendChild($clone);
            $this->useShadowTrees?->attach($child, $clone);
            // Held only for the nested collect, so a cycle back through
            // this referent stops here while a later, legitimate second
            // reference still gets its own instance.
            $this->useChain?->attach($referent, true);
            $this->collectUseShadowTrees($clone, $document, $depth + 1);
            $this->useChain?->detach($referent);
        }
    }

    /**
     * The element a `<use>` should clone, or null when the reference is
     * missing, external, circular, or over budget.
     */
    private function useReferentToClone(Use_ $use, SvgDocument $document): ?Element
    {
        if ($this->useBudget <= 0) {
            return null;
        }
        // Idempotent: a document projected twice must not grow a second
        // instance under the same `<use>`.
        foreach ($use->children as $existing) {
            if ($existing instanceof Element
                && $existing->getAttribute(self::USE_INSTANCE_ATTRIBUTE) !== null
            ) {
                return null;
            }
        }
        $referent = $use->resolve($document);
        if ($referent === null || $referent === $use) {
            return null;
        }
        if ($this->useChain?->contains($referent) ?? false) {
            return null;
        }
        // SVG 2 §5.6.2 — referencing an ancestor of the `<use>` is a
        // circular reference and the element is not rendered.
        for ($n = $use->parent; $n !== null; $n = $n->parent) {
            if ($n === $referent) {
                return null;
            }
        }
        return $referent;
    }

    /** Deep copy of an element subtree, detached from its parent. */
    private static function cloneElement(Element $element): Element
    {
        $copy = clone $element;
        $copy->parent = null;
        $children = $copy->children;
        $copy->children = [];
        foreach ($children as $child) {
            $copy->appendChild(self::cloneNode($child));
        }
        return $copy;
    }

    /** Deep copy of any node, detached from its parent. */
    private static function cloneNode(Node $node): Node
    {
        if ($node instanceof Element) {
            return self::cloneElement($node);
        }
        $copy = clone $node;
        $copy->parent = null;
        return $copy;
    }

    /**
     * SVG 2 §5.6 — a shadow-tree node is not addressable by id from the
     * document. Leaving the ids on would let a later `url(#…)` — or the
     * painter's own `href` resolution — land on the CLONE instead of
     * the original, which is exactly what happens when the `<use>` is
     * written before the `<defs>` it references.
     */
    private static function stripIds(Element $element): void
    {
        unset($element->attributes['id']);
        foreach ($element->children as $child) {
            if ($child instanceof Element) {
                self::stripIds($child);
            }
        }
    }

    private static function countElements(Element $element): int
    {
        $count = 1;
        foreach ($element->children as $child) {
            if ($child instanceof Element) {
                $count += self::countElements($child);
            }
        }
        return $count;
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
        $this->walkFrom($element, $parentValues, $document);
    }

    /**
     * The recursive half of {@see walk()}, with the document handed in.
     *
     * Split out because a `<use>`'s shadow-tree clone is projected
     * while still DETACHED — that is what gives it a shadow boundary —
     * and `walk()` finds its document by following parent links the
     * clone does not have.
     */
    private function walkFrom(
        Element $element,
        ?CascadedValues $parentValues,
        SvgDocument $document,
    ): void {
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
            // the author's per-element value with the cascaded one —
            // UNLESS that source is a CSS-wide keyword, which is not a
            // value at all but an instruction to the cascade. The
            // painter can do nothing with the literal string
            // `inherit`, so `fill="inherit"` used to fall through to
            // the black default; the resolved value has to come from
            // here.
            $own = $element->getAttribute($property);
            if ($own !== null && !Element::isCssWideKeyword($own)) {
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
                $this->walkFrom($child, $values, $document);
            }
        }

        // SVG 2 §5.6 PHASE 2 — style this `<use>`'s clone with the
        // `<use>`'s own cascade as its parent, which is what finally
        // makes `<use fill="green">` reach the shapes inside the
        // referent, then attach it. Done AFTER the element's own
        // children are walked so the instance isn't walked twice, and
        // while the clone is still detached so document-tree selectors
        // can't reach into it.
        if ($element instanceof Use_ && ($this->useShadowTrees?->contains($element) ?? false)) {
            $clone = $this->useShadowTrees[$element];
            $this->walkFrom($clone, $values, $document);
            $element->appendChild($clone);
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
