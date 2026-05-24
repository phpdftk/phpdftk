<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Holds the optional per-page render hooks registered on a {@see Pdf}
 * document: header, footer, and watermark closures.
 *
 * Each closure has the signature `function(PageContext $ctx): void` and
 * is invoked once per page during a deferred render pass, after all
 * flow content has been placed but before the PDF bytes are generated.
 */
final class PageDecorator
{
    public function __construct(
        public readonly ?\Closure $header = null,
        public readonly ?\Closure $footer = null,
        public readonly ?\Closure $watermark = null,
    ) {}

    public function withHeader(?\Closure $header): self
    {
        return new self($header, $this->footer, $this->watermark);
    }

    public function withFooter(?\Closure $footer): self
    {
        return new self($this->header, $footer, $this->watermark);
    }

    public function withWatermark(?\Closure $watermark): self
    {
        return new self($this->header, $this->footer, $watermark);
    }

    public function isEmpty(): bool
    {
        return $this->header === null && $this->footer === null && $this->watermark === null;
    }
}
