# Cross-package contracts

Phase 0.5 deliverable for the HTML/SVG rendering engine. Gates every Phase 1 sub-phase.

This document pins the **public** types crossing package boundaries in `phpdftk/css`, `phpdftk/text`, `phpdftk/html`, `phpdftk/svg`, `phpdftk/html-to-pdf`, `phpdftk/svg-to-pdf`, and the additions to `phpdftk/filesystem`. Per the strict-semver-from-1.0 decision in `html-and-svg.md`, any change to a signature here forces a major bump on every consumer simultaneously. Internal APIs are out of scope — they're designed per-sub-phase.

**Design principles for this surface:**

1. **Prefer fewer types, more flexibility internally.** Each public interface is a long-term commitment.
2. **Prefer value objects over service objects.** Immutable, easy to test, easy to compare.
3. **Use PHP 8.4 features.** `readonly` classes, typed constants, asymmetric visibility, enums with methods.
4. **Reject WHATWG DOM warts.** We're not implementing the JavaScript DOM — we expose what a PHP renderer needs. No live collections, no mutation observers, no event model.
5. **Names match the spec where unambiguous; renamed where the spec name is misleading or JS-flavoured.**
6. **All collections that escape internal code are immutable `array<int, T>` or `array<string, T>`.** Iterators only where the collection size is unbounded (parser tokens, etc.).

## Table of contents

