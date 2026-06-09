<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Mathml;

use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\HtmlToPdf\ForeignContent\DomXmlSerializer;
use Phpdftk\Mathml\MathmlDocument;
use Phpdftk\Mathml\Parser as MathmlParser;

/**
 * Convert an inline-MathML subtree from the HTML DOM into a typed
 * {@see MathmlDocument} so {@see \Phpdftk\MathmlToPdf\MathmlRenderer}
 * can paint it.
 *
 * Sibling to {@see \Phpdftk\HtmlToPdf\Svg\InlineSvgAdapter} and
 * structurally identical: routes through {@see DomXmlSerializer}
 * for the HTML-DOM-to-XML walk + namespace plumbing, then hands the
 * XML to the MathML parser. The adapter's own responsibility is:
 *
 *   - Reject elements that aren't a `<math>` in `MATHML_NS`.
 *   - Cache parsed documents by element identity so the same
 *     `<math>` painted on N pages only pays the parse cost once.
 *
 * The cache uses `===` identity, not equality — two distinct `<math>`
 * elements with byte-identical markup get parsed once each.
 */
final class InlineMathmlAdapter
{
    /** @var \SplObjectStorage<HtmlElement, MathmlDocument> */
    private \SplObjectStorage $cache;

    public function __construct(
        private readonly MathmlParser $parser = new MathmlParser(),
        private readonly DomXmlSerializer $serializer = new DomXmlSerializer(),
    ) {
        $this->cache = new \SplObjectStorage();
    }

    /**
     * Adapt an HTML DOM `<math>` element into a typed MathmlDocument.
     *
     * @throws \InvalidArgumentException When the element isn't a
     *   `<math>` in the MathML namespace.
     */
    public function adapt(HtmlElement $element): MathmlDocument
    {
        if (strtolower($element->localName) !== 'math') {
            throw new \InvalidArgumentException(sprintf(
                'InlineMathmlAdapter expects a <math> element, got <%s>.',
                $element->localName,
            ));
        }
        if ($element->namespaceUri() !== MathmlParser::MATHML_NS) {
            throw new \InvalidArgumentException(sprintf(
                'InlineMathmlAdapter expects namespace %s, got %s.',
                MathmlParser::MATHML_NS,
                $element->namespaceUri() ?? '(null)',
            ));
        }
        if ($this->cache->contains($element)) {
            return $this->cache[$element];
        }
        $xml = $this->serializer->serialize($element, MathmlParser::MATHML_NS);
        $math = $this->parser->parse($xml);
        $this->cache[$element] = $math;
        return $math;
    }
}
