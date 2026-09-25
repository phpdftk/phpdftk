<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Mathml;

use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Measure an inline `<math>` subtree during LAYOUT.
 *
 * {@see MathmlRenderer::intrinsicSize()} could always do this, but its
 * only caller was the painter — which runs after layout has already
 * placed every box. So layout sized the `<math>` atomic inline at 0x0
 * and the painter then drew a correctly-sized equation into a box that
 * reserved no room for it: content after an equation overprinted it,
 * and a line holding nothing but math collapsed to the height of an
 * empty text line.
 *
 * `BlockLayout` consults this from its inline-atomic pre-pass, which is
 * the same point where an `inline-block`'s used content size is
 * resolved, so the result flows into the existing `laidOut*` protocol
 * without `InlineLayout` needing to know MathML exists.
 *
 * ### The scratch writer
 *
 * `MathmlRenderer` takes a page and a writer because it normally
 * paints. Measuring touches neither — it only needs the two standard
 * faces to exist so the paint context can be constructed. Layout has no
 * page yet (pagination creates them later), so this holds a scratch
 * `PdfWriter` whose output is discarded. It is created lazily and
 * reused, so documents without MathML pay nothing at all and a document
 * with many equations pays for one throwaway writer.
 *
 * The parsed-document cache is shared with {@see InlineMathmlAdapter} by
 * passing the painter's adapter in, so a `<math>` measured at layout and
 * painted afterwards is parsed once rather than twice.
 */
final class MathmlIntrinsicSizer
{
    private ?MathmlRenderer $renderer = null;

    public function __construct(
        private readonly InlineMathmlAdapter $adapter = new InlineMathmlAdapter(),
    ) {}

    /**
     * Intrinsic size of `$element`'s MathML content at `$fontSize`.
     *
     * Returns `[width, height, baseline]` in PDF user-space units,
     * where `baseline` is the distance DOWN from the content-box top
     * edge to the math baseline — the offset CSS 2.1 §10.8.1 aligns an
     * atomic inline on. Returns null when the element is not MathML,
     * fails to parse, or measures to nothing, so callers keep whatever
     * sizing they would otherwise have applied.
     *
     * A parse failure is swallowed for the same reason the painter
     * swallows it: one malformed expression must not poison the page.
     *
     * @return array{0: float, 1: float, 2: float}|null
     */
    public function measure(HtmlElement $element, float $fontSize): ?array
    {
        try {
            $document = $this->adapter->adapt($element);
        } catch (\Throwable) {
            return null;
        }
        $renderer = $this->renderer();
        [$width, $height] = $renderer->intrinsicSize($document, $fontSize);
        if ($width <= 0.0 && $height <= 0.0) {
            return null;
        }

        return [$width, $height, $renderer->intrinsicAscent($document, $fontSize)];
    }

    private function renderer(): MathmlRenderer
    {
        if ($this->renderer === null) {
            $writer = new PdfWriter();
            $this->renderer = new MathmlRenderer($writer->addPage(), $writer);
        }

        return $this->renderer;
    }
}
