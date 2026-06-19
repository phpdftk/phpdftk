<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Parser;
use Phpdftk\Css\Selector\MatchableElement;
use Phpdftk\Css\Selector\Matcher;
use Phpdftk\Css\Selector\Specificity;
use Phpdftk\Css\Sheet\Declaration;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Sheet\StyleRule;
use Phpdftk\Css\Sheet\Stylesheet;
use Phpdftk\Css\Value\CustomProperty;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\ValueList;

/**
 * CSS Cascade 5 + inheritance implementation. Given a set of stylesheets
 * (with `Origin` tags) and a `MatchableElement`, produces a `CascadedValues`
 * containing each property's resolved value.
 *
 * Cascade order, per CSS Cascade 5 §6:
 *  1. Origin × Importance: !important UA > !important User > !important
 *     Author > Animation > Author > User > UA > rolled-in transitions
 *  2. Specificity (a, b, c)
 *  3. Source order (later wins)
 *
 * Inheritance per §7: properties marked `inherits=true` in the registry
 * fall back to the parent element's cascaded value when the cascade
 * produces no declaration for the property on this element.
 *
 * Phase 1D.3 ships the structural cascade. Custom-property substitution
 * (`var()`) and shadow-scoped matching arrive in 1D.4 / 1D.5. The
 * `inherit` / `initial` / `unset` / `revert` keywords are honoured here.
 */
final class Cascade
{
    /**
     * Cascade-tier numbering per CSS Cascade 5 §6. Higher number wins.
     *
     *  0: UA normal       — lowest
     *  1: User normal
     *  2: Author normal
     *  3: (animations — reserved for Phase 2)
     *  4: Author !important
     *  5: User !important
     *  6: UA !important   — highest
     */
    private static function tierFor(Origin $origin, bool $important): int
    {
        if ($important) {
            return match ($origin) {
                Origin::UserAgent => 6,
                Origin::User => 5,
                Origin::Author => 4,
            };
        }
        return match ($origin) {
            Origin::UserAgent => 0,
            Origin::User => 1,
            Origin::Author => 2,
        };
    }

    public function __construct(
        public readonly PropertyRegistry $registry = new PropertyRegistry(),
        private readonly Matcher $matcher = new Matcher(),
        private readonly ShorthandExpander $shorthands = new ShorthandExpander(),
        private readonly Parser $parser = new Parser(),
        /**
         * Viewport width in CSS pixels, used to evaluate `@media`
         * feature queries (`(min-width: N)`, `(max-width: N)`, etc).
         * Null = unknown (feature queries treated as matching so
         * print stylesheets that gate on width never silently drop).
         */
        private readonly ?float $viewportWidth = null,
        private readonly ?float $viewportHeight = null,
        /**
         * Media types that match this rendering context. Defaults to
         * `print` for the PDF-output target; the WPT harness and other
         * "browser-like" embedders can pass `screen` to match the
         * countless tests that gate on `@media screen and (…)`. Any
         * type IN this set (plus the universal `all`) matches.
         *
         * @var list<string>
         */
        private readonly array $matchingMediaTypes = ['print'],
    ) {
        $this->expandedCache = new \WeakMap();
        $this->selPseudoCache = new \WeakMap();
    }

    /**
     * Per-Declaration shorthand-expansion cache. Same Declaration
     * applied against many elements only pays the expansion cost
     * once. WeakMap so stylesheets can be GC'd cleanly.
     *
     * Value: `list<array{0: string, 1: \Phpdftk\Css\Value\Value}>`
     *
     * @var \WeakMap<object, mixed>
     */
    private \WeakMap $expandedCache;

    /**
     * Per-ComplexSelector pseudo-element-name cache. The same
     * selector applied against many elements only walks its
     * compounds once.
     *
     * Value: `array{0: ?string}` (tuple so we can distinguish
     * "cached null" from "missing").
     *
     * @var \WeakMap<object, mixed>
     */
    private \WeakMap $selPseudoCache;

    /**
     * Layer name → declaration-order index. Populated lazily per
     * `computeFor` call as we descend into `@layer` blocks. Named
     * layers reuse the same index across all of their occurrences,
     * anonymous blocks each get a fresh index.
     *
     * Per CSS Cascade 5 §5.3.1 — for normal author declarations, a
     * higher index (later-declared) wins; unlayered declarations
     * (index `null` on a candidate) outrank all layered.
     *
     * @var array<string, int>
     */
    private array $layerIndices = [];
    private int $nextLayerIndex = 0;

    /**
     * Expand a declaration's shorthand, memoised on the Declaration
     * object itself. Returns `[longhandName, expandedValue]` tuples
     * so the cascade can iterate without per-call array allocation.
     *
     * @return list<array{string, \Phpdftk\Css\Value\Value}>
     */
    private function expandDeclaration(\Phpdftk\Css\Sheet\Declaration $decl): array
    {
        if (isset($this->expandedCache[$decl])) {
            return $this->expandedCache[$decl];
        }
        $pairs = [];
        // CSS Cascade 5 §3.2 — the `all` shorthand applies its value
        // to EVERY CSS property except `direction` and `unicode-bidi`
        // (which deal with text direction and aren't reset). The
        // value must be a CSS-wide keyword: `initial` / `inherit` /
        // `unset` / `revert` / `revert-layer`. Fan out the
        // declaration so each property cascades on its own.
        if (strtolower($decl->property) === 'all') {
            foreach ($this->registry->all() as $propName => $_def) {
                if ($propName === 'direction' || $propName === 'unicode-bidi') {
                    continue;
                }
                $pairs[] = [$propName, $decl->value];
            }
            $this->expandedCache[$decl] = $pairs;
            return $pairs;
        }
        foreach ($this->shorthands->expand($decl->property, $decl->value) as $longhand => $value) {
            $pairs[] = [$longhand, $value];
        }
        $this->expandedCache[$decl] = $pairs;
        return $pairs;
    }

    /**
     * Return a Cascade configured for a specific viewport so that
     * `@media (min-width: N)`-style feature queries can evaluate.
     * The other dependencies are inherited from this instance.
     */
    public function withViewport(float $width, float $height): self
    {
        return new self(
            $this->registry,
            $this->matcher,
            $this->shorthands,
            $this->parser,
            $width,
            $height,
            $this->matchingMediaTypes,
        );
    }

    /**
     * Return a Cascade configured to match additional media types in
     * `@media` queries — useful when the embedder isn't a pure print
     * target. Pass `['print', 'screen']` to also honour author CSS
     * gated on `@media screen` (the assumed default for browser-
     * targeted WPT tests).
     *
     * @param list<string> $types
     */
    public function withMatchingMediaTypes(array $types): self
    {
        return new self(
            $this->registry,
            $this->matcher,
            $this->shorthands,
            $this->parser,
            $this->viewportWidth,
            $this->viewportHeight,
            $types,
        );
    }

    /**
     * Build the cascaded-values bag for an anonymous box (CSS Display 3
     * §3.4). The box has no element of its own, so it has no author
     * rules to match. Per spec the box takes the parent's *inherited*
     * properties (font, color, line-height, …) and leaves every
     * non-inherited property at its registry-defined initial value
     * (so e.g. `background-color`, `width`, `height`, `border-*`,
     * `padding-*`, `margin-*` come out at their initial values
     * regardless of what the parent declared).
     *
     * Custom properties always inherit (CSS Custom Properties §3) and
     * are copied straight across.
     */
    public function anonymousFromParent(?CascadedValues $parentValues): CascadedValues
    {
        $values = new CascadedValues($this->registry);
        $this->applyInheritance($values, $parentValues);
        $this->inheritCustomProperties($values, $parentValues);
        return $values;
    }

