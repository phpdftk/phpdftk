<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §15.3 — `<title>` element. Accessibility-tree title for
 * the closest container. Never renders directly. The PDF
 * writer's tagged-PDF path uses the title to populate the
 * structure tree's title attribute on the matching mark.
 */
final class Title extends Element
{
    public function __construct()
    {
        parent::__construct('title');
    }

    public function text(): string
    {
        return trim($this->collectText($this));
    }

    private function collectText(Element $element): string
    {
        $out = '';
        foreach ($element->children as $child) {
            if ($child instanceof Text) {
                $out .= $child->data;
            } elseif ($child instanceof Element) {
                $out .= $this->collectText($child);
            }
        }
        return $out;
    }
}
