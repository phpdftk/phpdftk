<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Svg;

use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\HtmlToPdf\ForeignContent\DomXmlSerializer;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\SvgDocument;

/**
 * Convert an inline-SVG subtree from the HTML DOM into a typed
 * {@see SvgDocument} so the existing SVG renderer can paint it.
 *
 * Routes through {@see DomXmlSerializer} for the HTML-DOM-to-XML
 * walk + namespace plumbing, then hands the XML to the SVG parser.
 * The adapter's own responsibility is:
 *
 *   - Reject elements that aren't an `<svg>` in `SVG_NS`.
 *   - Cache parsed documents by element identity so the same
 *     `<svg>` painted on N pages only pays the parse cost once.
 *
 * The cache uses `===` identity, not equality — two distinct `<svg>`
 * elements with byte-identical markup get parsed once each.
 */
final class InlineSvgAdapter
{
    /** @var \SplObjectStorage<HtmlElement, SvgDocument> */
    private \SplObjectStorage $cache;

    public function __construct(
        private readonly SvgParser $parser = new SvgParser(),
        private readonly DomXmlSerializer $serializer = new DomXmlSerializer(),
    ) {
        $this->cache = new \SplObjectStorage();
    }

    /**
     * Adapt an HTML DOM `<svg>` element into a typed SvgDocument.
     *
     * @throws \InvalidArgumentException When the element isn't an
     *   `<svg>` in the SVG namespace.
     */
    public function adapt(HtmlElement $element): SvgDocument
    {
        // Accept both `<svg>` in the SVG namespace and the prefixed XHTML
        // form `<svg:svg>` (left as a plain HTML element by the HTML
        // parser). The serializer below re-namespaces the subtree before
        // the SVG parser sees it.
        $tag = strtolower($element->localName);
        $colon = strrpos($tag, ':');
        $local = $colon !== false ? substr($tag, $colon + 1) : $tag;
        $isSvgNs = $element->namespaceUri() === SvgParser::SVG_NS;
        if ($local !== 'svg' || (!$isSvgNs && $colon === false)) {
            throw new \InvalidArgumentException(sprintf(
                'InlineSvgAdapter expects an <svg> element in the SVG namespace '
                . '(or the prefixed <svg:svg> form), got <%s> in namespace %s.',
                $element->localName,
                $element->namespaceUri() ?? '(null)',
            ));
        }
        if ($this->cache->contains($element)) {
            return $this->cache[$element];
        }
        $xml = $this->serializer->serialize($element, SvgParser::SVG_NS);
        $svg = $this->parser->parse($xml);
        $this->cache[$element] = $svg;
        return $svg;
    }
}
