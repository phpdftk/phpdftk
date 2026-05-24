<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Pseudo-class selector per Selectors 4 §3.5: `:hover`, `:first-child`,
 * `:nth-child(2n+1)`, `:not(...)`, `:is(...)`, `:where(...)`, `:has(...)`,
 * `:host`, `:host(...)`, `:host-context(...)`, `:lang(en)`, etc.
 *
 * Specificity per Selectors 4 §16:
 *  - `:where()` always contributes (0,0,0).
 *  - `:is()` / `:not()` / `:has()` contribute the max specificity of their
 *    argument selector list.
 *  - `:nth-child(... of S)` and `:nth-last-child(... of S)` similarly take
 *    the max of S, then add (0, 1, 0) for the pseudo itself.
 *  - All other pseudo-classes contribute (0, 1, 0).
 *
 * `arguments` carries the parsed inner SelectorList for the logical/has/is
 * family (and `:nth-*-of-type` selector lists), or null when the pseudo is
 * argument-less. `anPlusB` carries the parsed An+B coefficients for the
 * nth-* family (always-null otherwise). Free-form string args (e.g.
 * `:lang(en)`, `:dir(ltr)`) live in `argText`.
 */
final readonly class PseudoClassSelector extends SimpleSelector
{
    public function __construct(
        public string $name,
        public ?SelectorList $arguments = null,
        public ?AnPlusB $anPlusB = null,
        public ?string $argText = null,
    ) {}

    public function specificity(): Specificity
    {
        $lower = strtolower($this->name);

        if ($lower === 'where') {
            return new Specificity();
        }
        if (in_array($lower, ['is', 'not', 'has'], true)) {
            return $this->argumentMaxSpecificity();
        }
        $base = new Specificity(0, 1, 0);
        if (in_array($lower, ['nth-child', 'nth-last-child'], true)) {
            return $base->add($this->argumentMaxSpecificity());
        }
        return $base;
    }

    private function argumentMaxSpecificity(): Specificity
    {
        if ($this->arguments === null || $this->arguments->selectors === []) {
            return new Specificity();
        }
        $max = $this->arguments->selectors[0]->specificity();
        foreach (array_slice($this->arguments->selectors, 1) as $sel) {
            $max = $max->max($sel->specificity());
        }
        return $max;
    }

    public function toString(): string
    {
        if ($this->arguments !== null) {
            $parts = [];
            foreach ($this->arguments->selectors as $sel) {
                $parts[] = $sel->toString();
            }
            return ':' . $this->name . '(' . implode(', ', $parts) . ')';
        }
        if ($this->anPlusB !== null) {
            return ':' . $this->name . '(' . $this->anPlusB->toString() . ')';
        }
        if ($this->argText !== null) {
            return ':' . $this->name . '(' . $this->argText . ')';
        }
        return ':' . $this->name;
    }
}
