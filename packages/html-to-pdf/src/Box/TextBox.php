<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Html\Dom\Element;

/**
 * Leaf box wrapping a text node. The originating DOM element is the
 * inline parent the text was a child of (text nodes themselves don't
 * have associated CSS rules); `$text` is the raw character data.
 *
 * White-space collapsing and bidi reorder happen during layout, not box
 * generation — `$text` keeps the source bytes verbatim.
 */
final class TextBox extends Box
{
    public function __construct(
        ?Element $element,
        CascadedValues $style,
        public readonly string $text,
    ) {
        parent::__construct($element, $style);
    }
}
