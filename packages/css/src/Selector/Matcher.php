<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Selectors-4 matching engine. Operates on `MatchableElement`s so any DOM
 * implementation can plug in.
 *
 * The right-most compound of a complex selector is the "subject" — the
 * compound that must match the element passed in. Combinators read
 * right-to-left, hopping to ancestors / preceding siblings as needed.
 *
 * Phase 1D.2 covers the structural / attribute / common pseudo-class matchers.
 * Stateful pseudo-classes that depend on UI state (`:hover`, `:focus`,
 * `:active`, `:checked`, `:disabled`) return false for now — print rendering
 * doesn't observe those states. The matcher leaves them as forward-compat
 * extension points so the cascade can drop the unmatching rule cleanly.
 */
final class Matcher
{
    /**
     * Does any selector in `$list` match `$element`?
     */
    public function listMatches(SelectorList $list, MatchableElement $element): bool
    {
        foreach ($list->selectors as $sel) {
            if ($this->complexMatches($sel, $element)) {
                return true;
            }
        }
        return false;
    }

    public function complexMatches(ComplexSelector $complex, MatchableElement $element): bool
    {
        $n = count($complex->compounds);
        if ($n === 0) {
            return false;
        }
        // Walk right-to-left starting from the subject (last compound).
        return $this->matchAt($complex->compounds, $n - 1, $element);
    }

    /**
     * @param list<CompoundSelectorWithCombinator> $compounds
     */
    private function matchAt(array $compounds, int $index, MatchableElement $element): bool
    {
        $part = $compounds[$index];
        if (!$this->compoundMatches($part->compound, $element)) {
            return false;
        }
        if ($index === 0) {
            return true;
        }
        $combinator = $compounds[$index - 1]->combinatorToNext;
        $nextIndex = $index - 1;
        switch ($combinator) {
            case Combinator::Descendant:
                for ($p = $element->parentElement(); $p !== null; $p = $p->parentElement()) {
                    if ($this->matchAt($compounds, $nextIndex, $p)) {
                        return true;
                    }
                }
                return false;
            case Combinator::Child:
                $p = $element->parentElement();
                return $p !== null && $this->matchAt($compounds, $nextIndex, $p);
            case Combinator::NextSibling:
                $s = $element->previousElementSibling();
                return $s !== null && $this->matchAt($compounds, $nextIndex, $s);
            case Combinator::SubsequentSibling:
                for ($s = $element->previousElementSibling(); $s !== null; $s = $s->previousElementSibling()) {
                    if ($this->matchAt($compounds, $nextIndex, $s)) {
                        return true;
                    }
                }
                return false;
            case Combinator::Column:
                // Column combinator is rarely used and requires table-layout
                // semantics; treat as non-matching for now.
                return false;
            case null:
                // Should not occur for $index > 0.
                return false;
        }
        return false;
    }

    public function compoundMatches(CompoundSelector $compound, MatchableElement $element): bool
    {
        foreach ($compound->components as $simple) {
            if (!$this->simpleMatches($simple, $element)) {
                return false;
            }
        }
        return true;
    }

    public function simpleMatches(SimpleSelector $simple, MatchableElement $element): bool
    {
        return match (true) {
            $simple instanceof TypeSelector => $this->matchType($simple, $element),
            $simple instanceof UniversalSelector => true,
            $simple instanceof IdSelector => $element->elementId() === $simple->id,
            $simple instanceof ClassSelector => in_array(
                $simple->className,
                $element->classes(),
                true,
            ),
            $simple instanceof AttributeSelector => $this->matchAttribute($simple, $element),
            $simple instanceof PseudoClassSelector => $this->matchPseudoClass($simple, $element),
            $simple instanceof PseudoElementSelector => $this->matchPseudoElement($simple, $element),
            default => false,
        };
    }

    private function matchType(TypeSelector $sel, MatchableElement $el): bool
    {
        if (strcasecmp($sel->localName, $el->localName()) !== 0) {
            return false;
        }
        if ($sel->namespacePrefix === null || $sel->namespacePrefix === '*') {
            return true;
        }
        // Resolved namespace check would consult an @namespace registry;
        // 1D.2 ships the structural matcher and treats unknown prefixes as
        // pass-through. Empty prefix `|tag` requires null namespace.
        if ($sel->namespacePrefix === '') {
            return $el->namespaceUri() === null;
        }
        return true;
    }

