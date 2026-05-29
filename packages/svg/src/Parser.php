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

/**
 * Secure SVG-to-typed-tree parser. Consumes a string of SVG XML and
 * returns an `SvgDocument`.
 *
 * Security posture (see the Security section of `docs/plans/html-and-svg.md`):
 *
 *  - **External entities disabled.** The libxml options used
 *    (`LIBXML_NONET | LIBXML_NOENT` with `LIBXML_NOENT` deliberately
 *    omitted) prevent the parser from substituting external entity
 *    references — so a DOCTYPE that declares `<!ENTITY x SYSTEM "file:///…">`
 *    cannot exfiltrate file contents.
 *  - **No network access.** `LIBXML_NONET` blocks any URL fetch the parser
 *    might otherwise perform for entities or DTDs.
 *  - **XInclude rejected.** We never call `DOMDocument::xinclude()`.
 *    A document that contains `<xi:include …/>` is parsed verbatim — the
 *    element is preserved as an unknown element, not resolved.
 *
 * Unknown elements outside the implemented v1 subset are preserved as
 * generic `Element` instances so sanitiser-style callers can inspect them.
 * Phase-3 follow-ups will add typed classes for the remaining shapes and
 * structural elements.
 */
final class Parser
{
    /** SVG namespace URI per spec. */
    public const string SVG_NS = 'http://www.w3.org/2000/svg';

    public function parse(string $xml): SvgDocument
    {
        if (trim($xml) === '') {
            throw new InvalidSvgException('Cannot parse an empty SVG document.');
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            // LIBXML_NONET — no network fetches.
            // LIBXML_NOERROR / LIBXML_NOWARNING — drop spam; we check
            // libxml_get_errors() ourselves below.
            // LIBXML_NOENT is INTENTIONALLY OMITTED — substituting
            // entities is the XXE attack vector.
            $loaded = $dom->loadXML(
                $xml,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
            if (!$loaded) {
                $errors = libxml_get_errors();
                $first = $errors[0] ?? null;
                throw new InvalidSvgException(
                    $first === null
                        ? 'Failed to parse SVG XML.'
                        : sprintf('Failed to parse SVG XML: %s', trim($first->message)),
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
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
            // Strip the xmlns declarations themselves — they're
            // already implied by the namespaceURI of each node.
            if ($attr->prefix === 'xmlns' || $attr->name === 'xmlns') {
                continue;
            }
            // Use the qualified node name so `href` and `xlink:href`
            // remain distinct keys — libxml's `$attr->name` returns
            // just the local name, which would collapse the two.
            $dest->attributes[$attr->nodeName] = $attr->value;
        }
    }

    private function copyChildren(\DOMElement $src, Element $dest): void
    {
        foreach ($src->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $dest->appendChild(new Text($child->data));
                continue;
            }
            if ($child instanceof \DOMElement) {
                $dest->appendChild($this->buildElement($child));
            }
            // Comments / processing instructions / CDATA — drop. SVG 2
            // doesn't carry semantic meaning in any of them at the
            // parser level (CSS-inside-SVG via `<style>` reads as text,
            // not CDATA-specifically).
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
            default => new GenericElement($localName),
        };
    }
}
