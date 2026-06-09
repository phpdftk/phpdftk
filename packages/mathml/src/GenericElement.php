<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * Fallback element for any tag the parser doesn't have a typed class
 * for yet. Lets a document parse cleanly even when it contains
 * MathML 3 elements that MathML Core dropped (`<mlabeledtr>`,
 * `<mglyph>`, …) or vendor extensions.
 *
 * The Translator skips generic elements at paint time but recurses
 * into their children so a stray wrapper doesn't lose everything
 * inside it.
 */
final class GenericElement extends Element {}
