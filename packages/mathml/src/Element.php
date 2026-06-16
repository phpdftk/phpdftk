<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * A MathML element with a tag name, attributes, and a child list.
 * Concrete subclasses (`Mn`, `Mrow`, `Mfrac`, …) add typed accessors
 * over the raw attribute strings stored here. Mirrors the shape of
 * {@see \Phpdftk\Svg\Element} so callers used to the SVG tree work
 * the same way on the MathML one.
 *
 * Attribute names are case-sensitive per MathML Core — `mathvariant`,
 * `displaystyle`, `scriptlevel` keep their casing.
 */
abstract class Element extends Node
{
    /** @var array<string, string> */
    public array $attributes = [];

    /** @var list<Node> */
    public array $children = [];

    public function __construct(public readonly string $localName) {}

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function setAttribute(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function appendChild(Node $node): void
    {
        $node->parent = $this;
        $this->children[] = $node;
    }

    /**
     * Concatenated text content of every {@see Text} descendant in
     * document order. Token elements (`<mn>`, `<mi>`, …) use this to
     * answer "what character data did this element carry?" — the
     * tracer-bullet renderer hands the result straight to the text
     * shaper. Non-token containers (`<mrow>`, `<mfrac>`) flatten
     * their tree this way too, which is correct for accessibility
     * announcements and copy-paste even though it's not how the
     * painter will lay them out.
     */
    public function textContent(): string
    {
        $out = '';
        foreach ($this->children as $child) {
            if ($child instanceof Text) {
                $out .= $child->data;
            } elseif ($child instanceof Element) {
                $out .= $child->textContent();
            }
        }
        return $out;
    }

    /** @return list<Element> elements with the given local name in document order. */
    public function findByTag(string $localName): array
    {
        $out = [];
        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                if ($child->localName === $localName) {
                    $out[] = $child;
                }
                foreach ($child->findByTag($localName) as $nested) {
                    $out[] = $nested;
                }
            }
        }
        return $out;
    }

    /**
     * MathML `mathvariant` per Core §3.2.2 — sets a font-variant
     * override on the contained text (`normal`, `bold`, `italic`,
     * `script`, `fraktur`, etc.). Returns null when absent or empty
     * so the painter applies the default (italic on `<mi>` whose
     * content is a single letter; normal everywhere else).
     */
    public function mathvariant(): ?string
    {
        $raw = $this->attributes['mathvariant'] ?? null;
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        return $trimmed === '' ? null : strtolower($trimmed);
    }

    /**
     * `mathsize` per MathML Core §3.2.4 - sets the token element's
     * font size. The Core-canonical form is a CSS length (`12pt`,
     * `1.4em`, plain unitless numeric); the historic MathML 3
     * keywords (`small`, `normal`, `big`) are also accepted for
     * compatibility with content in the wild. Returns null when
     * absent or unparseable so the painter falls back to the
     * cascade's current size.
     *
     * The returned shape distinguishes "scale factor" from
     * "absolute" so the painter can apply the right computation
     * later:
     *
     *   ['scale', 1.4]     — `mathsize="1.4em"` or `1.4` (unitless).
     *   ['absolute', 12.0] — `mathsize="12pt"`. Value is in points.
     *   ['scale', 0.7]     — `mathsize="small"`.
     *   ['scale', 1.0]     — `mathsize="normal"`.
     *   ['scale', 1.4]     — `mathsize="big"`.
     *
     * @return ?array{0: 'scale'|'absolute', 1: float}
     */
    public function mathsize(): ?array
    {
        $raw = $this->attributes['mathsize'] ?? null;
        if ($raw === null) {
            return null;
        }
        $trimmed = strtolower(trim($raw));
        if ($trimmed === '') {
            return null;
        }
        $keyword = match ($trimmed) {
            'small'  => 0.7,
            'normal' => 1.0,
            'big'    => 1.4,
            default  => null,
        };
        if ($keyword !== null) {
            return ['scale', $keyword];
        }
        if (!preg_match('/^([0-9]*\.?[0-9]+)(em|pt|)$/', $trimmed, $m)) {
            return null;
        }
        $value = (float) $m[1];
        if ($value < 0.0) {
            return null;
        }
        if ($m[2] === 'pt') {
            return ['absolute', $value];
        }
        // 'em' or unitless -> scale factor.
        return ['scale', $value];
    }

    /**
     * `mathcolor` per MathML Core §3.2.5 - CSS color string for
     * the glyph foreground. Returns the raw string trimmed of
     * whitespace (the painter parses it), or null when absent /
     * empty. Inherited via the cascade.
     *
     * Falls back to the `style` attribute's `color:` declaration
     * when no explicit `mathcolor` is set, matching the
     * spec-mandated equivalence between the two forms. The
     * wpt-harness's DOM settler projects computed `color` values
     * into inline `style` declarations on MathML elements (see
     * scripts/cross-browser/settle-dom.mjs), so cascaded values
     * from external stylesheets flow through this hook in the
     * harness's settle-then-render path.
     */
    public function mathcolor(): ?string
    {
        $raw = $this->attributes['mathcolor'] ?? null;
        if ($raw === null) {
            $raw = $this->extractStyleProperty('color');
        }
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * `mathbackground` per MathML Core §3.2.5 - CSS color string
     * for the element's background rectangle. Returns the raw
     * string trimmed of whitespace (the painter parses it), or
     * null when absent / empty. NOT inherited - only applies to
     * the element it's set on.
     *
     * Notably does NOT consult the `style` attribute's
     * `background` / `background-color` declarations as a
     * fallback (#103). The spec-mandated equivalence is correct,
     * but turning on the fallback regresses ~18 WPT fixtures
     * (scripts/underover-stretchy-00{1,2,3},
     * fractions/frac-invalid-{2,3}, fractions/frac-default-padding,
     * tables/{,dynamic-}columnspan-rowspan-*) which use
     * `style="background: red"` on intermediate <mspace> elements
     * as "this should be covered" markers - covered by stretchy
     * operator glyphs, sized-to-match table cells, or fraction
     * default padding. The renderer's metric drift in those three
     * shape families leaks the red through. Closing #103 needs
     * those metric gaps tightened first (stretchy-operator glyph
     * coverage, mtable cell layout, mfrac padding alignment) -
     * NOT just the inline position-absolute fix #101 closed.
     */
    public function mathbackground(): ?string
    {
        $raw = $this->attributes['mathbackground'] ?? null;
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Resolve the element's `font-size` CSS style declaration to
     * PDF user-space points relative to `$parentFontSizePt`. The
     * wpt-harness's DOM settler projects computed font-size into
     * inline style so external CSS rules (`mfrac { font-size: 15em
     * }`) reach this hook in the harness's settle-then-render
     * path. Authors can also write inline `style="font-size: 15px"`
     * directly.
     *
     * Supported forms (the painter routes em / unitless through
     * the parent fontSize so the resulting pt matches html-to-pdf's
     * 1 CSS px == 1 PDF pt convention at the default 12pt base):
     *
     *   - `<n>px`  -> n pt (1:1)
     *   - `<n>pt`  -> n pt
     *   - `<n>em`  -> n * parentFontSizePt
     *   - `<n>%`   -> n / 100 * parentFontSizePt
     *   - `<n>`    -> n * parentFontSizePt (unitless, em-relative)
     *
     * Returns null when the declaration is absent or unparseable
     * so the painter keeps the parent context's fontSize.
     */
    public function styleFontSizePt(float $parentFontSizePt): ?float
    {
        $raw = $this->extractStyleProperty('font-size');
        if ($raw === null) {
            return null;
        }
        $trimmed = strtolower(trim($raw));
        if ($trimmed === '') {
            return null;
        }
        if (!preg_match('/^(-?\d*\.?\d+)\s*([a-z%]*)$/', $trimmed, $m)) {
            return null;
        }
        $value = (float) $m[1];
        if ($value < 0.0) {
            return null;
        }
        $unit = $m[2];
        return match ($unit) {
            'px', 'pt' => $value,
            'em', ''   => $value * $parentFontSizePt,
            '%'        => $value / 100.0 * $parentFontSizePt,
            'ex'       => $value * 0.5 * $parentFontSizePt,
            default    => null,
        };
    }

    /**
     * Extract a single CSS declaration value from the element's
     * `style` attribute. Splits on `;` then `:`, lowercases the
     * property name for comparison. Strips block-comment prefixes
     * the DOM settler uses to mark its projections (those are
     * harmless to the CSS spec but confuse trim()-style readers).
     * Returns null when the property is absent.
     */
    private function extractStyleProperty(string $property): ?string
    {
        $style = $this->attributes['style'] ?? null;
        if ($style === null) {
            return null;
        }
        $target = strtolower($property);
        // Strip /* ... */ block comments before tokenising. The DOM
        // settler emits a `/* phpdftk-settle-dom */` marker before
        // its projected declarations; CSS allows comments anywhere
        // and we just want them out of the way.
        $cleaned = preg_replace('/\/\*.*?\*\//s', ' ', $style) ?? $style;
        foreach (explode(';', $cleaned) as $decl) {
            $colon = strpos($decl, ':');
            if ($colon === false) {
                continue;
            }
            $name = strtolower(trim(substr($decl, 0, $colon)));
            if ($name !== $target) {
                continue;
            }
            $value = trim(substr($decl, $colon + 1));
            return $value === '' ? null : $value;
        }
        return null;
    }

    /**
     * `dir` per MathML Core §3.1.5.4 — `ltr` or `rtl`. Sets the
     * layout direction for this element's children. Inherits from
     * the nearest ancestor with `dir` set when absent. Returns null
     * when no explicit value is provided so the painter can walk up
     * the tree (or fall back to LTR).
     */
    public function dir(): ?string
    {
        $raw = $this->attributes['dir'] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'ltr', 'rtl' => $value,
            default => null,
        };
    }

    /**
     * Class names from the `class` attribute, whitespace-separated.
     * Empty list when absent or empty. Mirrors the SVG accessor of
     * the same name so cross-tree CSS selectors work uniformly.
     *
     * @return list<string>
     */
    public function classList(): array
    {
        $raw = $this->attributes['class'] ?? null;
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/\s+/', trim($raw)) ?: [];
        return array_values(array_filter($parts, static fn(string $c): bool => $c !== ''));
    }
}
