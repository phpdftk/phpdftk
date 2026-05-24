<?php

declare(strict_types=1);

namespace Phpdftk\Html;

use Phpdftk\Html\Dom\Comment;
use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\DocumentFragment;
use Phpdftk\Html\Dom\DocumentType;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\HTMLTemplateElement;
use Phpdftk\Html\Dom\Node;
use Phpdftk\Html\Dom\ShadowRoot;
use Phpdftk\Html\Dom\Text;

/**
 * HTML5 fragment serializer per WHATWG §13.3. Re-emits a DOM as conformant
 * HTML5 text; serialize→parse round-trips for any DOM the parser produced.
 *
 * Per Q11/contract: shadow roots marked serializable=true are emitted as
 * <template shadowrootmode="open|closed">…</template>; otherwise omitted
 * (the host element appears without its shadow content).
 *
 * Phase 1B.1 ships the structural traversal; the full character-escape
 * tables (the named-entity reverse map plus the void-element and raw-text
 * element registries) land alongside the tokenizer in Phase 1B.2.
 */
final class Serializer
{
    /** HTML void elements — emit as <foo> with no closing tag. */
    private const array VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'source', 'track', 'wbr',
    ];

    /** Raw-text elements — children are serialised verbatim (no escaping). */
    private const array RAW_TEXT_ELEMENTS = [
        'script', 'style', 'xmp', 'iframe', 'noembed', 'noframes', 'plaintext', 'noscript',
    ];

    public function serialize(Node $node): string
    {
        return $this->serializeNode($node, parentRawText: false);
    }

    private function serializeNode(Node $node, bool $parentRawText): string
    {
        return match (true) {
            $node instanceof Document, $node instanceof DocumentFragment
                => $this->serializeChildren($node, $parentRawText),
            $node instanceof DocumentType => sprintf('<!DOCTYPE %s>', $node->name),
            $node instanceof Text => $parentRawText ? $node->data : $this->escapeText($node->data),
            $node instanceof Comment => '<!--' . $node->data . '-->',
            $node instanceof Element => $this->serializeElement($node),
            default => '',
        };
    }

    private function serializeElement(Element $el): string
    {
        $tag = $el->localName;
        $out = '<' . $tag;
        foreach ($el->attributes() as $attr) {
            $out .= ' ' . $attr->qualifiedName() . '="' . $this->escapeAttribute($attr->value) . '"';
        }
        if (in_array($tag, self::VOID_ELEMENTS, true)) {
            return $out . '>';
        }
        $out .= '>';

        $rawText = in_array($tag, self::RAW_TEXT_ELEMENTS, true);

        // Per HTML §13.3: if the element has a serializable shadow root,
        // emit it first as <template shadowrootmode>…</template>.
        $shadow = $el->shadowRoot;
        if ($shadow !== null && $shadow->serializable) {
            $out .= sprintf(
                '<template shadowrootmode="%s"%s%s shadowrootserializable="">',
                $shadow->mode->name === 'Open' ? 'open' : 'closed',
                $shadow->delegatesFocus ? ' shadowrootdelegatesfocus=""' : '',
                $shadow->clonable ? ' shadowrootclonable=""' : '',
            );
            $out .= $this->serializeChildren($shadow, parentRawText: false);
            $out .= '</template>';
        }

        // <template> children live in its `content` fragment, not directly.
        if ($el instanceof HTMLTemplateElement && $el->content !== null) {
            $out .= $this->serializeChildren($el->content, $rawText);
        } else {
            $out .= $this->serializeChildren($el, $rawText);
        }
        $out .= '</' . $tag . '>';
        return $out;
    }

    private function serializeChildren(Node $node, bool $parentRawText): string
    {
        $out = '';
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            $out .= $this->serializeNode($n, $parentRawText);
        }
        return $out;
    }

    private function escapeText(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            "\u{00A0}" => '&nbsp;',
        ]);
    }

    private function escapeAttribute(string $value): string
    {
        return strtr($value, [
            '&' => '&amp;',
            '"' => '&quot;',
            "\u{00A0}" => '&nbsp;',
        ]);
    }
}
