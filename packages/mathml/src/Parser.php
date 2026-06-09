<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

use Phpdftk\Mathml\Exception\InvalidMathmlException;

/**
 * Secure MathML-to-typed-tree parser. Consumes a string of MathML XML
 * (or a fragment that the host wrapped in an explicit `<math>` root)
 * and returns a {@see MathmlDocument}.
 *
 * Security posture (mirrors {@see \Phpdftk\Svg\Parser}):
 *
 *  - **External entities disabled.** `LIBXML_NOENT` is INTENTIONALLY
 *    omitted from the libxml flags so the parser never substitutes
 *    `<!ENTITY x SYSTEM "file:///…">` references. XXE attack vector
 *    closed.
 *  - **No network access.** `LIBXML_NONET` blocks any URL fetch the
 *    parser might otherwise perform for DTDs.
 *  - **XInclude rejected.** We never call `DOMDocument::xinclude()`.
 *
 * Unknown elements outside the implemented v1 subset are preserved
 * as {@see GenericElement} instances so callers (sanitisers, format
 * converters) can inspect them without losing the subtree.
 *
 * Scope note: the v1 element set covers MathML Core tokens
 * (`<mn>`, `<mi>`, `<mo>`, `<ms>`, `<mtext>`) plus `<mrow>`. Fractions,
 * radicals, scripts, tables, spacing/framing, and the full operator
 * dictionary lookup land in follow-up slices per issue #30.
 */
final class Parser
{
    /** MathML namespace URI per spec. */
    public const string MATHML_NS = 'http://www.w3.org/1998/Math/MathML';

    public function parse(string $xml): MathmlDocument
    {
        if (trim($xml) === '') {
            throw new InvalidMathmlException('Cannot parse an empty MathML document.');
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            $loaded = $dom->loadXML(
                $xml,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
            if (!$loaded) {
                $errors = libxml_get_errors();
                $first = $errors[0] ?? null;
                throw new InvalidMathmlException(
                    $first === null
                        ? 'Failed to parse MathML XML.'
                        : sprintf('Failed to parse MathML XML: %s', trim($first->message)),
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }

        $root = $dom->documentElement;
        if ($root === null) {
            throw new InvalidMathmlException('MathML document has no root element.');
        }
        if ($root->localName !== 'math') {
            throw new InvalidMathmlException(sprintf(
                'Expected <math> root element, got <%s>.',
                $root->localName,
            ));
        }
        if ($root->namespaceURI !== null && $root->namespaceURI !== self::MATHML_NS) {
            throw new InvalidMathmlException(sprintf(
                'Root <math> declared in unexpected namespace %s.',
                $root->namespaceURI,
            ));
        }

        $doc = new MathmlDocument();
        $this->copyAttributes($root, $doc);
        $this->copyChildren($root, $doc);
        return $doc;
    }

    private function copyAttributes(\DOMElement $src, Element $dest): void
    {
        foreach ($src->attributes as $attr) {
            if (!$attr instanceof \DOMAttr) {
                continue;
            }
            // Strip xmlns declarations — implied by namespaceURI on
            // each node and we don't need them in the typed tree.
            if ($attr->prefix === 'xmlns' || $attr->name === 'xmlns') {
                continue;
            }
            $dest->attributes[$attr->nodeName] = $attr->value;
        }
    }

    private function copyChildren(\DOMElement $src, Element $dest): void
    {
        foreach ($src->childNodes as $child) {
            if ($child instanceof \DOMText) {
                // Token elements preserve their character data
                // verbatim — math syntax distinguishes `<mn>2</mn>`
                // from `<mn> 2 </mn>` only in attribute-derived
                // presentation, so we keep whitespace as-is and let
                // the painter decide.
                $dest->appendChild(new Text($child->data));
                continue;
            }
            if ($child instanceof \DOMElement) {
                $dest->appendChild($this->buildElement($child));
            }
            // Comments / processing instructions / CDATA — drop.
            // MathML Core doesn't carry semantic meaning in any of
            // them at the parser level.
        }
    }

    private function buildElement(\DOMElement $src): Element
    {
        $node = $this->makeElementForName($src->localName);
        $this->copyAttributes($src, $node);
        $this->copyChildren($src, $node);
        return $node;
    }

    private function makeElementForName(string $localName): Element
    {
        return match ($localName) {
            'mn' => new Mn(),
            'mi' => new Mi(),
            'mo' => new Mo(),
            'ms' => new Ms(),
            'mtext' => new Mtext(),
            'mrow' => new Mrow(),
            // Future slices land typed classes for `<mfrac>`,
            // `<msqrt>`, `<mroot>`, `<msub>`, `<msup>`,
            // `<msubsup>`, `<munder>`, `<mover>`, `<munderover>`,
            // `<mmultiscripts>`, `<mtable>`, `<mtr>`, `<mtd>`,
            // `<mpadded>`, `<mspace>`, `<menclose>`. Until then they
            // round-trip through GenericElement so a future Translator
            // can recognise them without a parser revision.
            default => new GenericElement($localName),
        };
    }
}
