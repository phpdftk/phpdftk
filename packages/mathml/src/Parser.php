<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

use Phpdftk\Mathml\Exception\InvalidMathmlException;
use Phpdftk\Xml\Exception\InvalidXmlException;
use Phpdftk\Xml\HardenedLoader;
use Phpdftk\Xml\TreeWalker;

/**
 * Secure MathML-to-typed-tree parser. Consumes a string of MathML XML
 * and returns a {@see MathmlDocument}.
 *
 * Routes through {@see HardenedLoader} + {@see TreeWalker} so the
 * libxml security boundary (no entity substitution, no network
 * fetches, no XInclude) is shared with the SVG parser and cannot
 * drift between them. This parser's only format-specific code is the
 * root validation (must be `<math>` in `MATHML_NS`) and the
 * `makeElementForName` typed-element factory.
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

    public function __construct(
        private readonly HardenedLoader $loader = new HardenedLoader(),
        private readonly TreeWalker $walker = new TreeWalker(),
    ) {}

    public function parse(string $xml): MathmlDocument
    {
        try {
            $dom = $this->loader->load($xml);
        } catch (InvalidXmlException $e) {
            // Re-cast the loader's generic message into a MathML-flavoured
            // one so the consumer's error text mentions the format.
            // The original libxml diagnostic is kept via the previous
            // exception.
            $message = str_replace(
                'parse XML',
                'parse MathML XML',
                $e->getMessage(),
            );
            throw new InvalidMathmlException($message, 0, $e);
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
        $this->walker->walk(
            $root,
            $doc,
            createElement: fn(string $localName) => $this->makeElementForName($localName),
            createText: static fn(string $data) => new Text($data),
            setAttribute: static fn(Element $el, string $name, string $value) => $el->setAttribute($name, $value),
            appendChild: static fn(Element $parent, Node $child) => $parent->appendChild($child),
        );
        return $doc;
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
            'mfrac' => new Mfrac(),
            'msqrt' => new Msqrt(),
            'mroot' => new Mroot(),
            'msub' => new Msub(),
            'msup' => new Msup(),
            'msubsup' => new Msubsup(),
            'munder' => new Munder(),
            'mover' => new Mover(),
            'munderover' => new Munderover(),
            'mmultiscripts' => new Mmultiscripts(),
            // Mprescripts and NoneElement are children of
            // mmultiscripts — they don't render on their own but
            // they need typed identity so the painter can scan
            // mmultiscripts's child list for the separator and
            // for absent-script placeholders.
            'mprescripts' => new Mprescripts(),
            'none' => new NoneElement(),
            'mtable' => new Mtable(),
            'mtr' => new Mtr(),
            'mtd' => new Mtd(),
            'mspace' => new Mspace(),
            'mpadded' => new Mpadded(),
            'mphantom' => new Mphantom(),
            'menclose' => new Menclose(),
            'mstyle' => new Mstyle(),
            // The MathML Core v1 element set is now complete. Anything
            // else (deprecated MathML 3 holdovers like mlabeledtr,
            // mglyph, mstack, mlongdiv, or Content MathML) round-trips
            // through GenericElement so a future Translator can pick
            // it up without revisiting the parser.
            default => new GenericElement($localName),
        };
    }
}
