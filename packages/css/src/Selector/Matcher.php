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
        // CSS Selectors 4 §6.6 — attribute selectors are case-
        // sensitive by default but HTML user agents treat a fixed
        // list of attributes as ASCII-case-insensitive. Tri-state:
        //   explicit `i` (caseInsensitive=true)   → case-insensitive
        //   explicit `s` (caseInsensitive=false)  → case-sensitive
        //   no flag       (caseInsensitive=null)  → HTML list lookup
        $caseInsensitive = match ($sel->caseInsensitive) {
            true => true,
            false => false,
            null => $sel->value !== null
                && $this->isHtmlCaseInsensitiveAttribute($el, $sel->name),
        };
        if ($caseInsensitive) {
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
            'nth-child' => $this->matchNthChild($sel, $el, fromEnd: false),
            'nth-last-child' => $this->matchNthChild($sel, $el, fromEnd: true),
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
            // CSS Selectors 4 §13.5 — `:scope` matches the scoping
            // element. Without an explicit @scope root, the
            // document's root element is the scope, so treat
            // `:scope` like `:root`.
            'scope' => $el->parentElement() === null,
            'lang' => $this->matchLang($sel, $el),
            // CSS Selectors 4 §15.2 — `:dir(ltr)` / `:dir(rtl)`
            // matches when the closest ancestor with a `dir=` attr
            // declares the requested direction (HTML §3.2.6.4).
            'dir' => $this->matchDir($sel, $el),
            'host', 'host-context' => false,
            // CSS Selectors 4 §10.4 — `:link` / `:any-link` match an
            // <a>, <area>, or <link> element with an href attribute.
            // `:any-link` also matches `:visited`; print medium can't
            // observe visit state so the two collapse here.
            'link', 'any-link' => $this->matchLink($el),
            // CSS Selectors 4 §11.2 — `:disabled` / `:enabled` are
            // static attribute-driven on form controls.
            'disabled' => $this->matchDisabled($el),
            'enabled' => $this->isFormControl($el) && !$this->matchDisabled($el),
            // CSS Selectors 4 §11.3 — `:checked` matches a checkbox /
            // radio / option element with the `checked` (or `selected`
            // for <option>) attribute set. Static; doesn't need a
            // user-state observation.
            'checked' => $this->matchChecked($el),
            // CSS Selectors 4 §11.5 — `:required` / `:optional` reflect
            // the static `required` attribute on form controls.
            'required' => $this->isFormControl($el) && $el->hasAttribute('required'),
            'optional' => $this->isFormControl($el) && !$el->hasAttribute('required'),
            // CSS Selectors 4 §11.6 — `:read-only` / `:read-write`
            // reflect the static `readonly` / `disabled` state.
            'read-only' => $this->matchReadOnly($el),
            'read-write' => $this->isFormControl($el) && !$this->matchReadOnly($el),
            // Remaining UI-state pseudos: print medium can't observe
            // them. Cascade drops the rule cleanly when these don't
            // match.
            'hover', 'focus', 'focus-within', 'focus-visible', 'active',
            'placeholder-shown', 'default',
            'valid', 'invalid', 'target', 'visited',
            'user-valid', 'user-invalid' => false,
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

    /**
     * CSS Selectors 4 §6.4 — `:nth-child(An+B [of S]?)` /
     * `:nth-last-child(...)`. When the optional `of S` clause is
     * present the An+B index is computed over the subset of
     * siblings that also match S, not over all element children.
     */
    private function matchNthChild(PseudoClassSelector $sel, MatchableElement $el, bool $fromEnd): bool
    {
        if ($sel->anPlusB === null) {
            return false;
        }
        if ($sel->arguments === null || $sel->arguments->isEmpty()) {
            $index = $fromEnd
                ? $el->indexAmongSiblingsFromEnd()
                : $el->indexAmongSiblings();
            return $sel->anPlusB->matches($index);
        }
        // `of S` form: the element must match S, and its index
        // within the subset of S-matching siblings is what An+B
        // compares against.
        if (!$this->listMatches($sel->arguments, $el)) {
            return false;
        }
        $parent = $el->parentElement();
        if ($parent === null) {
            return false;
        }
        $siblings = $parent->elementChildren();
        if ($fromEnd) {
            $siblings = array_reverse($siblings);
        }
        $index = 0;
        foreach ($siblings as $sibling) {
            if (!$this->listMatches($sel->arguments, $sibling)) {
                continue;
            }
            $index++;
            if ($sibling === $el) {
                return $sel->anPlusB->matches($index);
            }
        }
        return false;
    }

    /**
     * Per HTML §3.2.6.1 (and the Selectors 4 §6.6 cross-reference),
     * a fixed list of attributes are matched case-insensitively when
     * the element is in the HTML namespace. Anything outside this
     * list keeps the default case-sensitive comparison.
     */
    private function isHtmlCaseInsensitiveAttribute(MatchableElement $el, string $name): bool
    {
        $ns = $el->namespaceUri();
        if ($ns !== null && $ns !== 'http://www.w3.org/1999/xhtml') {
            return false;
        }
        static $list = null;
        if ($list === null) {
            $list = array_flip([
                'accept', 'accept-charset', 'align', 'alink', 'axis', 'bgcolor',
                'charset', 'checked', 'clear', 'codetype', 'color', 'compact',
                'declare', 'defer', 'dir', 'direction', 'disabled', 'enctype',
                'face', 'frame', 'frameborder', 'hreflang', 'http-equiv',
                'lang', 'language', 'link', 'media', 'method', 'multiple',
                'nohref', 'noresize', 'noshade', 'nowrap', 'readonly', 'rel',
                'rev', 'rules', 'scope', 'scrolling', 'selected', 'shape',
                'target', 'text', 'type', 'valign', 'valuetype', 'vlink',
            ]);
        }
        return isset($list[strtolower($name)]);
    }

    /**
     * Form-control element check used by the static UI-state
     * pseudos (`:enabled`, `:required`, `:optional`, `:read-write`).
     * Per HTML §15.3 the controllable elements are the form
     * elements that accept these attributes.
     */
    private function isFormControl(MatchableElement $el): bool
    {
        return in_array(strtolower($el->localName()), [
            'input', 'select', 'textarea', 'button',
            'fieldset', 'option', 'optgroup',
        ], true);
    }

    /**
     * `:disabled` — the element has the `disabled` attribute
     * present (HTML §15.3) AND is a form control.
     */
    private function matchDisabled(MatchableElement $el): bool
    {
        return $this->isFormControl($el) && $el->hasAttribute('disabled');
    }

    /**
     * `:checked` — checkboxes/radios with the `checked` attribute,
     * or `<option>` elements with the `selected` attribute.
     */
    private function matchChecked(MatchableElement $el): bool
    {
        $tag = strtolower($el->localName());
        if ($tag === 'input') {
            $type = strtolower($el->getAttributeValue('type') ?? 'text');
            if ($type === 'checkbox' || $type === 'radio') {
                return $el->hasAttribute('checked');
            }
            return false;
        }
        if ($tag === 'option') {
            return $el->hasAttribute('selected');
        }
        return false;
    }

    /**
     * `:read-only` — read-write controls become read-only when they
     * carry `readonly` or `disabled`. Non-form-control elements are
     * read-only by default (since they're not editable at all),
     * matching browser behaviour.
     */
    private function matchReadOnly(MatchableElement $el): bool
    {
        if (!$this->isFormControl($el)) {
            return true;
        }
        return $el->hasAttribute('readonly') || $el->hasAttribute('disabled');
    }

    /**
     * CSS Selectors 4 §10.4.1 — `:link`. Matches an HTML
     * `<a>`, `<area>`, or `<link>` element that carries an
     * `href` attribute.
     */
    private function matchLink(MatchableElement $el): bool
    {
        $tag = strtolower($el->localName());
        if (!in_array($tag, ['a', 'area', 'link'], true)) {
            return false;
        }
        return $el->getAttributeValue('href') !== null;
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

    /**
     * CSS Selectors 4 §15.2 — `:dir(ltr)` / `:dir(rtl)`. The
     * direction comes from the closest ancestor `dir=` attribute
     * (HTML §3.2.6.4). When no ancestor sets dir, defaults to
     * `ltr` per the HTML spec.
     */
    private function matchDir(PseudoClassSelector $sel, MatchableElement $el): bool
    {
        $arg = $sel->argText !== null ? strtolower(trim($sel->argText)) : '';
        if ($arg !== 'ltr' && $arg !== 'rtl' && $arg !== 'auto') {
            return false;
        }
        for ($n = $el; $n !== null; $n = $n->parentElement()) {
            $dir = $n->getAttributeValue('dir');
            if ($dir === null) {
                continue;
            }
            $dir = strtolower(trim($dir));
            if ($dir === 'ltr' || $dir === 'rtl' || $dir === 'auto') {
                return $dir === $arg;
            }
            // Invalid value — fall through to next ancestor.
        }
        // No dir set anywhere — HTML default is ltr.
        return $arg === 'ltr';
    }

    private function hasMatches(SelectorList $list, MatchableElement $el): bool
    {
        // `:has(s)` is a relative selector — its inner ComplexSelectors
        // may carry a `leadingCombinator` that constrains the search
        // (CSS Selectors 4 §17.5). Dispatch per selector:
        //
        //   Child (>)          → only direct children of $el
        //   NextSibling (+)    → the immediately-following sibling
        //   SubsequentSibling  → siblings after $el in the tree
        //   Descendant (null)  → all descendants (v1 behaviour)
        //
        // The list as a whole matches if any of its branches matches.
        foreach ($list->selectors as $branch) {
            $combinator = $branch->leadingCombinator;
            $branchList = new SelectorList($branch->text, [$branch]);
            if ($this->relativeSelectorMatches($branchList, $branch, $combinator, $el)) {
                return true;
            }
        }
        return false;
    }

    private function relativeSelectorMatches(
        SelectorList $list,
        ComplexSelector $branch,
        ?Combinator $combinator,
        MatchableElement $el,
    ): bool {
        switch ($combinator) {
            case Combinator::Child:
                foreach ($el->elementChildren() as $child) {
                    if ($this->listMatches($list, $child)) {
                        return true;
                    }
                }
                return false;
            case Combinator::NextSibling:
                $next = $el->nextElementSibling();
                if ($next === null) {
                    return false;
                }
                return $this->listMatches($list, $next);
            case Combinator::SubsequentSibling:
                $sib = $el->nextElementSibling();
                while ($sib !== null) {
                    if ($this->listMatches($list, $sib)) {
                        return true;
                    }
                    $sib = $sib->nextElementSibling();
                }
                return false;
            case Combinator::Descendant:
            case null:
            default:
                unset($branch);
                // Walk all descendants.
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