    /**
     * Run the cascade for one element. `$parentValues` is the already-
     * computed result for the element's parent — used for inheritance.
     * Pass `null` for the root element.
     *
     * @param list<Stylesheet> $sheets
     */
    public function computeFor(
        array $sheets,
        MatchableElement $element,
        ?CascadedValues $parentValues = null,
        ?string $pseudoElement = null,
    ): CascadedValues {
        // 1. Collect every (declaration, specificity, origin, source-order)
        //    tuple for declarations that match this element. When
        //    `$pseudoElement` is set (e.g. "before" / "after"), only rules
        //    whose selector ends in `::$pseudoElement` are included; when
        //    null, the inverse — rules ending in any pseudo-element are
        //    excluded so the host's cascade doesn't pick up content meant
        //    for a generated box.
        // Per-call layer-state reset so two sequential computeFor
        // calls don't accumulate stale layer indices.
        $this->layerIndices = [];
        $this->nextLayerIndex = 0;
        $candidates = [];
        $order = 0;
        foreach ($sheets as $sheet) {
            foreach ($this->activeStyleRules($sheet->rules) as [$rule, $layerIndex]) {
                $matchedSpec = null;
                foreach ($rule->selectors->selectors as $sel) {
                    $selPseudo = $this->selectorPseudoElementName($sel);
                    if ($pseudoElement === null) {
                        if ($selPseudo !== null) {
                            continue;
                        }
                    } else {
                        if ($selPseudo !== $pseudoElement) {
                            continue;
                        }
                    }
                    if (!$this->matcher->complexMatches($sel, $element)) {
                        continue;
                    }
                    $spec = $sel->specificity();
                    if ($matchedSpec === null || $spec->compare($matchedSpec) > 0) {
                        $matchedSpec = $spec;
                    }
                }
                if ($matchedSpec === null) {
                    continue;
                }
                foreach ($rule->declarations as $decl) {
                    foreach ($this->expandDeclaration($decl) as [$longhand, $value]) {
                        // Reuse the original Declaration when the
                        // longhand is unchanged (the common case for
                        // non-shorthand properties), skipping the
                        // per-cascade allocation. Same Specificity
                        // and origin can also be shared.
                        $decl2 = ($longhand === $decl->property)
                            ? $decl
                            : new Declaration($longhand, $value, $decl->important);
                        $candidates[] = [
                            'declaration' => $decl2,
                            'specificity' => $matchedSpec,
                            'origin' => $sheet->origin,
                            'layerIndex' => $layerIndex,
                            'order' => $order++,
                        ];
                    }
                }
            }
        }

        // 1b. HTML `style="…"` attribute declarations cascade as author rules
        // with elevated specificity per CSS Cascade 5 §6.4.4 — they beat any
        // realistic selector. Use Specificity(1024, 0, 0) so authors aren't
        // hitting a tie against id-laden selectors in practice. Pseudo-
        // elements never inherit inline style — `style="..."` always targets
        // the host element.
        $inlineCss = $pseudoElement === null
            ? $element->getAttributeValue('style')
            : null;
        if ($inlineCss !== null && $inlineCss !== '') {
            $inlineSpec = new Specificity(1024, 0, 0);
            $inlineRule = $this->parser->parseInlineStyle($inlineCss);
            foreach ($inlineRule->declarations as $decl) {
                foreach ($this->expandDeclaration($decl) as [$longhand, $value]) {
                    $decl2 = ($longhand === $decl->property)
                        ? $decl
                        : new Declaration($longhand, $value, $decl->important);
                    $candidates[] = [
                        'declaration' => $decl2,
                        'specificity' => $inlineSpec,
                        'origin' => Origin::Author,
                        'layerIndex' => null,
                        'order' => $order++,
                    ];
                }
            }
        }

        // 2. Group candidates by property and pick the cascade winner.
        // We need the full per-property candidate list (not just a
        // running maximum) so `revert-layer` can re-resolve against
        // lower-priority declarations after excluding the winner's
        // layer.
        /** @var array<string, list<int>> $byProperty index into $candidates */
        $byProperty = [];
        foreach ($candidates as $idx => $c) {
            $byProperty[$c['declaration']->property][] = $idx;
        }

        // 3. Materialise CascadedValues, then apply inheritance.
        $result = new CascadedValues($this->registry);
        foreach ($byProperty as $name => $indices) {
            $winner = $this->pickCascadeWinner($name, $indices, $candidates);
            if ($winner === null) {
                continue;
            }
            $value = $this->resolveSpecialKeywords(
                $name,
                $winner['declaration']->value,
                $parentValues,
            );
            if ($value !== null) {
                $result->set($name, $value);
            }
        }
        $this->applyInheritance($result, $parentValues);
        $this->inheritCustomProperties($result, $parentValues);
        $this->substituteCustomProperties($result);
        // CSS Color 5 §5 — `light-dark(<light>, <dark>)` is resolved
        // at COMPUTED-VALUE time using THIS element's `color-scheme`,
        // so it inherits as the chosen arm (not the symbolic
        // expression). Without this pass an inner element's
        // `color-scheme` would re-evaluate the parent's `light-dark()`
        // value, contradicting WPT light-dark-inheritance.
        $this->resolveLightDarkValues($result);
        $this->applyFontSizeAdjustZero($result);
        return $result;
    }

    /**
     * CSS Fonts 4 §5 — `font-size-adjust: 0` makes the used font-size
     * 0px regardless of the cascaded `font-size`. We don't compute
     * the full x-height-ratio remap, but the zero special case
     * (which intentionally hides text by collapsing its used size)
     * is handled directly so WPT font-size-adjust-005 / -014 pass.
     */
    private function applyFontSizeAdjustZero(CascadedValues $values): void
    {
        $adjust = $values->get('font-size-adjust');
        $isZero = false;
        if ($adjust instanceof \Phpdftk\Css\Value\Number) {
            $isZero = abs($adjust->value) < 1e-9;
        } elseif ($adjust instanceof \Phpdftk\Css\Value\Integer) {
            $isZero = $adjust->value === 0;
        }
        if (!$isZero) {
            return;
        }
        $values->set(
            'font-size',
            new Length(0.0, LengthUnit::Px),
        );
    }

    /**
     * Walk every cascaded value in `$values` and replace any
     * `LightDark` instance with its preferred arm — the dark side
     * when `color-scheme` resolves to a list whose first preferred
     * scheme is dark, the light side otherwise (matching the spec
     * default).
     */
    private function resolveLightDarkValues(CascadedValues $values): void
    {
        $scheme = $values->get('color-scheme');
        $isDark = false;
        if ($scheme instanceof Keyword && strtolower($scheme->name) === 'dark') {
            $isDark = true;
        } elseif ($scheme instanceof ValueList) {
            foreach ($scheme->values as $entry) {
                if (!$entry instanceof Keyword) {
                    continue;
                }
                $name = strtolower($entry->name);
                if ($name === 'dark') {
                    $isDark = true;
                    break;
                }
                if ($name === 'light') {
                    break;
                }
            }
        }
        foreach ($values->all() as $name => $value) {
            if ($value instanceof \Phpdftk\Css\Value\LightDark) {
                $values->set($name, $isDark ? $value->dark : $value->light);
            }
        }
    }

    /**
     * Return the name of the pseudo-element targeted by `$sel` (the last
     * compound's terminating `::name`), or null when the selector is a
     * regular host-element selector. Used to gate cascade matching so
     * `p::before` rules don't pollute `<p>`'s style and vice versa.
     */
    /**
     * Yield every `StyleRule` reachable from the given rule list,
     * recursing into `@media`-style conditional at-rules whose prelude
     * matches the current rendering context. Phase-1 simplification:
     * matches `@media print`, `@media all`, and any `@media` list that
     * mentions `print` or `all` (CSS Media Queries 4 §2.3 media types).
     * `@media screen` / `@media speech` / unrecognised media features
     * are skipped, so screen-only rules don't leak into print output.
     *
     * `@supports` blocks are always entered (we treat every supports()
     * condition as matching at Phase 1; full evaluation lands later
     * alongside `@supports` query parsing).
     *
     * @param list<\Phpdftk\Css\Sheet\Rule> $rules
     * @return iterable<array{0: StyleRule, 1: ?int}>
     */
    private function activeStyleRules(array $rules, ?int $layerIndex = null): iterable
    {
        foreach ($rules as $rule) {
            if ($rule instanceof StyleRule) {
                yield [$rule, $layerIndex];
                continue;
            }
            if ($rule instanceof \Phpdftk\Css\Sheet\AtRule) {
                $name = strtolower($rule->name);
                // Statement-form `@layer name1, name2;` (no block).
                // Just registers each name in declaration order so the
                // priority ranking is locked in before later usages.
                if ($name === 'layer' && $rule->block === null) {
                    $parts = preg_split('/\s*,\s*/', trim($rule->prelude)) ?: [];
                    foreach ($parts as $layerName) {
                        if ($layerName === '') {
                            continue;
                        }
                        $this->resolveLayerIndex($layerName);
                    }
                    continue;
                }
                if ($rule->block === null) {
                    continue;
                }
                if ($name === 'media') {
                    if (!$this->mediaPreludeMatches($rule->prelude)) {
                        continue;
                    }
                } elseif ($name === 'supports') {
                    if (!$this->supportsPreludeMatches($rule->prelude)) {
                        continue;
                    }
                } elseif ($name === 'layer') {
                    // CSS Cascade 5 §3.1 — `@layer <name>? { ... }`
                    // block-form. Resolve the layer name (or assign a
                    // fresh anonymous index) and pass it down so every
                    // nested StyleRule remembers which layer it lives
                    // in. Subsequent occurrences of the SAME named
                    // layer reuse the previously-assigned index, so
                    // splitting one layer across multiple `@layer foo
                    // { ... }` blocks composes correctly. Nested
                    // `@layer` inside another layer creates a "child"
                    // layer; we flatten by assigning a fresh index
                    // (full sub-layer priority lands later).
                    $prelude = trim($rule->prelude);
                    $childIndex = $prelude === ''
                        ? $this->nextLayerIndex++
                        : $this->resolveLayerIndex($prelude);
                    $nested = [];
                    foreach ($rule->block->contents as $item) {
                        if ($item instanceof \Phpdftk\Css\Sheet\Rule) {
                            $nested[] = $item;
                        }
                    }
                    yield from $this->activeStyleRules($nested, $childIndex);
                    continue;
                } elseif ($name === 'scope') {
                    // CSS Cascade 6 §3 — `@scope (root) [to limit] { ... }`
                    // Same pass-through posture as @layer for now;
                    // proper scope tree handling lands later.
                } elseif ($name === 'starting-style') {
                    // CSS Transitions 2 §3 — declares the entry
                    // (from-) state for transitioning properties.
                    // For static print render the starting state
                    // IS the rendered state, so the inner rules
                    // pass through.
                } elseif ($name === 'container') {
                    // CSS Containment 3 §4.4 — `@container [name?]
                    // (query) { ... }`. Container queries resolve
                    // against the containing element's size at
                    // layout time. For now we pass-through (rules
                    // apply unconditionally) so author CSS doesn't
                    // drop. Full container-query matching needs
                    // the layout engine to expose containing-block
                    // sizes back to the cascade.
                } elseif ($name === 'position-try') {
                    // CSS Anchor Positioning 1 §8 — `@position-try
                    // --fallback { ... }` declares positioning
                    // fallbacks used when the primary position
                    // overflows. For static print, primary wins;
                    // pass through so rules cascade.
                } else {
                    continue;
                }
                $nested = [];
                foreach ($rule->block->contents as $item) {
                    if ($item instanceof \Phpdftk\Css\Sheet\Rule) {
                        $nested[] = $item;
                    }
                }
                yield from $this->activeStyleRules($nested, $layerIndex);
            }
        }
    }