- [`phpdftk/html` — DOM types](#phpdftkhtml--dom-types)
- [`phpdftk/css` — CSS values, rules, computed style](#phpdftkcss--css-values-rules-computed-style)
- [`phpdftk/text` — shaping, line breaking, bidi](#phpdftktext--shaping-line-breaking-bidi)
- [`phpdftk/svg` — SVG parsed tree](#phpdftksvg--svg-parsed-tree)
- [`phpdftk/filesystem` — ResourceLoader additions](#phpdftkfilesystem--resourceloader-additions)
- [`phpdftk/html-to-pdf` — user entry points](#phpdftkhtml-to-pdf--user-entry-points)
- [`phpdftk/svg-to-pdf` — user entry points](#phpdftksvg-to-pdf--user-entry-points)
- [Resolved decisions](#resolved-decisions)

---

## `phpdftk/html` — DOM types

Namespace: `Phpdftk\Html\Dom\`

WHATWG tree construction mutates a DOM during parse (template elements, foster parenting, the adoption agency algorithm, **declarative shadow DOM**) — so the DOM and parser ship together. The public DOM is intentionally smaller than the WHATWG DOM Living Standard: no live `HTMLCollection`, no `NodeIterator`/`TreeWalker`, no `MutationObserver`, no `Range`, no `Event`/`EventTarget`, no custom elements, no `innerHTML`/`outerHTML` (use `Serializer` from the same package).

**Shadow DOM is included** (minimally) because WHATWG-conformant HTML parsing handles `<template shadowrootmode="open|closed">` declaratively — see [Declarative Shadow DOM](#declarative-shadow-dom) below. The Phase-1 contract surface is just enough to represent and serialize shadow roots; full encapsulation (slot distribution, `:host`/`::slotted`/`::part`, shadow-scoped selector matching) is Phase 2.

### Node hierarchy

```php
abstract class Node
{
    public Document $ownerDocument { get; }
    public ?Element $parentElement { get; }
    public ?Node $parentNode { get; }
    public ?Node $previousSibling { get; }
    public ?Node $nextSibling { get; }
    public ?Node $firstChild { get; }
    public ?Node $lastChild { get; }

    /** @return list<Node> snapshot, never live */
    public function childNodes(): array;

    public function nodeType(): NodeType; // enum
    public function nodeName(): string;   // e.g. "DIV", "#text", "#comment"

    public function textContent(): string;       // concatenation of descendant text
    public function setTextContent(string $text): void;

    public function appendChild(Node $child): void;
    public function insertBefore(Node $child, ?Node $reference): void;
    public function removeChild(Node $child): void;
    public function replaceChild(Node $newChild, Node $oldChild): void;

    public function clone(bool $deep = true): static;
}

enum NodeType
{
    case Element;
    case Text;
    case Comment;
    case Document;
    case DocumentType;
    case DocumentFragment;
    case ProcessingInstruction;
}
```

### Document

```php
final class Document extends Node
{
    public ?DocumentType $doctype { get; }
    public ?Element $documentElement { get; }       // root <html>
    public ?Element $head { get; }
    public ?Element $body { get; }
    public DocumentMode $mode { get; }              // quirks | limited-quirks | no-quirks
    public ?string $title { get; }                  // <title> text content
    public ?string $characterSet { get; }           // meta charset, defaults to "UTF-8"

    public function createElement(string $localName, string $namespace = self::HTML_NS): Element;
    public function createTextNode(string $data): Text;
    public function createComment(string $data): Comment;
    public function createDocumentFragment(): DocumentFragment;

    /** @return list<Element> */
    public function getElementsByTagName(string $localName): array;

    public function getElementById(string $id): ?Element;

    public const string HTML_NS = 'http://www.w3.org/1999/xhtml';
    public const string SVG_NS = 'http://www.w3.org/2000/svg';
    public const string MATHML_NS = 'http://www.w3.org/1998/Math/MathML';
}

enum DocumentMode { case Quirks; case LimitedQuirks; case NoQuirks; }
```

### Element

```php
final class Element extends Node
{
    public string $localName { get; }               // e.g. "div", "table"
    public string $namespaceURI { get; }            // HTML_NS / SVG_NS / MATHML_NS
    public ?string $prefix { get; }                 // typically null in HTML
    public string $tagName { get; }                 // uppercase in HTML, prefix:localName in foreign

    public ?string $id { get; }                     // value of id attribute, normalized
    public ClassList $classList { get; }            // see below

    /** @return list<Attr> */
    public function attributes(): array;

    public function hasAttribute(string $name): bool;
    public function getAttribute(string $name): ?string;
    public function setAttribute(string $name, string $value): void;
    public function removeAttribute(string $name): void;

    /** @return list<Element> direct element children only */
    public function children(): array;

    /** @return list<Element> via depth-first traversal */
    public function getElementsByTagName(string $localName): array;

    /**
     * Returns matching elements via the CSS selector engine.
     * Implemented by phpdftk/css's selector engine — phpdftk/html depends
     * on phpdftk/css for this one method.
     *
     * @return list<Element>
     */
    public function querySelectorAll(string $selector): array;
    public function querySelector(string $selector): ?Element;
    public function matches(string $selector): bool;
    public function closest(string $selector): ?Element;

    /**
     * The shadow root attached to this element, if any.
     * Populated by the parser when it encounters <template shadowrootmode>
     * inside this element (declarative shadow DOM). Null on non-host elements.
     */
    public ?ShadowRoot $shadowRoot { get; }

    /**
     * Attach a shadow root to this element. Used by the parser during DSD
     * tree construction; rarely needed by user code (treat as internal).
     * Throws if this element already has a shadow root, or if its tag is not
     * shadow-host-eligible per WHATWG.
     */
    public function attachShadow(ShadowRootMode $mode, ShadowRootInit $init = new ShadowRootInit()): ShadowRoot;
}

final class ClassList
{
    public function contains(string $token): bool;
    public function add(string ...$tokens): void;
    public function remove(string ...$tokens): void;
    /** @return list<string> */
    public function values(): array;
}
```

### Attr

```php
final readonly class Attr
{
    public function __construct(
        public string $localName,
        public string $value,
        public string $namespaceURI = Document::HTML_NS,
        public ?string $prefix = null,
    ) {}
}
```

### Text / Comment / DocumentType / DocumentFragment

```php
final class Text extends Node
{
    public string $data; // mutable

    public function splitText(int $offset): Text;
}

final class Comment extends Node
{
    public string $data;
}

final readonly class DocumentType extends Node
{
    public function __construct(
        public string $name,
        public string $publicId,
        public string $systemId,
    ) {}
}

final class DocumentFragment extends Node {}
```

### Declarative Shadow DOM

WHATWG HTML §13.2 ("in body" insertion mode) handles `<template shadowrootmode="open|closed">` by attaching a `ShadowRoot` to the parent element instead of placing the template content as a regular `<template>`. A WHATWG-conformant parser must produce shadow roots for real-world HTML, and **per the Q11 decision, Phase 1 ships full declarative shadow DOM encapsulation** — slot distribution, shadow-scoped selector matching, all the shadow pseudo-selectors. Phase 1 web components render with correct visual isolation. Imperative web components (Custom Elements registry, scripted `attachShadow()` from author code) remain permanently out of scope — see the JS-evaluation note in `html-and-svg.md` §Security.

```php
final class ShadowRoot extends DocumentFragment
{
    public Element $host { get; }
    public ShadowRootMode $mode { get; }
    public bool $delegatesFocus { get; }
    public bool $clonable { get; }
    public bool $serializable { get; }

    /** Slot assignment mode — "named" or "manual" per WHATWG. */
    public SlotAssignment $slotAssignment { get; }

    /**
     * @return list<HTMLSlotElement> slots in this shadow tree, in tree order
     */
    public function slots(): array;
}

enum ShadowRootMode { case Open; case Closed; }
enum SlotAssignment { case Named; case Manual; }

final readonly class ShadowRootInit
{
    public function __construct(
        public bool $delegatesFocus = false,
        public bool $clonable = false,
        public bool $serializable = false,
        public SlotAssignment $slotAssignment = SlotAssignment::Named,
    ) {}
}

/**
 * The <slot> element. A regular HTML element with extra accessors for the
 * flat-tree composition algorithm. Created by the parser like any other element.
 */
final class HTMLSlotElement extends Element
{
    public ?string $name { get; }                  // value of the "name" attribute

    /**
     * Nodes assigned to this slot in the flat tree after slot distribution.
     * For named slots: matched by slot="name" on direct children of the host.
     * For default slots: matched by direct children of the host without slot attr.
     * For manual slotting (SlotAssignment::Manual): set by parser-internal API.
     *
     * @return list<Node>
     */
    public function assignedNodes(bool $flatten = false): array;

    /** @return list<Element> assignedNodes filtered to Elements */
    public function assignedElements(bool $flatten = false): array;
}
```

**Phase 1 rendering treatment.** The cascade walks the **flat tree** (composed from light DOM + shadow tree + slot distribution per WHATWG §4.2.2.3.1). The `<slot>` element renders its assigned content when populated; otherwise it renders its fallback (light) content. Shadow-scoped CSS rules only match within their shadow tree, with these pseudo-selectors fully supported:

- `:host` — matches the shadow root's host element
- `:host(<selector>)` — matches the host when it matches the selector
- `:host-context(<selector>)` — matches the host when an ancestor matches the selector
- `::slotted(<selector>)` — matches assigned nodes from outside the shadow tree
- `::part(<ident>)` — matches elements inside a shadow tree exposed via `part` attribute
- `::theme(<ident>)` — matches `part`s anywhere in any shadow tree (per CSS Shadow Parts)

**Serialization.** `Serializer::serialize()` re-emits attached shadow roots as `<template shadowrootmode="open|closed">` per the HTML Living Standard, so serialize→parse round-trip preserves shadow structure. Per HTML spec, closed-mode shadow roots only serialize if `serializable: true` was set when attached.

**Scope impact on the html-and-svg phasing.** Phase 1 sub-phases 1D (selectors+cascade), 1E (box generation), and 1F (layout) all gain shadow-DOM-aware logic. This is roughly +25% effort on those three sub-phases. Phase 1 is no longer "minimal HTML"; it's "complete HTML with web components rendering correctly."

### Parser entry point

```php
namespace Phpdftk\Html;

final class Parser
{
    public function __construct(?ParserOptions $options = null) {}

    /** Parse a complete HTML document. */
    public function parseDocument(string $html, ?string $encoding = null): Dom\Document;

    /**
     * Parse an HTML fragment in the context of a host element.
     * Used for innerHTML-style operations and for HTML embedded in SVG <foreignObject>.
     */
    public function parseFragment(string $html, Dom\Element $context): Dom\DocumentFragment;
}

final readonly class ParserOptions
{
    public function __construct(
        public bool $scriptingEnabled = false, // affects <noscript> handling
        public ?string $assumedEncoding = null, // override BOM/meta detection
    ) {}
}
```

### Serializer

```php
namespace Phpdftk\Html;

final class Serializer
{
    /** Serialize a node to HTML5 syntax per WHATWG §13.3. */
    public function serialize(Dom\Node $node): string;
}
```

### What's deliberately NOT in the public surface

- Live `HTMLCollection` (we expose snapshot arrays)
- `NodeIterator`, `TreeWalker`, `Range`
- `MutationObserver`, custom elements
- Shadow-DOM **encapsulation features** at Phase 1 (slot distribution, `:host*`, `::slotted`, `::part`, `::theme`, shadow-scoped selector matching) — the shadow tree structure is exposed; the encapsulation semantics arrive in Phase 2
- `Event`/`EventTarget`/`dispatchEvent`
- `innerHTML`/`outerHTML` setters (use `Parser::parseFragment`)
- DOM Level 1/2/3 legacy methods
- `Element::style` (we don't conflate parsing with style computation)

---

## `phpdftk/css` — CSS values, rules, computed style

Namespace: `Phpdftk\Css\`

The CSS package produces three kinds of public outputs: **parsed stylesheets** (rules + selectors + declarations), **computed styles** (per-element resolved values after the cascade), and **the selector engine** that `phpdftk/html`'s `Element::querySelectorAll` depends on.

### Stylesheet, Rule, Declaration

```php
namespace Phpdftk\Css\Sheet;

final readonly class Stylesheet
{
    /** @param list<Rule> $rules */
    public function __construct(
        public array $rules,
        public Origin $origin = Origin::Author,
    ) {}
}

enum Origin
{
    case UserAgent;
    case User;
    case Author;
}

abstract readonly class Rule {}

final readonly class StyleRule extends Rule
{
    public function __construct(
        public Selector\SelectorList $selectors,
        /** @var list<Declaration> */
        public array $declarations,
    ) {}
}

final readonly class AtRule extends Rule
{
    public function __construct(
        public string $name,              // e.g. "media", "font-face", "page"
        public string $prelude,           // raw prelude tokens, serialised
        public ?AtRuleBlock $block,       // null for declaration-only at-rules
    ) {}
}

final readonly class AtRuleBlock
{
    /** @param list<Rule|Declaration> $contents */
    public function __construct(public array $contents) {}
}

final readonly class Declaration
{
    public function __construct(
        public string $property,          // lowercased, e.g. "color"
        public Value\Value $value,
        public bool $important = false,
    ) {}
}
```

### Value types

`Value` is the public, typed representation of any CSS value after tokenization. The hierarchy is fixed — every value of every property is one of these types or a `ValueList`.

```php
namespace Phpdftk\Css\Value;

abstract readonly class Value
{
    /** Re-serialise to a CSS string. Round-trip compatible. */
    abstract public function toCss(): string;
}

final readonly class Keyword extends Value      // e.g. auto, none, inherit
{ public function __construct(public string $name) {} }

final readonly class Number extends Value
{ public function __construct(public float $value) {} }

final readonly class Integer extends Value
{ public function __construct(public int $value) {} }

final readonly class Length extends Value
{
    public function __construct(
        public float $value,
        public LengthUnit $unit, // px, pt, em, rem, %, vw, vh, ...
    ) {}
}

enum LengthUnit: string
{
    case Px = 'px'; case Pt = 'pt'; case Cm = 'cm'; case Mm = 'mm';
    case In = 'in'; case Pc = 'pc'; case Q = 'q';
    case Em = 'em'; case Rem = 'rem'; case Ex = 'ex'; case Ch = 'ch';
    case Vw = 'vw'; case Vh = 'vh'; case Vmin = 'vmin'; case Vmax = 'vmax';
    case Percent = '%';
    case None = ''; // for <number> contexts that admit lengths

    public function isAbsolute(): bool;
    public function isRelative(): bool;
    public function isViewport(): bool;
}

final readonly class Color extends Value
{
    public function __construct(
        public float $r,    // 0..1, sRGB
        public float $g,    // 0..1
        public float $b,    // 0..1
        public float $a,    // 0..1
        public ColorSpace $space = ColorSpace::sRGB,
    ) {}
}

enum ColorSpace { case sRGB; case DisplayP3; case A98RGB; case ProPhotoRGB; case Rec2020; case OKLCH; case Lab; case Lch; }

final readonly class Url extends Value
{
    public function __construct(public string $url) {}
}

final readonly class Image extends Value
{
    public function __construct(public Url|Gradient|ImageSet $source) {}
}

abstract readonly class Gradient extends Value {}

final readonly class LinearGradient extends Gradient
{
    public function __construct(
        public float $angleDeg,
        /** @var list<GradientStop> */
        public array $stops,
    ) {}
}

final readonly class RadialGradient extends Gradient
{
    public function __construct(
        public GradientShape $shape,
        public ?Length $sizeX,
        public ?Length $sizeY,
        public Length $centerX,
        public Length $centerY,
        /** @var list<GradientStop> */
        public array $stops,
    ) {}
}

enum GradientShape { case Circle; case Ellipse; }

final readonly class GradientStop
{
    public function __construct(public Color $color, public ?Length $position) {}
}

final readonly class ImageSet extends Value
{
    /** @param list<ImageSetCandidate> $candidates */
    public function __construct(public array $candidates) {}
}

final readonly class Calc extends Value
{
    public function __construct(public CalcExpression $expression) {}
}

abstract readonly class CalcExpression {}
final readonly class CalcLeaf extends CalcExpression { public function __construct(public Value $value) {} }
final readonly class CalcBinary extends CalcExpression {
    public function __construct(public CalcExpression $left, public CalcOp $op, public CalcExpression $right) {}
}
final readonly class CalcFunc extends CalcExpression {
    public function __construct(public CalcFunction $func, /** @var list<CalcExpression> */ public array $args) {}
}
enum CalcOp { case Add; case Sub; case Mul; case Div; }
enum CalcFunction { case Min; case Max; case Clamp; case Round; case Mod; case Rem; case Sin; case Cos; case Tan; case Asin; case Acos; case Atan; case Atan2; case Pow; case Sqrt; case Hypot; case Log; case Exp; case Abs; case Sign; }

final readonly class Transform extends Value
{
    /** @param list<TransformFunction> $functions */
    public function __construct(public array $functions) {}
}

abstract readonly class TransformFunction {}
final readonly class TranslateTransform extends TransformFunction
{ public function __construct(public Length $x, public Length $y, public Length $z) {} }
final readonly class RotateTransform extends TransformFunction
{ public function __construct(public float $angleDeg, public float $ax, public float $ay, public float $az) {} }
final readonly class ScaleTransform extends TransformFunction
{ public function __construct(public float $sx, public float $sy, public float $sz) {} }
final readonly class SkewTransform extends TransformFunction
{ public function __construct(public float $xDeg, public float $yDeg) {} }
final readonly class MatrixTransform extends TransformFunction
{
    public function __construct(
        public float $a, public float $b, public float $c,
        public float $d, public float $e, public float $f,
    ) {}
}

final readonly class CssFunction extends Value
{
    public function __construct(
        public string $name,
        /** @var list<Value> */
        public array $arguments,
    ) {}
}

final readonly class CustomProperty extends Value
{
    /** Unresolved var(--name, fallback). Resolved during cascade. */
    public function __construct(
        public string $name,
        public ?Value $fallback,
    ) {}
}

final readonly class ValueList extends Value
{
    /** @param list<Value> $values */
    public function __construct(
        public array $values,
        public ListSeparator $separator,
    ) {}
}

enum ListSeparator { case Space; case Comma; case Slash; }
```

### Selectors

```php
namespace Phpdftk\Css\Selector;

final readonly class SelectorList
{
    /** @param list<ComplexSelector> $selectors */
    public function __construct(public array $selectors) {}
}

final readonly class ComplexSelector
{
    /** @param list<CompoundSelectorWithCombinator> $compounds */
    public function __construct(public array $compounds) {}

    public function specificity(): Specificity;
}

final readonly class CompoundSelectorWithCombinator
{
    public function __construct(
        public Combinator $combinator,           // for the first compound, Combinator::None
        public CompoundSelector $compound,
    ) {}
}

enum Combinator { case None; case Descendant; case Child; case AdjacentSibling; case GeneralSibling; }

final readonly class CompoundSelector
{
    /** @param list<SimpleSelector> $simples */
    public function __construct(public array $simples) {}
}

abstract readonly class SimpleSelector {}
final readonly class TypeSelector extends SimpleSelector
{ public function __construct(public string $localName, public ?string $namespace = null) {} }
final readonly class UniversalSelector extends SimpleSelector {}
final readonly class IdSelector extends SimpleSelector
{ public function __construct(public string $id) {} }
final readonly class ClassSelector extends SimpleSelector
{ public function __construct(public string $className) {} }
final readonly class AttributeSelector extends SimpleSelector
{
    public function __construct(
        public string $name,
        public AttributeMatch $match,
        public ?string $value,
        public bool $caseInsensitive = false,
    ) {}
}
enum AttributeMatch { case Exists; case Equals; case Includes; case DashMatch; case Prefix; case Suffix; case Contains; }
final readonly class PseudoClassSelector extends SimpleSelector
{ public function __construct(public string $name, public ?string $argument = null) {} }
final readonly class PseudoElementSelector extends SimpleSelector
{ public function __construct(public string $name, public ?string $argument = null) {} }

final readonly class Specificity
{
    public function __construct(
        public int $a,  // id count
        public int $b,  // class/attr/pseudo-class count
        public int $c,  // type/pseudo-element count
    ) {}

    public function compare(Specificity $other): int; // <=>
}
```

### Selector engine

```php
namespace Phpdftk\Css;

final class SelectorEngine
{
    /**
     * @return list<Phpdftk\Html\Dom\Element>
     */
    public function querySelectorAll(
        Phpdftk\Html\Dom\Document|Phpdftk\Html\Dom\Element $scope,
        string|Selector\SelectorList $selector,
    ): array;

    public function matches(
        Phpdftk\Html\Dom\Element $element,
        string|Selector\SelectorList $selector,
    ): bool;
}
```

### Parser

```php
namespace Phpdftk\Css;

final class Parser
{
    public function parseStylesheet(string $css, Sheet\Origin $origin = Sheet\Origin::Author): Sheet\Stylesheet;
    public function parseInlineStyle(string $css): Sheet\StyleRule; // for HTML style="..."
    public function parseValue(string $css, string $propertyHint = ''): Value\Value;
    public function parseSelector(string $selector): Selector\SelectorList;
}
```

### Cascade and computed style

```php
namespace Phpdftk\Css\Cascade;

/**
 * Per-element computed style after the cascade. Exposes a typed accessor for
 * every supported CSS property. Methods are AUTO-GENERATED from a property
 * registry (see Phpdftk\Css\Property\Registry) — never hand-edited. Adding a
 * new property is a registry change; the accessor is regenerated by a build
 * script that runs as part of `composer lint:fix`.
 *
 * The accessor return type matches the property's value grammar exactly:
 * - properties accepting a single value type return that type
 * - properties accepting multiple grammars return a union
 * - shorthand properties are not exposed; only their longhand expansions
 *
 * Initial values (from the property registry) are returned for properties not
 * explicitly set anywhere in the cascade.
 */
final readonly class ComputedStyle
{
    // === Color & background ===
    public function getColor(): Value\Color;
    public function getBackgroundColor(): Value\Color;
    public function getBackgroundImage(): Value\Image|Value\Keyword;     // image | none
    public function getBackgroundRepeat(): Value\Keyword;
    public function getBackgroundPosition(): Value\ValueList;
    public function getBackgroundSize(): Value\ValueList|Value\Keyword;
    public function getBackgroundAttachment(): Value\Keyword;
    public function getBackgroundOrigin(): Value\Keyword;
    public function getBackgroundClip(): Value\Keyword;
    public function getOpacity(): Value\Number;

    // === Font & text ===
    public function getFontFamily(): Value\ValueList;
    public function getFontSize(): Value\Length;
    public function getFontStyle(): Value\Keyword;
    public function getFontWeight(): Value\Integer|Value\Keyword;
    public function getFontStretch(): Value\Percentage|Value\Keyword;
    public function getFontVariant(): Value\ValueList;
    public function getLineHeight(): Value\Length|Value\Number|Value\Keyword;
    public function getTextAlign(): Value\Keyword;
    public function getTextAlignLast(): Value\Keyword;
    public function getTextDecoration(): Value\ValueList;
    public function getTextDecorationLine(): Value\ValueList;
    public function getTextDecorationStyle(): Value\Keyword;
    public function getTextDecorationColor(): Value\Color;
    public function getTextDecorationThickness(): Value\Length|Value\Keyword;
    public function getTextTransform(): Value\Keyword;
    public function getTextIndent(): Value\Length;
    public function getTextShadow(): Value\ValueList|Value\Keyword;
    public function getLetterSpacing(): Value\Length|Value\Keyword;
    public function getWordSpacing(): Value\Length|Value\Keyword;
    public function getWhiteSpace(): Value\Keyword;
    public function getWordBreak(): Value\Keyword;
    public function getOverflowWrap(): Value\Keyword;
    public function getHyphens(): Value\Keyword;
    public function getVerticalAlign(): Value\Length|Value\Keyword;
    public function getDirection(): Value\Keyword;
    public function getUnicodeBidi(): Value\Keyword;
    public function getWritingMode(): Value\Keyword;
    public function getTextOrientation(): Value\Keyword;

    // === Box model ===
    public function getDisplay(): Value\Keyword;
    public function getPosition(): Value\Keyword;
    public function getTop(): Value\Length|Value\Keyword;
    public function getRight(): Value\Length|Value\Keyword;
    public function getBottom(): Value\Length|Value\Keyword;
    public function getLeft(): Value\Length|Value\Keyword;
    public function getZIndex(): Value\Integer|Value\Keyword;
    public function getWidth(): Value\Length|Value\Keyword;
    public function getHeight(): Value\Length|Value\Keyword;
    public function getMinWidth(): Value\Length|Value\Keyword;
    public function getMinHeight(): Value\Length|Value\Keyword;
    public function getMaxWidth(): Value\Length|Value\Keyword;
    public function getMaxHeight(): Value\Length|Value\Keyword;
    public function getMarginTop(): Value\Length|Value\Keyword;
    public function getMarginRight(): Value\Length|Value\Keyword;
    public function getMarginBottom(): Value\Length|Value\Keyword;
    public function getMarginLeft(): Value\Length|Value\Keyword;
    public function getPaddingTop(): Value\Length;
    public function getPaddingRight(): Value\Length;
    public function getPaddingBottom(): Value\Length;
    public function getPaddingLeft(): Value\Length;
    public function getBorderTopWidth(): Value\Length;
    public function getBorderRightWidth(): Value\Length;
    public function getBorderBottomWidth(): Value\Length;
    public function getBorderLeftWidth(): Value\Length;
    public function getBorderTopStyle(): Value\Keyword;
    public function getBorderRightStyle(): Value\Keyword;
    public function getBorderBottomStyle(): Value\Keyword;
    public function getBorderLeftStyle(): Value\Keyword;
    public function getBorderTopColor(): Value\Color;
    public function getBorderRightColor(): Value\Color;
    public function getBorderBottomColor(): Value\Color;
    public function getBorderLeftColor(): Value\Color;
    public function getBorderTopLeftRadius(): Value\ValueList;
    public function getBorderTopRightRadius(): Value\ValueList;
    public function getBorderBottomLeftRadius(): Value\ValueList;
    public function getBorderBottomRightRadius(): Value\ValueList;
    public function getBoxSizing(): Value\Keyword;
    public function getBoxShadow(): Value\ValueList|Value\Keyword;
    public function getOverflow(): Value\Keyword;
    public function getOverflowX(): Value\Keyword;
    public function getOverflowY(): Value\Keyword;
    public function getVisibility(): Value\Keyword;
    public function getClipPath(): Value\Url|Value\Keyword;
    public function getMask(): Value\Url|Value\Keyword;

    // === Flex ===
    public function getFlexDirection(): Value\Keyword;
    public function getFlexWrap(): Value\Keyword;
    public function getJustifyContent(): Value\Keyword;
    public function getAlignItems(): Value\Keyword;
    public function getAlignContent(): Value\Keyword;
    public function getAlignSelf(): Value\Keyword;
    public function getFlexGrow(): Value\Number;
    public function getFlexShrink(): Value\Number;
    public function getFlexBasis(): Value\Length|Value\Keyword;
    public function getOrder(): Value\Integer;
    public function getGap(): Value\Length;
    public function getRowGap(): Value\Length;
    public function getColumnGap(): Value\Length;

    // === Grid ===
    public function getGridTemplateColumns(): Value\ValueList|Value\Keyword;
    public function getGridTemplateRows(): Value\ValueList|Value\Keyword;
    public function getGridTemplateAreas(): Value\ValueList|Value\Keyword;
    public function getGridAutoColumns(): Value\ValueList;
    public function getGridAutoRows(): Value\ValueList;
    public function getGridAutoFlow(): Value\Keyword;
    public function getGridColumnStart(): Value\Integer|Value\Keyword;
    public function getGridColumnEnd(): Value\Integer|Value\Keyword;
    public function getGridRowStart(): Value\Integer|Value\Keyword;
    public function getGridRowEnd(): Value\Integer|Value\Keyword;
    public function getGridArea(): Value\ValueList|Value\Keyword;
    public function getJustifyItems(): Value\Keyword;
    public function getJustifySelf(): Value\Keyword;
    public function getPlaceItems(): Value\ValueList;
    public function getPlaceContent(): Value\ValueList;
    public function getPlaceSelf(): Value\ValueList;

    // === Tables ===
    public function getBorderCollapse(): Value\Keyword;
    public function getBorderSpacing(): Value\Length;
    public function getTableLayout(): Value\Keyword;
    public function getCaptionSide(): Value\Keyword;
    public function getEmptyCells(): Value\Keyword;

    // === Lists ===
    public function getListStyleType(): Value\Keyword|Value\CssFunction;
    public function getListStylePosition(): Value\Keyword;
    public function getListStyleImage(): Value\Image|Value\Keyword;

    // === Transforms ===
    public function getTransform(): Value\Transform|Value\Keyword;
    public function getTransformOrigin(): Value\ValueList;
    public function getTransformBox(): Value\Keyword;

    // === Paged media ===
    public function getPage(): Value\Keyword|Value\CssFunction;
    public function getBreakBefore(): Value\Keyword;
    public function getBreakAfter(): Value\Keyword;
    public function getBreakInside(): Value\Keyword;
    public function getOrphans(): Value\Integer;
    public function getWidows(): Value\Integer;

    // === Multi-column ===
    public function getColumnCount(): Value\Integer|Value\Keyword;
    public function getColumnWidth(): Value\Length|Value\Keyword;
    public function getColumnRuleWidth(): Value\Length;
    public function getColumnRuleStyle(): Value\Keyword;
    public function getColumnRuleColor(): Value\Color;
    public function getColumnSpan(): Value\Keyword;
    public function getColumnFill(): Value\Keyword;

    // === Filters ===
    public function getFilter(): Value\ValueList|Value\Keyword;
    public function getBackdropFilter(): Value\ValueList|Value\Keyword;

    // === SVG-specific (used by phpdftk/svg-to-pdf) ===
    public function getFill(): Value\Color|Value\Url|Value\Keyword;
    public function getFillOpacity(): Value\Number;
    public function getFillRule(): Value\Keyword;
    public function getStroke(): Value\Color|Value\Url|Value\Keyword;
    public function getStrokeWidth(): Value\Length;
    public function getStrokeOpacity(): Value\Number;
    public function getStrokeLinecap(): Value\Keyword;
    public function getStrokeLinejoin(): Value\Keyword;
    public function getStrokeMiterlimit(): Value\Number;
    public function getStrokeDasharray(): Value\ValueList|Value\Keyword;
    public function getStrokeDashoffset(): Value\Length;

    // === Generic escape hatch (for unknown / custom / vendor-prefixed properties) ===
    public function getCustomProperty(string $name): ?Value\Value; // for --foo
    public function getUnknown(string $name): ?Value\Value;        // for anything else

    /** @return array<string, Value\Value> all set properties */
    public function all(): array;

    public function has(string $property): bool;
}
```

**Implementation note (not part of the contract):** these accessors are generated from `Phpdftk\Css\Property\Registry`, a per-property definition file that ships with the package. Adding a property is an additive minor-version bump to `phpdftk/css`. The registry is the single source of truth; the generator writes both `ComputedStyle` and the cascade's per-property handling. This is the only way the ~200+ accessor surface stays maintainable.

final class Cascade
{
    /** @param list<Sheet\Stylesheet> $stylesheets */
    public function __construct(
        array $stylesheets,
        Sheet\Stylesheet $userAgentSheet,
    ) {}

    /** Compute styles for an element given its position in the document. */
    public function computeFor(Phpdftk\Html\Dom\Element $element): ComputedStyle;

    /**
     * Compute styles for the entire document. Returned map is keyed by element
     * identity (spl_object_id). Memory-intensive for large documents — prefer
     * computeFor for streaming layout.
     *
     * @return array<int, ComputedStyle>
     */
    public function computeAll(Phpdftk\Html\Dom\Document $document): array;
}
```

---

## `phpdftk/text` — shaping, line breaking, bidi

Namespace: `Phpdftk\Text\`

Three output types: `LineBreakOpportunity` (where can a line break occur in a string), `BidiResult` (where do bidi runs start/end and what's their level), and `ShapedRun` / `ShapedParagraph` (glyphs positioned after OpenType shaping).

### Line breaking

```php
final class LineBreaker
{
    /**
     * Enumerate line-break opportunities for a string per UAX #14.
     * Locale affects strictness (e.g. CJK strictness, Japanese kinsoku).
     */
    public function breakOpportunities(string $text, string $locale = 'en'): LineBreakIterator;
}

final class LineBreakIterator implements \IteratorAggregate
{
    /** @return \Generator<int, LineBreakOpportunity> */
    public function getIterator(): \Generator;
}

final readonly class LineBreakOpportunity
{
    public function __construct(
        public int $offset,                  // byte offset into the source string
        public LineBreakKind $kind,
    ) {}
}

enum LineBreakKind { case Mandatory; case Allowed; }
```

### Bidi

```php
final class Bidi
{
    public function analyze(string $text, BidiBase $base = BidiBase::Auto): BidiResult;
}

enum BidiBase { case Ltr; case Rtl; case Auto; }

final readonly class BidiResult
{
    public function __construct(
        public BidiBase $resolvedBase,
        /** @var list<BidiRun> */
        public array $runs,
    ) {}

    /**
     * Resolved bidi level for a single character offset.
     * Computed lazily from runs; cheap O(log runs) via binary search.
     * Returns null if offset is out of range.
     */
    public function charLevelAt(int $offset): ?int;
}

final readonly class BidiRun
{
    public function __construct(
        public int $offset,
        public int $length,
        public int $level,                   // 0 = LTR, 1 = RTL, higher = nested embedding
    ) {}
}
```

### Shaping

```php
final class Shaper
{
    public function __construct(Phpdftk\FontParser\OpenTypeParser $fontParser) {}

    /**
     * Shape a run of text in a single font, direction, and script.
     * Higher-level callers (paragraph shaper) split mixed input into runs first.
     */
    public function shapeRun(
        string $text,
        ShapingContext $context,
    ): ShapedRun;

    /**
     * Shape an entire paragraph. Internally splits into bidi runs + script runs
     * + font runs (font fallback within a single style), then shapes each.
     */
    public function shapeParagraph(
        string $text,
        ParagraphContext $context,
    ): ShapedParagraph;
}

final readonly class ShapingContext
{
    public function __construct(
        public Phpdftk\FontParser\OpenTypeData $font,
        public float $fontSizePt,
        public string $script,               // ISO 15924 e.g. "Latn", "Hans", "Arab"
        public string $language,             // BCP 47 e.g. "en", "ja", "ar-EG"
        public ShapingDirection $direction,
        /** @var list<string> OpenType feature tags to enable, e.g. ["kern", "liga"] */
        public array $features = ['kern', 'liga'],
    ) {}
}

enum ShapingDirection { case Ltr; case Rtl; case Ttb; }

final readonly class ParagraphContext
{
    public function __construct(
        /** @var list<Phpdftk\FontParser\OpenTypeData> font stack (fallback order) */
        public array $fontStack,
        public float $fontSizePt,
        public string $language,
        public BidiBase $bidiBase = BidiBase::Auto,
        /** @var list<string> */
        public array $features = ['kern', 'liga'],
    ) {}
}

final readonly class ShapedRun
{
    public function __construct(
        public Phpdftk\FontParser\OpenTypeData $font,
        public float $fontSizePt,
        public ShapingDirection $direction,
        /** @var list<ShapedGlyph> */
        public array $glyphs,
        public float $totalAdvance,          // sum of glyph advances, in PDF user-space units (1pt)
    ) {}
}

final readonly class ShapedGlyph
{
    public function __construct(
        public int $glyphId,
        public int $sourceOffset,            // byte offset in the input string
        public int $sourceLength,            // UTF-8 length of source cluster
        public float $advanceX,
        public float $advanceY,
        public float $offsetX,
        public float $offsetY,
    ) {}
}

final readonly class ShapedParagraph
{
    public function __construct(
        public string $sourceText,
        public BidiResult $bidi,
        /** @var list<ShapedRun> in visual order after bidi reordering */
        public array $visualRuns,
        public LineBreakIterator $lineBreaks,
    ) {}
}
```

---

## `phpdftk/svg` — SVG parsed tree

Namespace: `Phpdftk\Svg\`

A typed tree where every SVG element gets a concrete class. Presentation attributes are pre-parsed into `Phpdftk\Css\Value\Value` instances (so `<rect fill="red">` and `<rect style="fill: red">` look identical to consumers). CSS-in-SVG (via `<style>` elements) is folded into the same computed style at parse time using `phpdftk/css`.

### Parser

```php
namespace Phpdftk\Svg;

final class Parser
{
    public function __construct(?ParserOptions $options = null) {}

    public function parse(string $xml): Tree\SvgDocument;
}

final readonly class ParserOptions
{
    public function __construct(
        public bool $applyCssCascade = true, // process <style> and external sheets
        public ?string $baseUrl = null,
    ) {}
}
```

### Tree

```php
namespace Phpdftk\Svg\Tree;

abstract readonly class SvgNode
{
    public function __construct(
        public ?string $id,
        public ComputedStyle $style,         // pre-resolved
        /** @var array<string, string> raw attributes (post-presentation-attribute extraction) */
        public array $rawAttributes,
        /** @var list<SvgNode> */
        public array $children,
    ) {}
}

final readonly class SvgDocument extends SvgNode
{
    public function __construct(
        ?string $id,
        ComputedStyle $style,
        array $rawAttributes,
        array $children,
        public Viewport $viewport,
        public ?Viewbox $viewBox,
        public ?string $preserveAspectRatio,
    ) {
        parent::__construct($id, $style, $rawAttributes, $children);
    }
}

final readonly class Viewport
{
    public function __construct(public float $width, public float $height) {}
}

final readonly class Viewbox
{
    public function __construct(public float $minX, public float $minY, public float $width, public float $height) {}
}

final readonly class SvgGroup extends SvgNode {}
final readonly class SvgDefs extends SvgNode {}
final readonly class SvgSymbol extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public ?Viewbox $viewBox,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgUse extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public string $href,
        public float $x, public float $y,
        public ?float $width, public ?float $height,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgPath extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        /** @var list<PathCommand> */
        public array $d,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

/**
 * Path commands preserve every SVG-2 variant — absolute vs. relative,
 * smooth/shorthand vs. explicit. Lossless: a parsed path round-trips back
 * to source via Serializer. Painter handles all variants; consumers writing
 * sanitizers / format-converters see the original author intent.
 */
abstract readonly class PathCommand {}

// MoveTo: M (abs), m (rel)
final readonly class MoveToAbs extends PathCommand { public function __construct(public float $x, public float $y) {} }
final readonly class MoveToRel extends PathCommand { public function __construct(public float $dx, public float $dy) {} }

// LineTo: L (abs), l (rel)
final readonly class LineToAbs extends PathCommand { public function __construct(public float $x, public float $y) {} }
final readonly class LineToRel extends PathCommand { public function __construct(public float $dx, public float $dy) {} }

// Horizontal/Vertical line shorthands: H/h, V/v
final readonly class HorizontalLineAbs extends PathCommand { public function __construct(public float $x) {} }
final readonly class HorizontalLineRel extends PathCommand { public function __construct(public float $dx) {} }
final readonly class VerticalLineAbs extends PathCommand { public function __construct(public float $y) {} }
final readonly class VerticalLineRel extends PathCommand { public function __construct(public float $dy) {} }

// Cubic Bezier: C/c (explicit), S/s (smooth — implicit first control point)
final readonly class CubicToAbs extends PathCommand {
    public function __construct(
        public float $x1, public float $y1,
        public float $x2, public float $y2,
        public float $x, public float $y,
    ) {}
}
final readonly class CubicToRel extends PathCommand {
    public function __construct(
        public float $dx1, public float $dy1,
        public float $dx2, public float $dy2,
        public float $dx, public float $dy,
    ) {}
}
final readonly class SmoothCubicToAbs extends PathCommand {
    public function __construct(public float $x2, public float $y2, public float $x, public float $y) {}
}
final readonly class SmoothCubicToRel extends PathCommand {
    public function __construct(public float $dx2, public float $dy2, public float $dx, public float $dy) {}
}

// Quadratic Bezier: Q/q (explicit), T/t (smooth)
final readonly class QuadToAbs extends PathCommand {
    public function __construct(public float $x1, public float $y1, public float $x, public float $y) {}
}
final readonly class QuadToRel extends PathCommand {
    public function __construct(public float $dx1, public float $dy1, public float $dx, public float $dy) {}
}
final readonly class SmoothQuadToAbs extends PathCommand {
    public function __construct(public float $x, public float $y) {}
}
final readonly class SmoothQuadToRel extends PathCommand {
    public function __construct(public float $dx, public float $dy) {}
}

// Elliptical arc: A (abs), a (rel)
final readonly class ArcToAbs extends PathCommand {
    public function __construct(
        public float $rx, public float $ry, public float $xAxisRotation,
        public bool $largeArc, public bool $sweep,
        public float $x, public float $y,
    ) {}
}
final readonly class ArcToRel extends PathCommand {
    public function __construct(
        public float $rx, public float $ry, public float $xAxisRotation,
        public bool $largeArc, public bool $sweep,
        public float $dx, public float $dy,
    ) {}
}

// Close: Z or z (no difference)
final readonly class ClosePath extends PathCommand {}

/**
 * Helper for the painter — accepts any PathCommand and produces the absolute
 * MoveTo/LineTo/CubicTo/QuadTo/ArcTo/Close needed for PDF emission. Stateful:
 * tracks current point and last cubic/quad control point for smooth variants.
 * Provided as a utility for downstream consumers (svg-to-pdf, sanitizers that
 * want canonical form) without forcing normalization at parse time.
 */
final class PathNormalizer
{
    /** @return list<PathCommand> only absolute, non-smooth variants */
    public function normalize(/** @var list<PathCommand> */ array $commands): array;
}

final readonly class SvgRect extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public float $x, public float $y,
        public float $width, public float $height,
        public float $rx, public float $ry,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgCircle extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public float $cx, public float $cy, public float $r,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgEllipse extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public float $cx, public float $cy, public float $rx, public float $ry,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgLine extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public float $x1, public float $y1, public float $x2, public float $y2,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgPolygon extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        /** @var list<array{0:float, 1:float}> */
        public array $points,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgPolyline extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        /** @var list<array{0:float, 1:float}> */
        public array $points,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgText extends SvgNode
{
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public float $x, public float $y,
        public string $text,
        public ?float $dx, public ?float $dy,
        public ?float $rotate,
        public ?TextAnchor $textAnchor,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

enum TextAnchor { case Start; case Middle; case End; }

final readonly class SvgTspan extends SvgNode { /* same shape as SvgText */ }
final readonly class SvgImage extends SvgNode {
    public function __construct(
        ?string $id, ComputedStyle $style, array $rawAttributes, array $children,
        public float $x, public float $y,
        public float $width, public float $height,
        public string $href,
    ) { parent::__construct($id, $style, $rawAttributes, $children); }
}

final readonly class SvgLinearGradient extends SvgNode { /* x1, y1, x2, y2, stops */ }
final readonly class SvgRadialGradient extends SvgNode { /* cx, cy, r, fx, fy, stops */ }
final readonly class SvgClipPath extends SvgNode {}
final readonly class SvgMask extends SvgNode {}
```

`ComputedStyle` here is the same type as `Phpdftk\Css\Cascade\ComputedStyle` — the SVG parser optionally runs the CSS cascade for `<style>` blocks, and presentation attributes are folded in as the lowest-priority origin.

---

## `phpdftk/filesystem` — ResourceLoader additions

Existing `Phpdftk\Filesystem\LocalFilesystem` is unchanged. New addition:

```php
namespace Phpdftk\Filesystem;

final class ResourceLoader
{
    public function __construct(
        public readonly ResourceLoaderPolicy $policy,
    ) {}

    /**
     * Resolve a URL (absolute, relative-to-base, data:, file:) to its bytes.
     * Enforces all the security rules in html-and-svg.md §Security.
     *
     * @throws ResourceLoadException on rejection (policy, size cap, MIME mismatch)
     */
    public function load(string $url, ?string $baseUrl = null): Resource;
}

final readonly class Resource
{
    public function __construct(
        public string $bytes,
        public string $mimeType,             // from magic-number sniffing, NOT from extension
        public string $sourceUrl,            // resolved absolute URL
        public int $sizeBytes,
    ) {}
}

final readonly class ResourceLoaderPolicy
{
    public function __construct(
        /** @var list<string> directories that file:// URLs may resolve under */
        public array $allowedRoots = [],
        public bool $allowDataUrls = true,
        public bool $allowRemote = false,    // Phase 2 only
        public int $maxImageBytes = 50 * 1024 * 1024,
        public int $maxFontBytesRaw = 20 * 1024 * 1024,
        public int $maxFontBytesDecompressed = 80 * 1024 * 1024,
        public int $maxStylesheetBytes = 2 * 1024 * 1024,
        public int $maxDataUrlBytes = 10 * 1024 * 1024,
        public int $maxTotalDocumentBytes = 256 * 1024 * 1024,
        public int $maxImportDepth = 16,
        public int $connectTimeoutSec = 5,   // Phase 2
        public int $readTimeoutSec = 10,     // Phase 2
        public int $maxRedirects = 5,        // Phase 2
        /** @var list<string> hosts allowed for remote fetches (empty = denylist mode) */
        public array $remoteHostAllowlist = [],
        /** @var list<string> hosts denied even if allowlist matches */
        public array $remoteHostDenylist = [],
    ) {}
}

class ResourceLoadException extends \RuntimeException {}
class ResourceLimitException extends ResourceLoadException {}
class ResourcePolicyException extends ResourceLoadException {}
```

---

## `phpdftk/html-to-pdf` — user entry points

Namespace: `Phpdftk\HtmlToPdf\`

### Renderer

```php
final class Renderer
{
    public function __construct(?RendererOptions $options = null) {}

    /**
     * Render an HTML+CSS document to a fresh PdfWriter.
     * Returns a RenderResult holding both the writer and any warnings emitted.
     * Each call is self-contained — Renderer holds no state between calls.
     */
    public function render(string $html, ?string $css = null): RenderResult;

    /**
     * Render into an existing PdfWriter. Used by Pdf::addHtml() sugar.
     * Returns warnings; the writer mutation is the side effect.
     *
     * @return list<Warning>
     */
    public function renderInto(
        Phpdftk\Pdf\Writer\PdfWriter $writer,
        string $html,
        ?string $css = null,
    ): array;

    /** Escape hatches for advanced consumers. */
    public function parse(string $html): Phpdftk\Html\Dom\Document;
    public function parseStylesheet(string $css): Phpdftk\Css\Sheet\Stylesheet;
}

final readonly class RenderResult
{
    public function __construct(
        public Phpdftk\Pdf\Writer\PdfWriter $writer,
        /** @var list<Warning> */
        public array $warnings,
    ) {}

    /** Convenience: true iff any warning has severity Error. */
    public function hasErrors(): bool;
}
```

### RendererOptions

Immutable value class matching the existing `Phpdftk\Pdf\Writer\Theme` pattern. Constructor takes named arguments for one-shot construction; `with*()` methods compose modified copies for programmatic composition.

```php
final readonly class RendererOptions
{
    public function __construct(
        public Phpdftk\Geometry\Rectangle $defaultPageSize = new Phpdftk\Geometry\Rectangle(0, 0, 612, 792), // US Letter
        /** @var list<string> e.g. ['Helvetica', 'Arial', 'sans-serif'] */
        public array $defaultFontStack = ['Helvetica', 'Arial', 'sans-serif'],
        public ?string $baseUrl = null,
        public bool $strict = false,
        public ?Phpdftk\Pdf\Conformance\ConformanceProfile $conformance = null,
        public Phpdftk\Filesystem\ResourceLoaderPolicy $securityPolicy = new Phpdftk\Filesystem\ResourceLoaderPolicy(),
        public ?string $userAgentStylesheet = null,         // override the built-in UA sheet
        public CursorBehavior $cursorAfterAddHtml = CursorBehavior::BelowLastBlock,
    ) {}

    public function withDefaultPageSize(Phpdftk\Geometry\Rectangle $size): self;
    /** @param list<string> $stack */
    public function withDefaultFontStack(array $stack): self;
    public function withBaseUrl(?string $url): self;
    public function withStrict(bool $strict): self;
    public function withConformance(?Phpdftk\Pdf\Conformance\ConformanceProfile $profile): self;
    public function withSecurityPolicy(Phpdftk\Filesystem\ResourceLoaderPolicy $policy): self;
    public function withUserAgentStylesheet(?string $css): self;
    public function withCursorBehavior(CursorBehavior $behavior): self;
}

/**
 * Where Pdf::addHtml() leaves the cursor after the HTML render finishes.
 * Only affects the integration path (Pdf::addHtml). The standalone Renderer
 * always produces a fresh PdfWriter with no cursor concept.
 */
enum CursorBehavior
{
    /** Directly below the last rendered block, on the last page used. Seamless flow continuation. */
    case BelowLastBlock;
    /** Top of bottom margin on the last page used. Skips remaining content area. */
    case BottomOfContentArea;
    /** Top of a fresh new page added after the HTML. Always starts subsequent content on a new page. */
    case NextPage;
}
```

### Warnings

```php
final readonly class Warning
{
    public function __construct(
        public WarningCode $code,
        public string $message,
        public WarningSeverity $severity,
        public ?WarningLocation $location,
        /** @var array<string, scalar|null> contextual key-values */
        public array $context = [],
    ) {}
}

enum WarningCode: string
{
    case UnsupportedCssProperty = 'unsupported_css_property';
    case UnsupportedCssValue = 'unsupported_css_value';
    case UnsupportedDisplayType = 'unsupported_display_type';
    case UnsupportedSelector = 'unsupported_selector';
    case MissingFont = 'missing_font';
    case MissingResource = 'missing_resource';
    case ResourceLoadFailed = 'resource_load_failed';
    case ResourceLimitExceeded = 'resource_limit_exceeded';
    case MalformedInput = 'malformed_input';
    case MimeMismatch = 'mime_mismatch';
    case SecurityViolation = 'security_violation';
    case DeprecatedFeature = 'deprecated_feature';
}

enum WarningSeverity { case Info; case Warning; case Error; }

final readonly class WarningLocation
{
    public function __construct(
        public string $source,               // "html" | "css" | "url:..."
        public ?int $line,
        public ?int $column,
    ) {}
}
```

### Exception hierarchy

```php
abstract class HtmlToPdfException extends \RuntimeException {}
final class StrictModeException extends HtmlToPdfException {}     // raised when strict:true and a Warning would fire
final class RenderException extends HtmlToPdfException {}         // unrecoverable render failure
final class ParseException extends HtmlToPdfException {}          // input HTML/CSS could not be parsed
```

### Sugar on `Phpdftk\Pdf\Writer\Pdf`

```php
// In packages/pdf/writer/src/Pdf.php
public function addHtml(string $html, ?string $css = null, ?RendererOptions $options = null): self;
```

Renders into the current page; advances the cursor past the rendered content; page breaks inside HTML add new pages. `Pdf::setHeader`/`setFooter` (writer Phase 1.1) take precedence over `@page` generated content per the html-and-svg plan.

---

## `phpdftk/svg-to-pdf` — user entry points

```php
namespace Phpdftk\SvgToPdf;

final class Renderer
{
    public function __construct(?RendererOptions $options = null) {}

    public function render(string $svg, ?Phpdftk\Geometry\Rectangle $targetSize = null): Phpdftk\Pdf\Writer\PdfWriter;

    public function renderInto(
        Phpdftk\Pdf\Writer\PdfWriter $writer,
        Phpdftk\Pdf\Writer\Page $page,
        string $svg,
        float $x, float $y,
        ?float $width = null, ?float $height = null,
    ): void;

    /** @return list<Warning> shared Warning type with html-to-pdf */
    public function getWarnings(): array;
}

final readonly class RendererOptions
{
    public function __construct(
        public bool $strict = false,
        public Phpdftk\Filesystem\ResourceLoaderPolicy $securityPolicy = new Phpdftk\Filesystem\ResourceLoaderPolicy(),
    ) {}
}
```

### Sugar on existing writer classes

```php
// Pdf::addSvg (Level 3 flow)
public function addSvg(string $svg, ?float $width = null, ?float $height = null): self;

// Writer\Page::drawSvg (Level 2 positioned)
public function drawSvg(string $svg, float $x, float $y, ?float $width = null, ?float $height = null): self;

// PdfDoc::createSvgTemplate (Level 2 reusable Form XObject)
public function createSvgTemplate(string $svg, ?Phpdftk\Geometry\Rectangle $bbox = null): Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
```

---

## Resolved decisions

All eleven Phase-0.5 open questions resolved via interview. Each decision is reflected in the contract above; this section is the audit trail.

1. **`Element` mutation public — by design.** Mutation is treated as a feature, not a leaked-implementation detail. Real use cases (server-side HTML transformation, link rewriting, sanitization preprocessing) justify the public surface. No `@internal` tagging; no separate frozen variant. One mutable DOM type.

2. **`ComputedStyle` typed accessors per property.** ~200+ typed getter methods, one per supported CSS property, generated from `Phpdftk\Css\Property\Registry`. Returns the property's value type directly (`Color`, `Length`, etc.) or a narrow union. Adding a property is a minor-version bump. The registry is the source of truth; generator script runs as part of `composer lint:fix`.

3. **Shaper emits glyph IDs + advances/offsets.** No outline extraction. Painter writes PDF `Tj`/`TJ` operators referencing glyph IDs directly via the embedded font's `cmap`. Matches PDF's native model; no wasted work.

4. **Bidi exposes runs as primary, per-character via `charLevelAt(int): ?int`.** Runs are what the painter consumes; per-character access is O(log runs) via binary search for callers that need it.

5. **SVG path commands preserve every SVG-2 variant.** ~20 concrete `PathCommand` subclasses (`MoveToAbs`/`MoveToRel`/`HorizontalLineAbs`/.../`SmoothQuadToRel`/.../`ArcToRel`). Painter handles all variants; `PathNormalizer` utility converts to canonical absolute form when consumers want it.

6. **`Renderer::render()` returns `RenderResult { writer, warnings }`.** No Renderer-level warning accumulation. Each render is self-contained. `renderInto()` returns the warning array directly since the writer is the input.

7. **`Pdf::addHtml()` cursor configurable via `CursorBehavior` enum.** Default `BelowLastBlock` (seamless flow continuation). Alternatives `BottomOfContentArea` and `NextPage`. Configured via `RendererOptions::withCursorBehavior()`.

8. **`RendererOptions` constructor + `with*()` methods.** Mirrors the existing `Phpdftk\Pdf\Writer\Theme` pattern. Immutable value class; named-arg construction; fluent immutable updates.

9. **Shared test fixtures at `tests/fixtures/<category>/` at repo root.** Categories: `fonts/`, `html/`, `css/`, `svg/`, etc. `phpdftk/font-parser` existing fonts move to `tests/fixtures/fonts/` in Phase 0.5 refactor. No separate dev package.

10. **Bench runner: try public GitHub Actions first.** Defer self-hosted infrastructure. If shared-runner noise trips the 10% regression gate too often, escalate to GitHub-hosted large runners (paid) or self-hosted. Until then, the 10% gate may be temporarily relaxed — flagged in `html-and-svg.md` §Benchmarking.

11. **Full DSD encapsulation lands in Phase 1.** Slot distribution, `<slot>` flat-tree composition, shadow-scoped selector matching, and the shadow pseudo-selectors (`:host`/`:host()`/`:host-context()`/`::slotted()`/`::part()`/`::theme()`) all in MVP. Increases Phase-1 effort by ~25% on sub-phases 1D, 1E, 1F. Web components render with correct visual isolation at MVP.

This document is now the locked Phase 0.5 contract. Changes after this point require a major version bump on every consumer simultaneously. New cross-package types added via this surface require a coordinated minor bump on all dependents.
