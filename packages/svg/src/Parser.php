<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

use Phpdftk\Svg\Exception\InvalidSvgException;
use Phpdftk\Svg\Shape\Circle;
use Phpdftk\Svg\Shape\Ellipse;
use Phpdftk\Svg\Shape\Line;
use Phpdftk\Svg\Shape\Polygon;
use Phpdftk\Svg\Shape\Polyline;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Xml\Exception\InvalidXmlException;
use Phpdftk\Xml\HardenedLoader;
use Phpdftk\Xml\TreeWalker;

/**
 * Secure SVG-to-typed-tree parser. Consumes a string of SVG XML and
 * returns an `SvgDocument`.
 *
 * Routing through {@see HardenedLoader} for libxml + {@see TreeWalker}
 * for the DOM walk means this parser's only format-specific code is
 * the root validation (must be `<svg>` in `SVG_NS`) and the
 * `makeElementForName` typed-element factory. The security boundary
 * (no entity substitution, no network fetches, no XInclude) is owned
 * by `HardenedLoader` so SVG and MathML cannot drift.
 *
 * Unknown elements outside the implemented v1 subset are preserved as
 * generic `Element` instances so sanitiser-style callers can inspect
 * them.
 */
final class Parser
{
    /** SVG namespace URI per spec. */
    public const string SVG_NS = 'http://www.w3.org/2000/svg';

    public function __construct(
        private readonly HardenedLoader $loader = new HardenedLoader(),
        private readonly TreeWalker $walker = new TreeWalker(),
    ) {}

    public function parse(string $xml): SvgDocument
    {
        try {
            $dom = $this->loader->load($xml);
        } catch (InvalidXmlException $e) {
            // Re-throw as the format-specific exception so consumers
            // can keep a single catch block per parser. The original
            // libxml message is preserved via the previous exception.
            // The error text is reflavoured to mention "SVG" so the
            // consumer's logs make the format clear.
            $message = str_replace(
                'parse XML',
                'parse SVG XML',
                $e->getMessage(),
            );
            throw new InvalidSvgException($message, 0, $e);
        }

        $root = $dom->documentElement;
        if ($root === null) {
            throw new InvalidSvgException('SVG document has no root element.');
        }
        if ($root->localName !== 'svg') {
            throw new InvalidSvgException(sprintf(
                'Expected <svg> root element, got <%s>.',
                $root->localName,
            ));
        }
        if ($root->namespaceURI !== null && $root->namespaceURI !== self::SVG_NS) {
            throw new InvalidSvgException(sprintf(
                'Root <svg> declared in unexpected namespace %s.',
                $root->namespaceURI,
            ));
        }

        $doc = new SvgDocument();
        $this->walker->walk(
            $root,
            $doc,
            createElement: fn(string $localName) => $this->makeElementForName($localName),
            createText: fn(string $data) => new Text($data),
            setAttribute: static fn(Element $el, string $name, string $value) => $el->setAttribute($name, $value),
            appendChild: static fn(Element $parent, Node $child) => $parent->appendChild($child),
        );
        return $doc;
    }

    private function makeElementForName(string $localName): Element
    {
        return match ($localName) {
            'rect' => new Rect(),
            'circle' => new Circle(),
            'ellipse' => new Ellipse(),
            'line' => new Line(),
            'polyline' => new Polyline(),
            'polygon' => new Polygon(),
            'g' => new Group(),
            'path' => new Path(),
            'text' => new Text\TextElement(),
            'tspan' => new Text\Tspan(),
            'defs' => new Defs(),
            'symbol' => new Symbol(),
            'use' => new Use_(),
            'clipPath' => new ClipPath(),
            'mask' => new Mask(),
            'image' => new Image(),
            'linearGradient' => new Gradient\LinearGradient(),
            'radialGradient' => new Gradient\RadialGradient(),
            'stop' => new Gradient\Stop(),
            'style' => new StyleElement(),
            // SVG 2 §12.1.1 — anchor container; PDF maps to a link
            // annotation when an href is present.
            'a' => new A_(),
            // SVG 2 §15.3 — accessibility metadata; never paints.
            'title' => new Title(),
            'desc' => new Desc(),
            // SVG 2 §5.7 — conditional rendering container.
            'switch' => new Switch_(),
            // SVG 2 §11.6 — foreign-content placeholder.
            'foreignObject' => new ForeignObject(),
            // SVG 2 §11.6 — vertex-marker definition (arrowheads etc.)
            'marker' => new Marker(),
            // SVG 2 §13.3 — tiled fill pattern definition.
            'pattern' => new Pattern(),
            // SVG 2 Filter Effects §6.1 — filter graph definition.
            'filter' => new Filter(),
            // SVG 2 Filter Effects §15 — filter primitives that
            // live inside `<filter>`. Each lifts to its own typed
            // class for accessor convenience.
            'feGaussianBlur' => new Filter\FeGaussianBlur(),
            'feOffset' => new Filter\FeOffset(),
            'feFlood' => new Filter\FeFlood(),
            'feBlend' => new Filter\FeBlend(),
            'feComposite' => new Filter\FeComposite(),
            'feMorphology' => new Filter\FeMorphology(),
            'feMerge' => new Filter\FeMerge(),
            'feMergeNode' => new Filter\FeMergeNode(),
            'feColorMatrix' => new Filter\FeColorMatrix(),
            'feDropShadow' => new Filter\FeDropShadow(),
            'feTurbulence' => new Filter\FeTurbulence(),
            'feImage' => new Filter\FeImage(),
            'feTile' => new Filter\FeTile(),
            'feDisplacementMap' => new Filter\FeDisplacementMap(),
            'feConvolveMatrix' => new Filter\FeConvolveMatrix(),
            'feComponentTransfer' => new Filter\FeComponentTransfer(),
            'feFuncR' => new Filter\FeFuncR(),
            'feFuncG' => new Filter\FeFuncG(),
            'feFuncB' => new Filter\FeFuncB(),
            'feFuncA' => new Filter\FeFuncA(),
            'feDiffuseLighting' => new Filter\FeDiffuseLighting(),
            'feSpecularLighting' => new Filter\FeSpecularLighting(),
            'feDistantLight' => new Filter\FeDistantLight(),
            'fePointLight' => new Filter\FePointLight(),
            'feSpotLight' => new Filter\FeSpotLight(),
            // SVG 2 §6.3 — declarative named viewport.
            'view' => new View(),
            // SVG 2 §15.2 — out-of-scope script content; the typed
            // class lets the Translator skip it explicitly rather
            // than recursing into any nested `<text>` etc. children
            // a malicious document might smuggle in.
            'script' => new Script(),
            // SVG 2 §19 — animation elements. Out of scope for the
            // static print medium; typed for explicit Translator
            // skip and external-tooling recognition.
            'animate' => new Animate(),
            'animateTransform' => new AnimateTransform(),
            'animateMotion' => new AnimateMotion(),
            'set' => new SetElement(),
            'mpath' => new MPath(),
            // SVG 2 §6.4 — RDF/metadata, never renders.
            'metadata' => new Metadata(),
            default => new GenericElement($localName),
        };
    }
}
