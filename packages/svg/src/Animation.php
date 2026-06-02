<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * SVG 2 §19 — common base for animation elements (`<animate>`,
 * `<animateTransform>`, `<animateMotion>`, `<set>`). Animation
 * is out of scope for the static print medium, but the
 * elements still parse to typed classes so external tooling
 * (sanitisers, validators, optimisers) can recognise them
 * without re-walking attribute strings, and so the renderer
 * can explicitly skip them rather than recursing.
 *
 * Exposes the SMIL-style attribute surface authors target:
 * `attributeName`, `attributeType`, the timing keywords
 * (`begin` / `dur` / `end` / `repeatCount`), and the value
 * specification (`from` / `to` / `by` / `values`).
 */
abstract class Animation extends Element
{
    public function attributeName(): ?string
    {
        return $this->getAttribute('attributeName');
    }

    public function attributeType(): ?string
    {
        return $this->getAttribute('attributeType');
    }

    public function begin(): ?string
    {
        return $this->getAttribute('begin');
    }

    public function dur(): ?string
    {
        return $this->getAttribute('dur');
    }

    public function end(): ?string
    {
        return $this->getAttribute('end');
    }

    public function repeatCount(): ?string
    {
        return $this->getAttribute('repeatCount');
    }

    public function from(): ?string
    {
        return $this->getAttribute('from');
    }

    public function to(): ?string
    {
        return $this->getAttribute('to');
    }

    public function by(): ?string
    {
        return $this->getAttribute('by');
    }

    public function values(): ?string
    {
        return $this->getAttribute('values');
    }
}
