<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Conformance;

use Phpdftk\Html\Dom\Comment;
use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentType;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\HTMLTemplateElement;
use Phpdftk\Html\Dom\Node;
use Phpdftk\Html\Dom\Text;

/**
 * Serialise a parsed DOM to the html5lib-tests canonical tree format so we
 * can compare against the `#document` section of a `.dat` file.
 *
 * Format (per https://github.com/html5lib/html5lib-tests):
 *
 *   | <!DOCTYPE html>          # doctype
 *   | <html>                   # element
 *   |   key="value"            # attribute (one per line, sorted alphabetically)
 *   |   <head>                 # nested element
 *   |   <body>
 *   |     "Hello"              # text node
 *   |     <p>
 *   |     <!-- comment -->     # comment
 *
 * Foreign-namespace elements are prefixed: `<svg svg>`, `<math math>`,
 * `<svg foreignObject>` (with the canonical case preserved). Attribute
 * names in foreign namespaces use `prefix attr` form.
 *
 * Template element content is rendered nested under `content` line.
 */
final class DomTreeSerializer
{
    public static function serialize(Document $document): string
    {
        $out = [];
        foreach ($document->childNodes() as $child) {
            self::walk($child, 0, $out);
        }
        return implode("\n", $out);
    }

    /** @param list<string> $out */
    private static function walk(Node $node, int $depth, array &$out): void
    {
        $indent = '| ' . str_repeat('  ', $depth);

        if ($node instanceof DocumentType) {
            $line = $indent . '<!DOCTYPE ' . $node->name;
            if ($node->publicId !== '' || $node->systemId !== '') {
                $line .= ' "' . $node->publicId . '" "' . $node->systemId . '"';
            }
            $line .= '>';
            $out[] = $line;
            return;
        }

        if ($node instanceof Element) {
            $localName = self::canonicalElementName($node);
            $out[] = $indent . '<' . $localName . '>';
            // Attributes sorted alphabetically by qualified name.
            $attrs = [];
            foreach ($node->attributes() as $attr) {
                $attrs[] = [self::canonicalAttrName($attr->localName, $attr->namespaceURI, $attr->prefix), $attr->value];
            }
            usort($attrs, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));
            foreach ($attrs as [$name, $value]) {
                $out[] = '| ' . str_repeat('  ', $depth + 1) . $name . '="' . $value . '"';
            }
            // Template content: render under "content" line.
            if ($node instanceof HTMLTemplateElement && $node->content !== null) {
                $out[] = '| ' . str_repeat('  ', $depth + 1) . 'content';
                foreach ($node->content->childNodes() as $child) {
                    self::walk($child, $depth + 2, $out);
                }
            }
            foreach ($node->childNodes() as $child) {
                self::walk($child, $depth + 1, $out);
            }
            return;
        }

        if ($node instanceof Text) {
            $out[] = $indent . '"' . $node->data . '"';
            return;
        }

        if ($node instanceof Comment) {
            $out[] = $indent . '<!-- ' . $node->data . ' -->';
            return;
        }
    }

    private static function canonicalElementName(Element $el): string
    {
        $ns = $el->namespaceURI;
        if ($ns === Document::SVG_NS) {
            return 'svg ' . $el->localName;
        }
        if ($ns === Document::MATHML_NS) {
            return 'math ' . $el->localName;
        }
        return $el->localName;
    }

    private static function canonicalAttrName(string $localName, string $namespace, ?string $prefix): string
    {
        if ($namespace === Document::XLINK_NS) {
            return 'xlink ' . $localName;
        }
        if ($namespace === Document::XML_NS) {
            return 'xml ' . $localName;
        }
        if ($namespace === Document::XMLNS_NS) {
            return 'xmlns ' . $localName;
        }
        if ($prefix !== null && $namespace !== Document::HTML_NS) {
            return $prefix . ' ' . $localName;
        }
        return $localName;
    }
}
