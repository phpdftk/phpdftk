<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §15.3 — `<desc>` element. Long-form description of the
 * containing SVG fragment. Like `<title>`, never renders directly
 * — purely accessibility metadata.
 */
final class Desc extends Element
{
    public function __construct()
    {
        parent::__construct('desc');
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
