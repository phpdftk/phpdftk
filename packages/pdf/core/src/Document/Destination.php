<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNull;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Explicit destination array (ISO 32000-2 Table 148).
 *
 * Represents a destination within a PDF document, specifying a page
 * and the desired view when navigating to that page.
 */
class Destination implements Serializable
{
    /**
     * @param array<int, float|null> $params
     */
    private function __construct(
        private PdfReference $page,
        private PdfName $type,
        private array $params,
    ) {
    }

    /**
     * /XYZ left top zoom — display page at given position and zoom.
     * Null parameters indicate "unchanged".
     */
    public static function xyz(PdfReference $page, ?float $left = null, ?float $top = null, ?float $zoom = null): self
    {
        return new self($page, new PdfName('XYZ'), [$left, $top, $zoom]);
    }

    /**
     * /Fit — fit entire page in window.
     */
    public static function fit(PdfReference $page): self
    {
        return new self($page, new PdfName('Fit'), []);
    }

    /**
     * /FitH top — fit page width, position at top coordinate.
     */
    public static function fitH(PdfReference $page, ?float $top = null): self
    {
        return new self($page, new PdfName('FitH'), [$top]);
    }

    /**
     * /FitV left — fit page height, position at left coordinate.
     */
    public static function fitV(PdfReference $page, ?float $left = null): self
    {
        return new self($page, new PdfName('FitV'), [$left]);
    }

    /**
     * /FitR left bottom right top — fit rectangle in window.
     */
    public static function fitR(PdfReference $page, float $left, float $bottom, float $right, float $top): self
    {
        return new self($page, new PdfName('FitR'), [$left, $bottom, $right, $top]);
    }

    /**
     * /FitB — fit bounding box in window.
     */
    public static function fitB(PdfReference $page): self
    {
        return new self($page, new PdfName('FitB'), []);
    }

    /**
     * /FitBH top — fit bounding box width, position at top.
     */
    public static function fitBH(PdfReference $page, ?float $top = null): self
    {
        return new self($page, new PdfName('FitBH'), [$top]);
    }

    /**
     * /FitBV left — fit bounding box height, position at left.
     */
    public static function fitBV(PdfReference $page, ?float $left = null): self
    {
        return new self($page, new PdfName('FitBV'), [$left]);
    }

    public function toPdf(): string
    {
        $items = [$this->page, $this->type];

        foreach ($this->params as $param) {
            if ($param === null) {
                $items[] = new PdfNull();
            } else {
                $items[] = new PdfNumber($param);
            }
        }

        return (new PdfArray($items))->toPdf();
    }
}
