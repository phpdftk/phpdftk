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
        );
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
        $candidates = [];
        $order = 0;
        foreach ($sheets as $sheet) {
            foreach ($this->activeStyleRules($sheet->rules) as $rule) {
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
                        'order' => $order++,
                    ];
                }
            }
        }

        // 2. Pick winning declaration per property.
        /** @var array<string, array{declaration: Declaration, tier: int, specificity: Specificity, order: int}> $byProperty */
        $byProperty = [];
        foreach ($candidates as $c) {
            $name = $c['declaration']->property;
            $tier = self::tierFor($c['origin'], $c['declaration']->important);
            $existing = $byProperty[$name] ?? null;
            if ($existing === null || $this->shouldReplace($existing, $tier, $c['specificity'], $c['order'])) {
                $byProperty[$name] = [
                    'declaration' => $c['declaration'],
                    'tier' => $tier,
                    'specificity' => $c['specificity'],
                    'order' => $c['order'],
                ];
            }
        }

        // 3. Materialise CascadedValues, then apply inheritance.
        $result = new CascadedValues($this->registry);
        foreach ($byProperty as $name => $winner) {
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
        return $result;
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
     * @return iterable<StyleRule>
     */
    private function activeStyleRules(array $rules): iterable
    {
        foreach ($rules as $rule) {
            if ($rule instanceof StyleRule) {
                yield $rule;
                continue;
            }
            if ($rule instanceof \Phpdftk\Css\Sheet\AtRule && $rule->block !== null) {
                $name = strtolower($rule->name);
                if ($name === 'media') {
                    if (!$this->mediaPreludeMatches($rule->prelude)) {
                        continue;
                    }
                } elseif ($name === 'supports') {
                    if (!$this->supportsPreludeMatches($rule->prelude)) {
                        continue;
                    }
                } elseif ($name === 'layer') {
                    // CSS Cascade 5 §3.1 — `@layer <name> { ... }`
                    // block-form. We currently treat layers as
                    // pass-through (cascade order = source order,
                    // ignoring layer priority). This is wrong per
                    // §3.4 for cross-layer conflicts, but it lets
                    // author CSS inside layer blocks actually
                    // apply instead of being silently dropped.
                    // Full layer-priority cascade lands as a
                    // follow-up.
                } elseif ($name === 'scope') {
                    // CSS Cascade 6 §3 — `@scope (root) [to limit] { ... }`
                    // Same pass-through posture as @layer for now;
                    // proper scope tree handling lands later.
                } else {
                    continue;
                }
                $nested = [];
                foreach ($rule->block->contents as $item) {
                    if ($item instanceof \Phpdftk\Css\Sheet\Rule) {
                        $nested[] = $item;
                    }
                }
                yield from $this->activeStyleRules($nested);
            }
        }
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
        // Split on top-level ` and ` between feature groups. Features
        // are parenthesised so they're easy to separate.
        $parts = preg_split('/\s+and\s+/', $query) ?: [];
        $typeMatches = true;
        $first = $parts[0] ?? '';
        if ($first === '' || $first[0] !== '(') {
            // First slot is a media type (or empty).
            $typeMatches = $first === '' || $first === 'all'
                || $first === 'print';
            array_shift($parts);
        }
        if (!$typeMatches) {
            return false;
        }
        foreach ($parts as $featureRaw) {
            $feature = trim($featureRaw);
            if ($feature === '' || $feature[0] !== '(' || !str_ends_with($feature, ')')) {
                return false;
            }
            $inside = trim(substr($feature, 1, -1));
            if (!$this->matchFeatureQuery($inside)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate one feature query like `min-width: 600px` or
     * `orientation: portrait`. Returns false for any feature the
     * cascade doesn't model.
     */
    private function matchFeatureQuery(string $inside): bool
    {
        if (!str_contains($inside, ':')) {
            // Boolean form `(color)` — unsupported. False per spec.
            return false;
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
        return false;
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
    private function supportsPreludeMatches(string $prelude): bool
    {
        $prelude = trim($prelude);
        if ($prelude === '') {
            return true;
        }
        $pos = 0;
        $result = $this->parseSupportsOr($prelude, $pos);
        return $result;
    }

    /** Recursive-descent: `<and-cond> ( 'or' <and-cond> )*`. */
    private function parseSupportsOr(string $s, int &$pos): bool
    {
        $left = $this->parseSupportsAnd($s, $pos);
        while (true) {
            $this->skipSupportsWs($s, $pos);
            if (!$this->consumeSupportsKeyword($s, $pos, 'or')) {
                break;
            }
            $right = $this->parseSupportsAnd($s, $pos);
            $left = $left || $right;
        }
        return $left;
    }

    /** Recursive-descent: `<unary> ( 'and' <unary> )*`. */
    private function parseSupportsAnd(string $s, int &$pos): bool
    {
        $left = $this->parseSupportsUnary($s, $pos);
        while (true) {
            $this->skipSupportsWs($s, $pos);
            if (!$this->consumeSupportsKeyword($s, $pos, 'and')) {
                break;
            }
            $right = $this->parseSupportsUnary($s, $pos);
            $left = $left && $right;
        }
        return $left;
    }

    /** Recursive-descent: `'not'? <primary>`. */
    private function parseSupportsUnary(string $s, int &$pos): bool
    {
        $this->skipSupportsWs($s, $pos);
        $negate = $this->consumeSupportsKeyword($s, $pos, 'not');
        $inner = $this->parseSupportsPrimary($s, $pos);
        return $negate ? !$inner : $inner;
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
        // Bare feature function form: `selector(...)` etc.
        if (preg_match('/\G([A-Za-z][\w-]*)\(/A', $s, $m, 0, $pos) === 1) {
            $name = strtolower($m[1]);
            if (in_array($name, ['selector', 'font-format', 'font-tech'], true)) {
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
                return $this->evaluateSupportsFeature($m[1] . '(' . $arg . ')');
            }
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
        // level, recurse — it's a logical group like `(A and B)`.
        if (preg_match('/^(not\s|.*\s(and|or)\s)/i', $body) === 1) {
            $sub = 0;
            return $this->parseSupportsOr($body, $sub);
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
        // Must be followed by whitespace or end (so 'and' doesn't match in 'android').
        $next = $s[$pos + $len] ?? '';
        if ($next !== '' && !ctype_space($next) && $next !== '(') {
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
            // `(property: value)` form — we honour any property the
            // cascade's registry knows about. Value validation is a
            // larger lift and pessimistic on edge cases would silently
            // drop valid author CSS, so we accept on property match.
            [$prop] = array_map('trim', explode(':', $body, 2));
            return $this->registry->has(strtolower($prop));
        }
        // Boolean form `(property)`.
        return $this->registry->has(strtolower($body));
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
            return $parsed->selectors !== [];
        } catch (\Throwable) {
            return false;
        }
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
        if (preg_match('/^([\-+]?[0-9]*\.?[0-9]+)\s*(px|pt|in|cm|mm)?$/', $valueRaw, $m) !== 1) {
            return false;
        }
        $n = (float) $m[1];
        $unit = $m[2] ?? 'px';
        $px = match ($unit) {
            'pt' => $n * 96.0 / 72.0,
            'in' => $n * 96.0,
            'cm' => $n * 96.0 / 2.54,
            'mm' => $n * 96.0 / 25.4,
            default => $n,
        };
        return match ($name) {
            'min-width', 'min-height' => $viewportExtent >= $px,
            'max-width', 'max-height' => $viewportExtent <= $px,
            'width', 'height' => abs($viewportExtent - $px) < 0.001,
            default => false,
        };
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
     * @param array{tier: int, specificity: Specificity, order: int} $existing
     */
    private function shouldReplace(array $existing, int $tier, Specificity $spec, int $order): bool
    {
        if ($tier !== $existing['tier']) {
            return $tier > $existing['tier'];
        }
        $cmp = $spec->compare($existing['specificity']);
        if ($cmp !== 0) {
            return $cmp > 0;
        }
        return $order > $existing['order'];
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
        if ($fontSize instanceof Length) {
            $emCtx = new LengthContext(
                parentFontSize: $context->parentFontSize,
                currentFontSize: $context->parentFontSize,
                rootFontSize: $context->rootFontSize,
                viewportWidth: $context->viewportWidth,
                viewportHeight: $context->viewportHeight,
            );
            $currentFontSize = LengthResolver::toPx($fontSize, $emCtx);
            $values->set('font-size', new Length($currentFontSize, LengthUnit::Px));
        }
        $bodyCtx = $context->withCurrentFontSize($currentFontSize);

        foreach ($values->all() as $name => $value) {
            if ($name === 'font-size') {
                continue;
            }
            if ($value instanceof Length) {
                $values->set(
                    $name,
                    new Length(LengthResolver::toPx($value, $bodyCtx), LengthUnit::Px),
                );
            }
        }
        return $values;
    }
}