    private function matchAttribute(AttributeSelector $sel, MatchableElement $el): bool
    {
        if ($sel->matchType === AttributeMatchType::Exists) {
            return $el->hasAttribute($sel->name);
        }
        $value = $el->getAttributeValue($sel->name);
        if ($value === null) {
            return false;
        }
        $target = $sel->value ?? '';
        if ($sel->caseInsensitive) {
            $value = strtolower($value);
            $target = strtolower($target);
        }
        if ($sel->matchType === AttributeMatchType::Equals) {
            return $value === $target;
        }
        if ($sel->matchType === AttributeMatchType::Includes) {
            return $this->wordListIncludes($value, $target);
        }
        if ($sel->matchType === AttributeMatchType::DashMatch) {
            return $value === $target || str_starts_with($value, $target . '-');
        }
        if ($target === '') {
            return false;
        }
        if ($sel->matchType === AttributeMatchType::PrefixMatch) {
            return str_starts_with($value, $target);
        }
        if ($sel->matchType === AttributeMatchType::SuffixMatch) {
            return str_ends_with($value, $target);
        }
        return str_contains($value, $target);
    }

    private function wordListIncludes(string $value, string $token): bool
    {
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            return false;
        }
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        return in_array($token, $parts, true);
    }

    private function matchPseudoClass(PseudoClassSelector $sel, MatchableElement $el): bool
    {
        $name = strtolower($sel->name);
        return match ($name) {
            'root' => $el->parentElement() === null,
            'empty' => $el->elementChildren() === [],
            'first-child' => $el->indexAmongSiblings() === 1,
            'last-child' => $el->indexAmongSiblingsFromEnd() === 1,
            'only-child' => $el->indexAmongSiblings() === 1 && $el->indexAmongSiblingsFromEnd() === 1,
            'first-of-type' => $el->indexAmongTypeSiblings() === 1,
            'last-of-type' => $el->indexAmongTypeSiblingsFromEnd() === 1,
            'only-of-type' => $el->indexAmongTypeSiblings() === 1 && $el->indexAmongTypeSiblingsFromEnd() === 1,
            'nth-child' => $this->matchNth($sel, $el, $el->indexAmongSiblings()),
            'nth-last-child' => $this->matchNth($sel, $el, $el->indexAmongSiblingsFromEnd()),
            'nth-of-type' => $this->matchNth($sel, $el, $el->indexAmongTypeSiblings()),
            'nth-last-of-type' => $this->matchNth($sel, $el, $el->indexAmongTypeSiblingsFromEnd()),
            'not' => $sel->arguments !== null
                && !$this->listMatches($sel->arguments, $el),
            'is', 'matches' => $sel->arguments !== null
                && $this->listMatches($sel->arguments, $el),
            'where' => $sel->arguments !== null
                && $this->listMatches($sel->arguments, $el),
            'has' => $sel->arguments !== null
                && $this->hasMatches($sel->arguments, $el),
            'scope' => false,
            'lang' => $this->matchLang($sel, $el),
            'dir' => false,
            'host', 'host-context' => false,
            // UI-state pseudos: print medium can't observe them. Cascade
            // drops the rule cleanly when these don't match.
            'hover', 'focus', 'focus-within', 'focus-visible', 'active',
            'checked', 'disabled', 'enabled', 'required', 'optional',
            'read-only', 'read-write', 'placeholder-shown', 'default',
            'valid', 'invalid', 'target', 'visited', 'link' => false,
            default => false,
        };
    }

    private function matchNth(PseudoClassSelector $sel, MatchableElement $el, int $index): bool
    {
        if ($sel->anPlusB === null) {
            return false;
        }
        if (!$sel->anPlusB->matches($index)) {
            return false;
        }
        if ($sel->arguments !== null && !$sel->arguments->isEmpty()) {
            // `... of S` form — additionally require the element to match S.
            return $this->listMatches($sel->arguments, $el);
        }
        return true;
    }

    private function matchLang(PseudoClassSelector $sel, MatchableElement $el): bool
    {
        $arg = $sel->argText !== null ? strtolower(trim($sel->argText)) : '';
        if ($arg === '') {
            return false;
        }
        // Walk ancestor `lang` attributes — closest one wins.
        for ($n = $el; $n !== null; $n = $n->parentElement()) {
            $lang = $n->getAttributeValue('lang') ?? $n->getAttributeValue('xml:lang');
            if ($lang === null) {
                continue;
            }
            $lang = strtolower($lang);
            if ($lang === $arg || str_starts_with($lang, $arg . '-')) {
                return true;
            }
            return false;
        }
        return false;
    }

    private function hasMatches(SelectorList $list, MatchableElement $el): bool
    {
        // `:has(s)` matches when at least one descendant matches s.
        $stack = $el->elementChildren();
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($this->listMatches($list, $node)) {
                return true;
            }
            foreach ($node->elementChildren() as $c) {
                $stack[] = $c;
            }
        }
        return false;
    }

    private function matchPseudoElement(PseudoElementSelector $sel, MatchableElement $el): bool
    {
        // Pseudo-elements are virtual; the cascade attaches their rules to
        // generated boxes rather than to host elements. From the perspective
        // of "does this element match the selector," they're match-true on
        // the element they're attached to. Refined in the cascade in 1D.3.
        return true;
    }
}