    /**
     * Look up (or assign) a stable index for a named layer. Named
     * layers reuse the same index across all occurrences, so the
     * priority ranking is locked in by the FIRST mention of the name —
     * even when later styles add more rules to the same layer.
     */
    private function resolveLayerIndex(string $name): int
    {
        $key = strtolower($name);
        if (!isset($this->layerIndices[$key])) {
            $this->layerIndices[$key] = $this->nextLayerIndex++;
        }
        return $this->layerIndices[$key];
    }

    /**
     * Evaluate a CSS Media Queries 4 prelude against the print
     * rendering context. Supports:
     *  - comma-separated media query list — true when ANY part matches
     *  - bare media types: `print`, `all` match; `screen`, `speech`
     *    don't
     *  - logical `not` prefix — inverts the rest of the query
     *  - logical `only` prefix — historical legacy keyword, treated
     *    as no-op (the query must otherwise match)
     *  - `and`-joined feature queries: `(min-width: N)`, `(max-width:
     *    N)`, `(width: N)`, plus their `min-height` / `max-height` /
     *    `height` siblings; resolves against the cascade's viewport
     *    dimensions when set
     *  - `(orientation: portrait | landscape)` — true when matching
     *    the viewport's aspect ratio
     * Unknown features evaluate to `false` per spec (CSS Media
     * Queries 4 §3.1) so a query gated on something we don't model
     * never accidentally matches.
     */
    private function mediaPreludeMatches(string $prelude): bool
    {
        $lower = strtolower($prelude);
        if ($lower === '' || $lower === 'all') {
            return true;
        }
        foreach (explode(',', $lower) as $part) {
            if ($this->matchSingleMediaQuery(trim($part))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match a single comma-separated media query — `[not|only] type?
     * [and (feature)]*` per CSS Media Queries 4 §2.1.
     */
    private function matchSingleMediaQuery(string $query): bool
    {
        if ($query === '') {
            return false;
        }
        $negate = false;
        if (str_starts_with($query, 'not ')) {
            $negate = true;
            $query = trim(substr($query, 4));
        } elseif (str_starts_with($query, 'only ')) {
            // `only` is a no-op gate for legacy browsers; the rest of
            // the query must still match.
            $query = trim(substr($query, 5));
        }
        if ($query === '') {
            return false;
        }
        // Per CSS Media Queries 4 §2.1, `not` / `and` / `only` / `or`
        // are reserved keywords and cannot be media types. A query
        // whose type slot holds one of them is invalid syntax → `not
        // all` → false. The outer `not` prefix does NOT flip this:
        // invalid stays invalid.
        $parts = preg_split('/\s+and\s+/', $query) ?: [];
        $head = $parts[0] ?? '';
        if ($head !== '' && $head[0] !== '(' && in_array($head, ['not', 'and', 'only', 'or', 'layer'], true)) {
            return false;
        }
        $result = $this->evaluateMediaQueryBody($query);
        return $negate ? !$result : $result;
    }

    /**
     * Evaluate a media-query body — `<type>? [and (feature)]*`.
     * Returns true when the type matches AND every feature query
     * evaluates to true.
     */
    private function evaluateMediaQueryBody(string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return true;
        }
        // Modern MQ4 syntax — the body IS a media-condition (no
        // leading type token), with arbitrary `and` / `or` / `not`
        // and grouped parens. Delegate to the condition evaluator
        // which is paren-depth-aware.
        if ($query[0] === '(') {
            return $this->evaluateMediaCondition($query);
        }
        // Legacy syntax — `<type> [ and (feature) ]*`. Split on
        // top-level ` and ` (features are parenthesised so they
        // separate cleanly) and verify each.
        $parts = preg_split('/\s+and\s+/', $query) ?: [];
        $first = $parts[0] ?? '';
        $typeMatches = $first === ''
            || $first === 'all'
            || in_array($first, $this->matchingMediaTypes, true);
        if (!$typeMatches) {
            return false;
        }
        array_shift($parts);
        foreach ($parts as $featureRaw) {
            $feature = trim($featureRaw);
            if ($feature === '' || $feature[0] !== '(' || !str_ends_with($feature, ')')) {
                return false;
            }
            $inside = trim(substr($feature, 1, -1));
            if (!$this->evaluateMediaCondition($inside)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate the body of a parenthesised media condition. Handles
     * the CSS Media Queries 4 `<media-condition>` shape:
     *
     *   `not (...)`               negation of a nested condition
     *   `(...) and (...)`         conjunction inside a group
     *   `(...) or (...)`          disjunction inside a group
     *   `<name>[: <value>]`       a plain feature query
     *
     * Pure feature names (no leading `not` / no nested parens) defer
     * to {@see matchFeatureQuery}.
     */
    private function evaluateMediaCondition(string $body): bool
    {
        $body = trim($body);
        if (str_starts_with($body, 'not ') || str_starts_with($body, 'not(')) {
            $rest = trim(substr($body, 3));
            // CSS Media Queries 4 §3.3 — `not` is a unary operator
            // over EXACTLY one media-in-parens. `not (X) and (Y)` is
            // a syntax error: the `not` cannot pair with a trailing
            // and/or chain unless the whole and/or is grouped
            // (`not ((X) and (Y))`). Verify the rest is a single
            // depth-balanced paren expression with nothing trailing.
            if (!$this->isSingleParenExpression($rest)) {
                return false;
            }
            return !$this->evaluateMediaCondition(trim(substr($rest, 1, -1)));
        }
        if ($body !== '' && $body[0] === '(') {
            // Top-level looks like `(X) <op> (Y) ...` — split on top-
            // level `and` / `or` between parenthesised groups.
            $segments = $this->splitMediaConditionAt($body, ['and', 'or']);
            if (count($segments) > 1) {
                // CSS Media Queries 4 §3.3 — `and` and `or` cannot be
                // mixed at the same level without explicit grouping
                // parens: `(A) and (B) or (C)` is invalid syntax. Also
                // a bare `not (X)` cannot appear as a segment of an
                // and/or chain — it must be wrapped: `(not (X))`. Both
                // shapes reject as `not all` per §3.1.
                $opsSeen = [];
                foreach ($segments as [$segOp, $segText]) {
                    if ($segOp !== null) {
                        $opsSeen[$segOp] = true;
                    }
                    $segText = trim($segText);
                    if ($segText === '' || $segText[0] !== '(' || !str_ends_with($segText, ')')) {
                        return false;
                    }
                }
                if (isset($opsSeen['and']) && isset($opsSeen['or'])) {
                    return false;
                }
                $result = null;
                foreach ($segments as [$segOp, $segText]) {
                    $segVal = $this->evaluateMediaCondition(trim($segText));
                    if ($result === null) {
                        $result = $segVal;
                        continue;
                    }
                    $result = $segOp === 'and' ? ($result && $segVal) : ($result || $segVal);
                }
                return (bool) $result;
            }
            if (str_ends_with($body, ')')) {
                return $this->evaluateMediaCondition(trim(substr($body, 1, -1)));
            }
            return false;
        }
        return $this->matchFeatureQuery($body);
    }

    /**
     * Test whether `$s` is a single depth-balanced paren expression
     * with no trailing content — that is, `(X)` where the opening
     * paren at index 0 only closes at the last character. Used to
     * enforce the CSS Media Queries 4 §3.3 rule that `not` takes
     * exactly one `<media-in-parens>` operand.
     */
    private function isSingleParenExpression(string $s): bool
    {
        $s = trim($s);
        if ($s === '' || $s[0] !== '(' || !str_ends_with($s, ')')) {
            return false;
        }
        $depth = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            if ($s[$i] === '(') {
                $depth++;
            } elseif ($s[$i] === ')') {
                $depth--;
                // If the outermost `(` closes before the last char,
                // there's content after it → not a single paren expr.
                if ($depth === 0 && $i !== $n - 1) {
                    return false;
                }
            }
        }
        return $depth === 0;
    }

    /**
     * Split a `(A) and (B) and (C)` (or `or`-joined) string into
     * `[[op, segment], ...]` pairs, where `op` is the operator that
     * preceded the segment (`null` for the first). Honors paren depth
     * so nested `(not (X))` groups don't get torn apart.
     *
     * @param list<string> $operators
     * @return list<array{0: ?string, 1: string}>
     */
    private function splitMediaConditionAt(string $body, array $operators): array
    {
        $segments = [];
        $current = '';
        $currentOp = null;
        $depth = 0;
        $i = 0;
        $n = strlen($body);
        while ($i < $n) {
            $ch = $body[$i];
            if ($ch === '(') {
                $depth++;
                $current .= $ch;
                $i++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                $current .= $ch;
                $i++;
                continue;
            }
            if ($depth === 0 && ctype_space($ch)) {
                foreach ($operators as $op) {
                    $candidate = $op . ' ';
                    if (substr($body, $i + 1, strlen($candidate)) === $candidate) {
                        $segments[] = [$currentOp, trim($current)];
                        $current = '';
                        $currentOp = $op;
                        $i += 1 + strlen($candidate);
                        continue 2;
                    }
                }
            }
            $current .= $ch;
            $i++;
        }
        if ($current !== '') {
            $segments[] = [$currentOp, trim($current)];
        }
        return $segments;
    }

    /**
     * Evaluate one feature query like `min-width: 600px` or
     * `orientation: portrait`. Returns false for any feature the
     * cascade doesn't model.
     */
    private function matchFeatureQuery(string $inside): bool
    {
        if (!str_contains($inside, ':')) {
            // CSS Media Queries 4 §2.4.4 — boolean form: `(feature)`
            // matches when the feature's value is non-zero / not the
            // default "no" answer. We answer for the dimension features
            // we actually model; everything else stays false so an
            // unknown feature can never silently match.
            $name = strtolower(trim($inside));
            switch ($name) {
                case 'width':
                case 'device-width':
                    return $this->viewportWidth === null || $this->viewportWidth > 0;
                case 'height':
                case 'device-height':
                    return $this->viewportHeight === null || $this->viewportHeight > 0;
                case 'aspect-ratio':
                case 'device-aspect-ratio':
                    return $this->viewportWidth !== null
                        && $this->viewportHeight !== null
                        && $this->viewportWidth > 0
                        && $this->viewportHeight > 0;
                case 'resolution':
                    return true;
                case 'color':
                    return true;
                case 'monochrome':
                case 'color-index':
                case 'grid':
                    return false;
                default:
                    return false;
            }
        }
        [$name, $valueRaw] = array_map('trim', explode(':', $inside, 2));
        $name = strtolower($name);
        $valueRaw = strtolower($valueRaw);
        if ($name === 'orientation') {
            if ($this->viewportWidth === null || $this->viewportHeight === null) {
                return true;
            }
            $isLandscape = $this->viewportWidth >= $this->viewportHeight;
            return ($valueRaw === 'landscape' && $isLandscape)
                || ($valueRaw === 'portrait' && !$isLandscape);
        }
        if (in_array($name, ['min-width', 'max-width', 'width'], true)) {
            return $this->matchDimensionFeature($name, $valueRaw, $this->viewportWidth);
        }
        if (in_array($name, ['min-height', 'max-height', 'height'], true)) {
            return $this->matchDimensionFeature($name, $valueRaw, $this->viewportHeight);
        }
        // CSS Media Queries 4 §4.7 — device-* features mirror the
        // top-level dimensions for our print-target rendering context;
        // we don't model paged output devices separately from the
        // rendered viewport.
        if (in_array($name, ['min-device-width', 'max-device-width', 'device-width'], true)) {
            $bare = substr($name, 0, 3) === 'min' ? 'min-width'
                  : (substr($name, 0, 3) === 'max' ? 'max-width' : 'width');
            return $this->matchDimensionFeature($bare, $valueRaw, $this->viewportWidth);
        }
        if (in_array($name, ['min-device-height', 'max-device-height', 'device-height'], true)) {
            $bare = substr($name, 0, 3) === 'min' ? 'min-height'
                  : (substr($name, 0, 3) === 'max' ? 'max-height' : 'height');
            return $this->matchDimensionFeature($bare, $valueRaw, $this->viewportHeight);
        }
        // §4.4 — `color`, `color-index`, `monochrome` are integer
        // features (bits per channel; palette size; monochrome bits).
        // We model a color print device: 8-bit color, no palette,
        // no monochrome. Per §3 these features are "false in the
        // negative range" — the legacy min-/max- form clamps the
        // queried value to [0, ∞) before comparison so negative
        // thresholds always satisfy a `min-` query and never satisfy
        // a `max-` query (max- against negative clamps to 0).
        if (in_array($name, ['min-color', 'max-color', 'color'], true)) {
            return $this->matchIntegerFeature($name, $valueRaw, 8);
        }
        if (in_array($name, ['min-color-index', 'max-color-index', 'color-index'], true)) {
            return $this->matchIntegerFeature($name, $valueRaw, 0);
        }
        if (in_array($name, ['min-monochrome', 'max-monochrome', 'monochrome'], true)) {
            return $this->matchIntegerFeature($name, $valueRaw, 0);
        }
        // CSS Media Queries 4 §4.6 — `aspect-ratio` / `device-aspect-
        // ratio` and their min-/max- prefix forms. Value is a
        // `<ratio>` (`<number> [ / <number> ]?`). Compares against
        // the viewport's width / height ratio; with no viewport we
        // can't decide → return true permissively so author CSS
        // doesn't silently drop on print contexts.
        if (in_array($name, [
            'aspect-ratio', 'min-aspect-ratio', 'max-aspect-ratio',
            'device-aspect-ratio', 'min-device-aspect-ratio', 'max-device-aspect-ratio',
        ], true)) {
            return $this->matchRatioFeature($name, $valueRaw);
        }
        return false;
    }

    /**
     * Evaluate an `(aspect-ratio: <ratio>)` style feature query.
     * The `<ratio>` may be `<num>` (treated as `<num>/1`) or
     * `<num>/<num>`. Compares against the viewport width/height
     * ratio.
     */
    private function matchRatioFeature(string $name, string $valueRaw): bool
    {
        if ($this->viewportWidth === null
            || $this->viewportHeight === null
            || $this->viewportWidth <= 0
            || $this->viewportHeight <= 0
        ) {
            return true;
        }
        $valueRaw = trim($valueRaw);
        // CSS Values 4 §10 — `calc(<num> / <num>)` evaluates to a
        // number that can stand in for the ratio. Handle the simple
        // single-division form here (WPT mq-calc-008) without falling
        // through to the full calc engine.
        if (preg_match('/^calc\s*\(\s*([\-+]?[0-9]*\.?[0-9]+)\s*\/\s*([\-+]?[0-9]*\.?[0-9]+)\s*\)$/i', $valueRaw, $cm) === 1) {
            $num = (float) $cm[1];
            $den = (float) $cm[2];
        } elseif (preg_match('/^([\-+]?[0-9]*\.?[0-9]+)\s*(?:\/\s*([\-+]?[0-9]*\.?[0-9]+))?$/', $valueRaw, $m) === 1) {
            $num = (float) $m[1];
            $den = isset($m[2]) ? (float) $m[2] : 1.0;
        } else {
            return false;
        }
        // Browsers treat any ratio with a zero numerator or
        // denominator as +∞ for `max-*` and 0 for `min-*` (so a
        // `0/N` ratio always satisfies `max-aspect-ratio` and never
        // satisfies `min-aspect-ratio` — WPT device-aspect-ratio-002
        // walks the `0/0` shape explicitly).
        if ($den === 0.0) {
            return str_starts_with($name, 'max-');
        }
        if ($num <= 0.0) {
            return str_starts_with($name, 'max-');
        }
        $queried = $num / $den;
        $viewport = $this->viewportWidth / $this->viewportHeight;
        if (str_starts_with($name, 'min-')) {
            return $viewport >= $queried;
        }
        if (str_starts_with($name, 'max-')) {
            return $viewport <= $queried;
        }
        return abs($viewport - $queried) < 1e-6;
    }

    /**
     * Match an integer-valued media feature — `color`, `color-index`,
     * `monochrome`. Same shape as {@see matchDimensionFeature} but
     * parses bare integers instead of `<length>` values.
     *
     * Compares against the literal queried value (no clamping). The
     * device values we model are all non-negative; the natural
     * numeric comparison gives the same answer browsers do for
     * negative thresholds (WPT mq-negative-range-001/002): `min-color:
     * -10` always satisfies (8 ≥ -10) and `max-color-index: -10` never
     * does (0 ≤ -10 is false).
     */
    private function matchIntegerFeature(string $name, string $valueRaw, int $deviceValue): bool
    {
        $valueRaw = trim($valueRaw);
        if (!is_numeric($valueRaw)) {
            return false;
        }
        $queried = (int) $valueRaw;
        return match (true) {
            str_starts_with($name, 'min-') => $deviceValue >= $queried,
            str_starts_with($name, 'max-') => $deviceValue <= $queried,
            default => $deviceValue === $queried,
        };
    }

    /**
     * Evaluate a CSS Conditional Rules 3 `@supports` prelude. Returns
     * true when the cascade can honour the queried feature. Supported:
     *  - `(property: value)` — true when the property is registered
     *    AND the value parses without errors
     *  - `(property)` boolean form — true when the property is
     *    registered
     *  - `not <cond>` — invert
     *  - `<a> and <b>` / `<a> or <b>` — combined conditions
     *  - parentheses for grouping
     *
     * `selector()`, `font-tech()`, `font-format()` and other extended
     * predicates evaluate to false (we don't model selector support
     * granularity).
     */
    public function supportsPreludeMatches(string $prelude): bool
    {
        $prelude = trim($prelude);
        if ($prelude === '') {
            return true;
        }
        $pos = 0;
        $result = $this->parseSupportsOr($prelude, $pos);
        // CSS Conditional Rules 3 §3.1 — the entire prelude must be a
        // single <supports-condition>. Leftover non-whitespace input
        // means the prelude is a syntax error → drop the rule. WPT
        // css-supports-039 exercises this with `(color: green)
        // or(color: blue)` where `or(` is a function token; the OR
        // parser finishes after `(color: green)` and the trailing
        // function call shouldn't silently win.
        $this->skipSupportsWs($prelude, $pos);
        if ($pos < strlen($prelude)) {
            return false;
        }
        return $result;
    }

    /**
     * Parse a top-level CSS Conditional Rules 3 §3.1
     * `<supports-condition>`:
     *
     *   not <supports-in-parens>
     *   <supports-in-parens> [ and <supports-in-parens> ]+
     *   <supports-in-parens> [ or  <supports-in-parens> ]+
     *   <supports-in-parens>
     *
     * The three forms are mutually exclusive: a `not` cannot be mixed
     * with `and` / `or` at the same level (`not X and Y` is invalid;
     * use `(not X) and Y` instead), and `and` cannot be mixed with
     * `or` at the same level (`X and Y or Z` is invalid; use
     * `(X and Y) or Z`). Each violation is a syntax error per §3.1,
     * which makes the at-rule drop entirely. `not`/`and`/`or` nested
     * INSIDE a `(...)` group are parsed via parseSupportsPrimary →
     * recursive parseSupportsOr, so grouping parens still combine
     * arbitrarily.
     */
    private function parseSupportsOr(string $s, int &$pos): bool
    {
        $this->skipSupportsWs($s, $pos);
        if ($this->consumeSupportsKeyword($s, $pos, 'not')) {
            $inner = $this->parseSupportsPrimary($s, $pos);
            // After `not <supports-in-parens>`, no further `and`/`or`
            // is allowed at this level. Anything trailing is a syntax
            // error; the supportsPreludeMatches caller already verifies
            // EOF, but we leave $pos at the first non-ws character so
            // it sees the leftover.
            return !$inner;
        }
        $left = $this->parseSupportsPrimary($s, $pos);
        $this->skipSupportsWs($s, $pos);
        // Look ahead for the FIRST operator after the initial primary
        // — that pins the operator type for the entire chain.
        $firstOp = null;
        if ($this->peekSupportsKeyword($s, $pos, 'and')) {
            $firstOp = 'and';
        } elseif ($this->peekSupportsKeyword($s, $pos, 'or')) {
            $firstOp = 'or';
        } else {
            return $left;
        }
        // Consume the chosen operator chain. The other operator can
        // not appear at the same level; if we hit it the rule drops.
        while ($this->consumeSupportsKeyword($s, $pos, $firstOp)) {
            $right = $this->parseSupportsPrimary($s, $pos);
            $left = $firstOp === 'and' ? ($left && $right) : ($left || $right);
            $this->skipSupportsWs($s, $pos);
        }
        return $left;
    }

    /**
     * Peek whether a keyword token starts at $pos without consuming
     * it. Used by parseSupportsOr to pick the operator type before
     * committing.
     */
    private function peekSupportsKeyword(string $s, int $pos, string $kw): bool
    {
        $len = strlen($kw);
        if (strtolower(substr($s, $pos, $len)) !== $kw) {
            return false;
        }
        $next = $s[$pos + $len] ?? '';
        return $next === '' || ctype_space($next);
    }

    /**
     * Recursive-descent: `(<expr>)` OR a bare feature function call
     * (`selector(...)`, `font-format(...)`, `font-tech(...)`) per
     * CSS Conditional Rules 4 §3 — these appear bare in the
     * prelude, NOT wrapped in another `(...)`.
     */
    private function parseSupportsPrimary(string $s, int &$pos): bool
    {
        $this->skipSupportsWs($s, $pos);
        if ($pos >= strlen($s)) {
            return false;
        }
        // Bare feature function form: `selector(...)`, `font-format(...)`,
        // `font-tech(...)`, plus the CSS Conditional Rules 3 §3.1
        // `<general-enclosed>` forwards-compat shape — any other
        // `<ident>(<any-value>)` consumes its tokens and evaluates
        // to false. This lets `unknown(...) or (color: green)` keep
        // parsing the `or` branch instead of stopping dead at the
        // unknown function (WPT css-supports-036).
        if (preg_match('/\G([A-Za-z][\w-]*)\(/A', $s, $m, 0, $pos) === 1) {
            $name = strtolower($m[1]);
            $pos += strlen($m[0]);
            $start = $pos;
            $depth = 1;
            while ($pos < strlen($s)) {
                $ch = $s[$pos];
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                $pos++;
            }
            $arg = substr($s, $start, $pos - $start);
            if ($pos < strlen($s)) {
                $pos++; // skip closing ')'
            }
            if (in_array($name, ['selector', 'font-format', 'font-tech'], true)) {
                return $this->evaluateSupportsFeature($m[1] . '(' . $arg . ')');
            }
            // <general-enclosed> — unknown function notation. Consume
            // and evaluate to false per §3.1.
            return false;
        }
        if ($s[$pos] !== '(') {
            return false;
        }
        $pos++; // skip '('
        // Find the matching close paren, respecting nesting.
        $start = $pos;
        $depth = 1;
        while ($pos < strlen($s)) {
            $ch = $s[$pos];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            $pos++;
        }
        $body = trim(substr($s, $start, $pos - $start));
        if ($pos < strlen($s)) {
            $pos++; // skip ')'
        }
        // If the body itself contains `and`/`or`/`not` at the top
        // level, recurse — it's a logical group like `(A and B)`. The
        // recursive call must consume the WHOLE body; any trailing
        // tokens (e.g. `not X or Y` where `not` and `or` are mixed)
        // make the whole group invalid → false per §3.1.
        if (preg_match('/^(not\s|.*\s(and|or)\s)/i', $body) === 1) {
            $sub = 0;
            $result = $this->parseSupportsOr($body, $sub);
            $this->skipSupportsWs($body, $sub);
            if ($sub < strlen($body)) {
                return false;
            }
            return $result;
        }
        // CSS Conditional Rules 3 §3 — extra parens around a single
        // sub-expression are allowed. `((color: green))` strips to
        // `(color: green)` as the body, which must recurse as a
        // primary itself rather than being misread as a malformed
        // `(property: value)` declaration. The recursive call must
        // consume the WHOLE body; trailing tokens like ` or(...)` in
        // WPT at-supports-043 make the whole prelude invalid.
        if ($body !== '' && $body[0] === '(') {
            $sub = 0;
            $result = $this->parseSupportsPrimary($body, $sub);
            $this->skipSupportsWs($body, $sub);
            if ($sub < strlen($body)) {
                return false;
            }
            return $result;
        }
        return $this->evaluateSupportsFeature($body);
    }

    private function skipSupportsWs(string $s, int &$pos): void
    {
        while ($pos < strlen($s) && ctype_space($s[$pos])) {
            $pos++;
        }
    }

    private function consumeSupportsKeyword(string $s, int &$pos, string $kw): bool
    {
        $this->skipSupportsWs($s, $pos);
        $len = strlen($kw);
        if (strtolower(substr($s, $pos, $len)) !== $kw) {
            return false;
        }
        // Must be followed by whitespace or end. Per CSS Conditional
        // Rules 3 §3.1 and CSS Syntax 3, `not(`, `and(`, `or(` are
        // FUNCTION tokens — they bind into a function call, NOT the
        // boolean operator keyword. WPT css-supports-038 / -039 fail
        // when we accept `not(unknown)` as the `not` operator instead
        // of as an unknown function. So whitespace (or EOF) is the
        // ONLY valid boundary after the keyword.
        $next = $s[$pos + $len] ?? '';
        if ($next !== '' && !ctype_space($next)) {
            return false;
        }
        $pos += $len;
        return true;
    }

    private function evaluateSupportsFeature(string $body): bool
    {
        if ($body === '') {
            return false;
        }
        // CSS Conditional Rules 4 §3 — `selector(<sel>)`,
        // `font-format(<f>)`, `font-tech(<t>)`. Detect by leading
        // function name.
        if (preg_match('/^([A-Za-z][\w-]*)\s*\((.*)\)\s*$/s', $body, $m) === 1) {
            $name = strtolower($m[1]);
            $arg = trim($m[2]);
            return match ($name) {
                'selector' => $this->evaluateSupportsSelector($arg),
                'font-format' => self::evaluateSupportsFontFormat($arg),
                'font-tech' => self::evaluateSupportsFontTech($arg),
                default => false,
            };
        }
        if (str_contains($body, ':')) {
            // `(property: value)` form — property must be in the
            // registry AND the value must parse for the property's
            // known type. Full type validation is a larger lift, but
            // catching unambiguously-invalid values (e.g. `color:
            // rainbow` — `rainbow` is not a CSS color) fixes the
            // common @supports-condition-failure pattern used by
            // every browser-feature-detection stylesheet in the wild.
            $colonPos = strpos($body, ':');
            if ($colonPos === false) {
                return false;
            }
            $prop = strtolower(trim(substr($body, 0, $colonPos)));
            $value = trim(substr($body, $colonPos + 1));
            // CSS Conditional Rules 3 §3.1 — the body is exactly one
            // declaration. A top-level `;` separates declarations
            // (WPT at-supports-039); bare `]`, `}`, `[`, `{` are
            // token delimiters that never appear at the top level
            // of a single declaration's value (WPT at-supports-026).
            // Also balance must be preserved — a left bracket without
            // its right mate (or vice versa) is a parse error too.
            $parenDepth = 0;
            $bracketDepth = 0;
            $braceDepth = 0;
            $vlen = strlen($value);
            for ($i = 0; $i < $vlen; $i++) {
                $ch = $value[$i];
                if ($ch === '(') {
                    $parenDepth++;
                } elseif ($ch === ')') {
                    $parenDepth--;
                } elseif ($ch === '[') {
                    $bracketDepth++;
                } elseif ($ch === ']') {
                    $bracketDepth--;
                } elseif ($ch === '{') {
                    $braceDepth++;
                } elseif ($ch === '}') {
                    $braceDepth--;
                } elseif ($parenDepth + $bracketDepth + $braceDepth === 0 && $ch === ';') {
                    return false;
                }
                if ($parenDepth < 0 || $bracketDepth < 0 || $braceDepth < 0) {
                    return false;
                }
            }
            if ($parenDepth !== 0 || $bracketDepth !== 0 || $braceDepth !== 0) {
                return false;
            }
            // The declaration grammar permits a trailing `!important`;
            // the flag changes specificity, not parse validity, so
            // strip it before per-type acceptance.
            $value = preg_replace('/\s*!\s*important\s*$/i', '', $value) ?? $value;
            $value = trim($value);
            // Property is "supported" if it's in the registry (a
            // longhand we know about) OR a known shorthand we expand.
            // Shorthands aren't registered with initial values but
            // the @supports prelude still names them.
            if (!$this->registry->has($prop) && !$this->shorthands->isShorthand($prop)) {
                return false;
            }
            return $this->supportsValueIsAcceptable($prop, $value);
        }
        // Boolean form `(property)`.
        return $this->registry->has(strtolower($body));
    }

    /**
     * Validate a `(property: value)` body's value against the property's
     * known type system, narrow enough to catch the common @supports
     * feature-detection patterns. Full type validation is a larger lift;
     * this helper catches:
     *
     *   - bareword identifiers that aren't named colours, for colour-typed
     *     properties (`color: rainbow` → false)
     *   - empty / whitespace-only values
     *
     * Other property types currently fall through to "accept", matching
     * the previous behaviour. Tightening per-type acceptance is additive
     * and can land per-property.
     */
    private function supportsValueIsAcceptable(string $property, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        // Colour-typed properties: `color`, `background-color`,
        // `border-*-color`, `outline-color`, `text-decoration-color`, etc.
        // A bareword that isn't a CSS named colour (or `currentcolor` /
        // `transparent`) fails the value-validity check.
        if ($this->isColorTypedProperty($property)) {
            return $this->isAcceptableColorValue($value);
        }
        // CSS Conditional Rules 3 §3.1 — at minimum, the value must
        // either be a bare keyword/length/percentage or use one of
        // the standard CSS function notations. An unknown `<ident>(…)`
        // shape (e.g. `compute(…)` in WPT at-supports-018) is not a
        // recognised value type and the gated rule must drop.
        if (!$this->valueOnlyUsesKnownFunctions($value)) {
            return false;
        }
        return true;
    }

    /**
     * Scan a value string for any `<ident>(…)` callsites; allow only
     * the CSS Values 4 + Color 5 + Images 4 standard names. Anything
     * else (`compute(…)`, `xyz(…)`) is an unknown notation per
     * §3.1's `<general-enclosed>` definition and the surrounding
     * `(property: value)` shape evaluates as false.
     */
    private function valueOnlyUsesKnownFunctions(string $value): bool
    {
        if (!str_contains($value, '(')) {
            return true;
        }
        // CSS Values 4 §10 math functions + CSS Functions registry.
        $known = [
            'calc', 'min', 'max', 'clamp', 'round', 'mod', 'rem',
            'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2',
            'pow', 'sqrt', 'hypot', 'log', 'exp', 'abs', 'sign',
            // Color functions (Color 4/5).
            'rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch',
            'oklab', 'oklch', 'color', 'color-mix', 'light-dark',
            'device-cmyk', 'contrast-color',
            // Value references.
            'var', 'attr', 'env', 'url',
            // Images / gradients.
            'linear-gradient', 'radial-gradient', 'conic-gradient',
            'repeating-linear-gradient', 'repeating-radial-gradient',
            'repeating-conic-gradient',
            'image', 'image-set', 'cross-fade', 'paint', 'element',
            // Counters / content / generated.
            'counter', 'counters', 'string', 'target-counter',
            'target-counters', 'target-text', 'leader',
            // Shapes / transforms / filters.
            'rect', 'inset', 'circle', 'ellipse', 'polygon', 'path',
            'shape', 'ray', 'xywh',
            'translate', 'translatex', 'translatey', 'translatez',
            'translate3d', 'scale', 'scalex', 'scaley', 'scalez',
            'scale3d', 'rotate', 'rotatex', 'rotatey', 'rotatez',
            'rotate3d', 'skew', 'skewx', 'skewy', 'matrix', 'matrix3d',
            'perspective',
            'blur', 'brightness', 'contrast', 'drop-shadow',
            'grayscale', 'hue-rotate', 'invert', 'opacity',
            'saturate', 'sepia',
            // Anchor positioning.
            'anchor', 'anchor-size',
            // Animation timing.
            'cubic-bezier', 'steps', 'linear',
            // Math / numeric.
            'progress',
            // Custom selectors / nesting.
            'fit-content', 'minmax', 'repeat', 'subgrid-line',
        ];
        // Find each `ident(` call. The ident must be one of the known
        // names; otherwise the value is invalid for @supports.
        if (preg_match_all('/(?<![A-Za-z0-9_-])([A-Za-z][\w-]*)\s*\(/', $value, $m) === false) {
            return true;
        }
        foreach ($m[1] as $name) {
            if (!in_array(strtolower($name), $known, true)) {
                return false;
            }
        }
        return true;
    }

    private function isColorTypedProperty(string $property): bool
    {
        return match ($property) {
            'color',
            'background-color',
            'border-top-color',
            'border-right-color',
            'border-bottom-color',
            'border-left-color',
            'border-block-start-color',
            'border-block-end-color',
            'border-inline-start-color',
            'border-inline-end-color',
            'outline-color',
            'text-decoration-color',
            'text-emphasis-color',
            'caret-color',
            'column-rule-color',
            'fill',
            'stroke',
            'flood-color',
            'lighting-color',
            'stop-color' => true,
            default => false,
        };
    }

    private function isAcceptableColorValue(string $value): bool
    {
        $lower = strtolower($value);
        // CSS-wide keywords (always acceptable per the cascade).
        if (in_array($lower, ['inherit', 'initial', 'unset', 'revert', 'revert-layer'], true)) {
            return true;
        }
        // Bare keyword forms: named colour, currentcolor, transparent.
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $value) === 1) {
            if ($lower === 'currentcolor' || $lower === 'transparent') {
                return true;
            }
            return \Phpdftk\Css\Value\NamedColors::knows($lower);
        }
        // Hex notation.
        if (preg_match('/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1) {
            return true;
        }
        // Functional notation (rgb / rgba / hsl / hwb / lab / lch / oklab /
        // oklch / color / color-mix) — accept on shape; the cascade's
        // ValueParser handles the argument validation when the rule actually
        // applies.
        if (preg_match('/^(rgba?|hsla?|hwb|lab|lch|oklab|oklch|color|color-mix)\s*\(/i', $value) === 1) {
            return true;
        }
        return false;
    }

    /**
     * CSS Conditional Rules 4 — `selector(<sel>)` is true when the
     * inner selector parses cleanly. Selector support granularity
     * isn't modelled (we don't differentiate "supported but not
     * yet implemented" from "matches nothing"), so any well-formed
     * selector returns true.
     */
    private function evaluateSupportsSelector(string $selector): bool
    {
        try {
            $parsed = \Phpdftk\Css\Selector\SelectorParser::parse($selector);
        } catch (\Throwable) {
            return false;
        }
        // CSS Conditional Rules 4 §3 — `selector()` takes a SINGLE
        // `<complex-selector>`, NOT a `<selector-list>`. `selector(div,
        // div)` is invalid (WPT at-supports-selector-004) and must
        // evaluate false so the gated rule drops.
        if (count($parsed->selectors) !== 1) {
            return false;
        }
        // `selector()` reports whether the queried selector is actually
        // SUPPORTED — not merely parseable. A bare `<ident>(…)`
        // extension shape, an unknown pseudo-class or pseudo-element,
        // or any vendor-prefixed (`-webkit-`, `-moz-`) name we don't
        // implement all evaluate to false (WPT at-supports-selector-003).
        return $this->selectorIsFullySupported($parsed->selectors[0]);
    }

    private function selectorIsFullySupported(\Phpdftk\Css\Selector\ComplexSelector $selector): bool
    {
        foreach ($selector->compounds as $compound) {
            // ComplexSelector's compounds carry a CompoundSelector
            // plus the combinator to the next compound; we only need
            // the inner one's simple-selector components.
            $inner = $compound instanceof \Phpdftk\Css\Selector\CompoundSelectorWithCombinator
                ? $compound->compound
                : $compound;
            foreach ($inner->components as $simple) {
                if (!$this->simpleSelectorIsSupported($simple)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function simpleSelectorIsSupported(\Phpdftk\Css\Selector\SimpleSelector $simple): bool
    {
        if ($simple instanceof \Phpdftk\Css\Selector\PseudoElementSelector) {
            return $this->isKnownPseudoElement($simple->name)
                && ($simple->arguments === null
                    || $this->allSelectorsSupported($simple->arguments));
        }
        if ($simple instanceof \Phpdftk\Css\Selector\PseudoClassSelector) {
            if (!$this->isKnownPseudoClass($simple->name)) {
                return false;
            }
            if ($simple->arguments !== null) {
                return $this->allSelectorsSupported($simple->arguments);
            }
            return true;
        }
        return true;
    }

    private function allSelectorsSupported(\Phpdftk\Css\Selector\SelectorList $list): bool
    {
        foreach ($list->selectors as $sel) {
            if (!$this->selectorIsFullySupported($sel)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whitelist of pseudo-elements we recognise — CSS Pseudo 4 §3 plus
     * a handful of widely-implemented extensions. Anything not on the
     * list (notably every vendor-prefixed name) reports unsupported.
     */
    private function isKnownPseudoElement(string $name): bool
    {
        return in_array(strtolower($name), [
            'after', 'before',
            'first-letter', 'first-line',
            'backdrop', 'marker', 'placeholder', 'file-selector-button',
            'selection', 'target-text', 'highlight',
            'spelling-error', 'grammar-error',
            'cue', 'cue-region',
            'slotted', 'part', 'theme',
            'view-transition', 'view-transition-group',
            'view-transition-image-pair', 'view-transition-old',
            'view-transition-new',
            'placeholder-shown', 'details-content',
            // HTML interactive controls.
            'picker', 'picker-icon',
            // Scroll markers.
            'scroll-marker', 'scroll-marker-group',
            'scroll-button',
            // Column boxes.
            'column',
            // Search.
            'search-text',
            // Form inputs.
            'first-letter', 'first-line',
        ], true);
    }

    /**
     * Whitelist of pseudo-classes we recognise. Same posture as pseudo-
     * elements — anything not on the list (notably vendor-prefixed
     * names like `-webkit-*`) reports unsupported.
     */
    private function isKnownPseudoClass(string $name): bool
    {
        return in_array(strtolower($name), [
            'hover', 'active', 'focus', 'focus-visible', 'focus-within',
            'link', 'visited', 'any-link', 'target', 'target-within',
            'scope', 'host', 'host-context',
            'root', 'empty', 'blank',
            'first-child', 'last-child', 'only-child',
            'first-of-type', 'last-of-type', 'only-of-type',
            'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type',
            'nth-col', 'nth-last-col',
            'not', 'is', 'where', 'has',
            'lang', 'dir',
            'enabled', 'disabled', 'read-only', 'read-write',
            'required', 'optional', 'placeholder-shown',
            'checked', 'indeterminate', 'default',
            'valid', 'invalid', 'in-range', 'out-of-range',
            'user-invalid', 'user-valid',
            'autofill',
            'fullscreen', 'modal', 'picture-in-picture',
            'popover-open',
            'past', 'current', 'future',
            'state',
            'open', 'closed',
            'defined',
        ], true);
    }

    /**
     * CSS Conditional Rules 4 / Fonts 4 §4.3 — `font-format()`.
     * True for the well-known font format keywords browsers
     * recognise. Without an actual font subsystem we accept the
     * standard formats and reject the rest.
     */
    private static function evaluateSupportsFontFormat(string $format): bool
    {
        $format = strtolower(trim($format, " \t\n\r\0\x0B\"'"));
        return in_array($format, [
            'collection',
            'embedded-opentype',
            'opentype',
            'svg',
            'truetype',
            'woff',
            'woff2',
        ], true);
    }

    /**
     * CSS Conditional Rules 4 / Fonts 4 §4.4 — `font-tech()`. We
     * don't model OpenType variations / palettes / colorv0 / etc
     * granularity (rendering paths for those land with `phpdftk/text`
     * shaping), so this returns false for unknown techs. Accept the
     * baseline ones the renderer's font loader actually supports.
     */
    private static function evaluateSupportsFontTech(string $tech): bool
    {
        $tech = strtolower(trim($tech, " \t\n\r\0\x0B\"'"));
        return in_array($tech, [
            'variations',
            'palettes',
        ], true);
    }

    /**
     * Compare a `<length>` value against the viewport extent.
     * Supports px (default), pt, in, cm, mm. Unknown units evaluate
     * to false. When the viewport extent is unknown (null), the
     * query is treated as matching so print stylesheets never
     * silently drop.
     */
    private function matchDimensionFeature(string $name, string $valueRaw, ?float $viewportExtent): bool
    {
        if ($viewportExtent === null) {
            return true;
        }
        $px = $this->resolveMediaDimensionValue($valueRaw);
        if ($px === null) {
            return false;
        }
        // CSS Values 4 §10 — `<length>` values used in `@media`
        // feature queries are clamped to their valid range. For
        // width / height that means `[0, ∞)` — a negative `calc()`
        // result like `calc(-100px)` clamps to `0px`, so
        // `(min-width: calc(-100px))` matches any non-negative
        // viewport width.
        $px = max(0.0, $px);
        return match ($name) {
            'min-width', 'min-height' => $viewportExtent >= $px,
            'max-width', 'max-height' => $viewportExtent <= $px,
            'width', 'height' => abs($viewportExtent - $px) < 0.001,
            default => false,
        };
    }

    /**
     * Resolve a `<length>` value used in an `@media` feature query
     * down to absolute pixels. Supports bare unit-suffixed lengths
     * (`100px`, `5cm`, `72pt`, `0`) plus simple `calc(<sum>)`
     * arithmetic over those — addition and subtraction at the top
     * level, sufficient for the canonical `calc(-100px)` clamping
     * case from CSS Values 4 §10. Returns null when the value
     * doesn't parse cleanly.
     */
    private function resolveMediaDimensionValue(string $raw): ?float
    {
        $raw = trim($raw);
        if (preg_match('/^calc\s*\((.*)\)\s*$/i', $raw, $m) === 1) {
            return $this->evaluateMediaCalcSum(trim($m[1]));
        }
        return $this->parseMediaLength($raw);
    }

    private function parseMediaLength(string $value): ?float
    {
        $value = trim($value);
        if (preg_match('/^([\-+]?[0-9]*\.?[0-9]+)\s*([a-z]*)$/i', $value, $m) !== 1) {
            return null;
        }
        $n = (float) $m[1];
        $unit = strtolower($m[2] ?? 'px');
        // CSS Media Queries 4 §1.3 — `rem` / `em` / `ex` / `ch` inside
        // an `@media` query use the INITIAL value of `font-size` on
        // the root element (16 CSS px in the absence of a UA
        // stylesheet override), NOT the cascaded value. This
        // intentionally diverges from property-context resolution so
        // authors can build feature-detection breakpoints that don't
        // shift when they bump root font-size on `:root`.
        $rootFontPx = 16.0;
        return match ($unit) {
            '', 'px' => $n,
            'pt' => $n * 96.0 / 72.0,
            'in' => $n * 96.0,
            'cm' => $n * 96.0 / 2.54,
            'mm' => $n * 96.0 / 25.4,
            'q'  => $n * 96.0 / 101.6,
            'pc' => $n * 16.0,
            'em', 'rem' => $n * $rootFontPx,
            'ex' => $n * $rootFontPx * 0.5,
            'ch' => $n * $rootFontPx * 0.5,
            default => null,
        };
    }

    /**
     * Evaluate a sum of media-query lengths: each top-level `+` / `-`
     * separates a term, every term is a `parseMediaLength` value.
     * Returns null on any malformation so the caller fails the feature
     * query — partial evaluation would silently shift a real layout
     * decision off a spec-invalid value.
     */
    private function evaluateMediaCalcSum(string $body): ?float
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }
        // Split on `+` and `-` operators while keeping the operator
        // tokens. Whitespace is required around `+` / `-` (CSS Values 4
        // §10.4) to disambiguate from signed literals; we honour that.
        $tokens = preg_split('/\s+([+-])\s+/', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false || $tokens === []) {
            return null;
        }
        $sum = $this->parseMediaLength($tokens[0]);
        if ($sum === null) {
            return null;
        }
        $count = count($tokens);
        for ($i = 1; $i + 1 < $count; $i += 2) {
            $op = $tokens[$i];
            $value = $this->parseMediaLength($tokens[$i + 1]);
            if ($value === null) {
                return null;
            }
            $sum = $op === '-' ? $sum - $value : $sum + $value;
        }
        return $sum;
    }

    private function selectorPseudoElementName(\Phpdftk\Css\Selector\ComplexSelector $sel): ?string
    {
        // Memoise by selector identity — the same ComplexSelector
        // is matched against every element of the cascade, but
        // its pseudo-element tail is a property of the selector
        // alone.
        if (isset($this->selPseudoCache[$sel])) {
            return $this->selPseudoCache[$sel][0];
        }
        $compounds = $sel->compounds;
        $result = null;
        if ($compounds !== []) {
            $last = $compounds[array_key_last($compounds)]->compound;
            foreach ($last->components as $simple) {
                if ($simple instanceof \Phpdftk\Css\Selector\PseudoElementSelector) {
                    $result = strtolower($simple->name);
                    break;
                }
            }
        }
        $this->selPseudoCache[$sel] = [$result];
        return $result;
    }

    /**
     * Pick the cascade winner for one property, honouring layer
     * priority and `revert-layer` rollback per CSS Cascade 5 §5.3.
     *
     * Ranking, highest-priority first:
     *  1. Tier (origin × importance) — already encoded by `tierFor`.
     *  2. Layer priority within the author tier — for NORMAL author
     *     declarations, unlayered outranks any layered candidate, and
     *     a later-declared layer outranks an earlier-declared one.
     *     For !IMPORTANT author the order reverses (any layered
     *     !important outranks unlayered, earlier-declared layer
     *     outranks later). Outside the author origin layers don't
     *     apply, so a single bucket suffices.
     *  3. Specificity (a, b, c).
     *  4. Source order — later wins.
     *
     * When the picked winner's cascaded value is the `revert-layer`
     * keyword, the spec says to recompute the cascade as if THIS
     * LAYER didn't exist (CSS Cascade 5 §5.4). We drop every
     * candidate that shares the winner's layer (including the
     * unlayered bucket itself when revert-layer appears unlayered)
     * and re-pick from the remainder, looping until we find a
     * non-`revert-layer` value or run out of candidates.
     *
     * @param list<int> $indices
     * @param list<array{declaration: Declaration, specificity: Specificity, origin: Origin, layerIndex: ?int, order: int}> $candidates
     * @return null|array{declaration: Declaration, tier: int, specificity: Specificity, order: int}
     */
    private function pickCascadeWinner(string $property, array $indices, array $candidates): ?array
    {
        // Two exclusion sets for the rollback loops:
        //  • `excludedLayers` — keyed on `origin:layer`; populated by
        //    `revert-layer` knock-outs (CSS Cascade 5 §5.4).
        //  • `excludedOrigins` — keyed on origin; populated by `revert`
        //    knock-outs (CSS Cascade 5 §5.3). `revert` drops the whole
        //    current origin's contribution, so on the next iteration
        //    we look at the next lower origin (Author → User → UA →
        //    initial fallback).
        $excludedLayers = [];
        $excludedOrigins = [];
        while (true) {
            $best = null;
            foreach ($indices as $idx) {
                $c = $candidates[$idx];
                $layer = $c['layerIndex'];
                if (isset($excludedOrigins[$c['origin']->name])) {
                    continue;
                }
                // Scope the exclusion key by origin so that an author
                // `revert-layer` only knocks out the author bucket
                // (not the UA stylesheet's matching candidates, which
                // live at origin=UserAgent with layerIndex=null too).
                $layerKey = $c['origin']->name . ':'
                    . ($layer === null ? 'unlayered' : 'L' . $layer);
                if (isset($excludedLayers[$layerKey])) {
                    continue;
                }
                $tier = self::tierFor($c['origin'], $c['declaration']->important);
                $layerRank = $this->layerRank($c['origin'], $c['declaration']->important, $layer);
                if ($best === null
                    || $this->beats(
                        $tier,
                        $layerRank,
                        $c['specificity'],
                        $c['order'],
                        $best['tier'],
                        $best['layerRank'],
                        $best['specificity'],
                        $best['order'],
                    )
                ) {
                    $best = [
                        'declaration' => $c['declaration'],
                        'tier' => $tier,
                        'layerRank' => $layerRank,
                        'layerKey' => $layerKey,
                        'origin' => $c['origin'],
                        'specificity' => $c['specificity'],
                        'order' => $c['order'],
                    ];
                }
            }
            if ($best === null) {
                return null;
            }
            $winnerValue = $best['declaration']->value;
            if ($winnerValue instanceof Keyword
                && strtolower($winnerValue->name) === 'revert-layer'
            ) {
                // Drop this layer (or the unlayered bucket) from
                // consideration and re-pick. Eventually we either
                // hit a concrete value in a lower-priority layer or
                // run dry, in which case the property falls through
                // to the registry's initial value via
                // `resolveSpecialKeywords` on `revert-layer`.
                $excludedLayers[$best['layerKey']] = true;
                continue;
            }
            if ($winnerValue instanceof Keyword
                && strtolower($winnerValue->name) === 'revert'
            ) {
                // CSS Cascade 5 §5.3 — `revert` rolls the cascade
                // back to the next lower origin. Drop every
                // candidate from the winner's origin and re-pick.
                // Eventually we either land on the UA stylesheet's
                // contribution (the next lower origin still in the
                // candidate list) or run dry → registry initial.
                $excludedOrigins[$best['origin']->name] = true;
                continue;
            }
            return [
                'declaration' => $best['declaration'],
                'tier' => $best['tier'],
                'specificity' => $best['specificity'],
                'order' => $best['order'],
            ];
        }
    }

    /**
     * Numeric layer priority within the author tier. Higher wins.
     *
     * Author normal:
     *   • unlayered → PHP_INT_MAX (always beats any layered).
     *   • layered N → N (later-declared layers got larger N at
     *     resolution time, so they outrank earlier-declared).
     *
     * Author !important:
     *   • unlayered → PHP_INT_MIN (any layered !important wins).
     *   • layered N → -N (first-declared layer's small N becomes
     *     large after negation, so it outranks later-declared).
     *
     * Non-author origins ignore layers — return 0 uniformly so the
     * ranking collapses back to tier + specificity + source order.
     */
    private function layerRank(Origin $origin, bool $important, ?int $layerIndex): int
    {
        if ($origin !== Origin::Author) {
            return 0;
        }
        if ($important) {
            return $layerIndex === null ? PHP_INT_MIN : -$layerIndex;
        }
        return $layerIndex === null ? PHP_INT_MAX : $layerIndex;
    }

    /**
     * Strict "does A beat B" cascade comparison. Tier first, then
     * layer priority within the tier, then specificity, then source
     * order — matches CSS Cascade 5 §6.
     */
    private function beats(
        int $aTier,
        int $aLayer,
        Specificity $aSpec,
        int $aOrder,
        int $bTier,
        int $bLayer,
        Specificity $bSpec,
        int $bOrder,
    ): bool {
        if ($aTier !== $bTier) {
            return $aTier > $bTier;
        }
        if ($aLayer !== $bLayer) {
            return $aLayer > $bLayer;
        }
        $cmp = $aSpec->compare($bSpec);
        if ($cmp !== 0) {
            return $cmp > 0;
        }
        return $aOrder > $bOrder;
    }

    /**
     * Handle the `inherit` / `initial` / `unset` / `revert` keywords. Returns
     * the resolved value (or null when the cascade should leave the property
     * to fall through to inheritance / initial).
     */
    private function resolveSpecialKeywords(
        string $name,
        Value $value,
        ?CascadedValues $parent,
    ): ?Value {
        if (!$value instanceof Keyword) {
            return $value;
        }
        $lower = strtolower($value->name);
        $def = $this->registry->get($name);
        return match ($lower) {
            'inherit' => $parent?->get($name) ?? $def?->initial,
            'initial' => $def?->initial,
            'unset' => $def !== null && $def->inherits
                ? ($parent?->get($name) ?? $def->initial)
                : $def?->initial,
            'revert', 'revert-layer' => $def?->initial,
            default => $value,
        };
    }

    private function applyInheritance(CascadedValues $values, ?CascadedValues $parent): void
    {
        if ($parent === null) {
            return;
        }
        // Iterate only the inheriting subset instead of walking
        // every property. The registry caches the list internally
        // so this is constant time per cascade run.
        foreach ($this->registry->inheritingNames() as $name) {
            if ($values->has($name)) {
                continue;
            }
            $inheritedValue = $parent->get($name);
            if ($inheritedValue !== null) {
                $values->set($name, $inheritedValue);
            }
        }
    }

    /**
     * CSS Custom Properties §3: custom properties always inherit. Copy any
     * not-locally-declared property down from the parent so later `var()`
     * substitution sees the inherited values.
     */
    private function inheritCustomProperties(CascadedValues $values, ?CascadedValues $parent): void
    {
        if ($parent === null) {
            return;
        }
        foreach ($parent->customProperties() as $name => $value) {
            if (!$values->has($name)) {
                $values->set($name, $value);
            }
        }
    }

    /**
     * Walk every cascaded value and substitute `var(--name[, fallback])`
     * references with the resolved custom-property value. Per the spec
     * (CSS Variables §3.2), a missing variable and no fallback leaves the
     * property invalid at computed-value time — the cascade then falls back
     * to the property's initial value.
     *
     * Substitution depth is capped at 100 to match the project's
     * configurable defaults (see Security section in `html-and-svg.md`).
     */
    private function substituteCustomProperties(CascadedValues $values): void
    {
        foreach ($values->all() as $name => $value) {
            $resolved = $this->substituteValue($value, $values, 0);
            if ($resolved === null) {
                // Invalid at computed-value time → revert to initial.
                $def = $this->registry->get($name);
                if ($def !== null) {
                    $values->set($name, $def->initial);
                }
                continue;
            }
            if ($resolved !== $value) {
                $values->set($name, $resolved);
            }
        }
    }

    private function substituteValue(Value $value, CascadedValues $values, int $depth): ?Value
    {
        if ($depth > 100) {
            return null;
        }
        if ($value instanceof CustomProperty) {
            $referenced = $values->get($value->name);
            if ($referenced !== null) {
                return $this->substituteValue($referenced, $values, $depth + 1);
            }
            if ($value->fallback !== null) {
                return $this->substituteValue($value->fallback, $values, $depth + 1);
            }
            return null;
        }
        if ($value instanceof ValueList) {
            $newChildren = [];
            foreach ($value->values as $child) {
                $resolved = $this->substituteValue($child, $values, $depth + 1);
                if ($resolved === null) {
                    return null;
                }
                $newChildren[] = $resolved;
            }
            return new ValueList($newChildren, $value->separator);
        }
        return $value;
    }

    /**
     * Resolve relative-unit lengths to absolute pixels. Two-pass: font-size
     * resolves first against `$context->parentFontSize`, then every other
     * length resolves against the resulting font-size (passed in
     * `currentFontSize`).
     *
     * Mutates `$values` in place and returns it for chaining. Idempotent —
     * already-px lengths pass through unchanged.
     */
    public function resolveLengths(CascadedValues $values, LengthContext $context): CascadedValues
    {
        // Resolve font-size first, using the parent's font-size as the em basis.
        $fontSize = $values->get('font-size');
        $currentFontSize = $context->parentFontSize;
        $emCtx = new LengthContext(
            parentFontSize: $context->parentFontSize,
            currentFontSize: $context->parentFontSize,
            rootFontSize: $context->rootFontSize,
            viewportWidth: $context->viewportWidth,
            viewportHeight: $context->viewportHeight,
        );
        if ($fontSize instanceof Length) {
            $currentFontSize = LengthResolver::toPx($fontSize, $emCtx);
            $values->set('font-size', new Length($currentFontSize, LengthUnit::Px));
        } elseif ($fontSize instanceof \Phpdftk\Css\Value\Percentage) {
            // CSS Fonts 3 §3.5 — `font-size: <percentage>` resolves
            // against the inherited (parent) font-size. Resolving here
            // turns the Percentage into a concrete Length so layout
            // doesn't fall back to the parent size verbatim.
            $currentFontSize = $context->parentFontSize * ($fontSize->value / 100.0);
            $values->set('font-size', new Length($currentFontSize, LengthUnit::Px));
        } elseif ($fontSize instanceof \Phpdftk\Css\Value\Calc) {
            $resolved = CalcEvaluator::resolveValue($fontSize, $emCtx);
            if ($resolved instanceof Length) {
                $currentFontSize = $resolved->value;
                $values->set('font-size', $resolved);
            }
        }
        $bodyCtx = $context->withCurrentFontSize($currentFontSize);

        foreach ($values->all() as $name => $value) {
            if ($name === 'font-size') {
                continue;
            }
            $resolved = $this->resolveValueLengths($value, $bodyCtx);
            if ($resolved !== $value) {
                $values->set($name, $resolved);
            }
        }
        return $values;
    }

    /**
     * Walk a property value tree and replace Length / Calc nodes with
     * absolute-pixel Lengths. ValueLists are rebuilt with their elements
     * resolved recursively (so e.g. `box-shadow: calc(1em + 10px)
     * calc(2em + 11px) 4px black` lands at the painter as a list of
     * pixel Lengths plus the colour).
     *
     * Leaves the value untouched when it isn't a Length / Calc / ValueList
     * — Keywords, Colors, Urls, etc. don't need length resolution.
     */
    private function resolveValueLengths(
        \Phpdftk\Css\Value\Value $value,
        LengthContext $ctx,
    ): \Phpdftk\Css\Value\Value {
        if ($value instanceof Length) {
            return new Length(LengthResolver::toPx($value, $ctx), LengthUnit::Px);
        }
        if ($value instanceof \Phpdftk\Css\Value\Calc) {
            return CalcEvaluator::resolveValue($value, $ctx);
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            $children = [];
            $changed = false;
            foreach ($value->values as $v) {
                $rv = $this->resolveValueLengths($v, $ctx);
                if ($rv !== $v) {
                    $changed = true;
                }
                $children[] = $rv;
            }
            return $changed ? new \Phpdftk\Css\Value\ValueList($children, $value->separator) : $value;
        }
        return $value;
    }
}
