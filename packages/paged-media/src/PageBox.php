<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia;

use Phpdftk\Geometry\Rectangle;

/**
 * Concrete page geometry for one rendered page — size, margins, and
 * the resolved content area where the document flow lives.
 *
 * CSS Paged Media 3 §3 defines the page model: the page sheet (the
 * physical PDF page) contains the page area (everything inside
 * page-margin minus marginalia), which contains the page content
 * area where document flow renders.
 *
 *   page sheet     = full PDF page (size from @page { size })
 *   page area      = sheet minus page-margin
 *   content area   = page area; the box document content flows into
 *
 * Margin boxes occupy the band between the sheet edge and the
 * content area, positioned per {@see MarginBoxPosition}.
 *
 * Phase 4G.1 (extraction) constructs PageBox instances from the
 * resolved `@page` cascade, then passes them to the painter.
 */
final readonly class PageBox
{
    public function __construct(
        public Rectangle $size,
        public PageMargin $margin,
    ) {}

    /**
     * The content area — page sheet minus the four margins. This
     * is where document flow renders; everything outside is the
     * marginalia band.
     */
    public function contentArea(): Rectangle
    {
        return new Rectangle(
            $this->margin->left,
            $this->margin->bottom,
            $this->size->width - $this->margin->left - $this->margin->right,
            $this->size->height - $this->margin->top - $this->margin->bottom,
        );
    }

    /**
     * Letter (US default) — 612 × 792 PDF points, 72-point margins
     * on every edge.
     */
    public static function letter(): self
    {
        return new self(
            new Rectangle(0.0, 0.0, 612.0, 792.0),
            PageMargin::uniform(72.0),
        );
    }

    /**
     * A4 — 595 × 842 PDF points, 72-point margins on every edge.
     */
    public static function a4(): self
    {
        return new self(
            new Rectangle(0.0, 0.0, 595.0, 842.0),
            PageMargin::uniform(72.0),
        );
    }
}
