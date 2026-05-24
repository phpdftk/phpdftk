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
    ) {}

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
                    foreach ($this->shorthands->expand($decl->property, $decl->value) as $longhand => $value) {
                        $candidates[] = [
                            'declaration' => new Declaration($longhand, $value, $decl->important),
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
                foreach ($this->shorthands->expand($decl->property, $decl->value) as $longhand => $value) {
                    $candidates[] = [
                        'declaration' => new Declaration($longhand, $value, $decl->important),
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
                } elseif ($name !== 'supports') {
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
     * `@media <prelude>` matches when the media list mentions `print` or
     * `all` (the renderer's intrinsic media). Logical `not`/`only` and
     * media features (`(min-width: ...)`) are not honoured at Phase 1 —
     * a `not print` query incorrectly matches; this is a follow-up.
     */
    private function mediaPreludeMatches(string $prelude): bool
    {
        $lower = strtolower($prelude);
        if ($lower === '' || $lower === 'all') {
            return true;
        }
        foreach (explode(',', $lower) as $part) {
            $tokens = preg_split('/\s+/', trim($part)) ?: [];
            foreach ($tokens as $tok) {
                if ($tok === 'print' || $tok === 'all') {
                    return true;
                }
            }
        }
        return false;
    }

    private function selectorPseudoElementName(\Phpdftk\Css\Selector\ComplexSelector $sel): ?string
    {
        $compounds = $sel->compounds;
        if ($compounds === []) {
            return null;
        }
        $last = $compounds[array_key_last($compounds)]->compound;
        foreach ($last->components as $simple) {
            if ($simple instanceof \Phpdftk\Css\Selector\PseudoElementSelector) {
                return strtolower($simple->name);
            }
        }
        return null;
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
        foreach ($this->registry->all() as $name => $def) {
            if (!$def->inherits) {
                continue;
            }
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
