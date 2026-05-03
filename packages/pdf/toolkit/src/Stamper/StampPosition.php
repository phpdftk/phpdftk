<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Stamper;

/**
 * Predefined stamp positions on a page.
 */
enum StampPosition
{
    case TopLeft;
    case TopCenter;
    case TopRight;
    case Center;
    case BottomLeft;
    case BottomCenter;
    case BottomRight;

    /**
     * Compute X/Y coordinates for text placement given page dimensions and margins.
     *
     * @return array{float, float} [x, y]
     */
    public function computeCoordinates(
        float $pageWidth,
        float $pageHeight,
        float $contentWidth,
        float $contentHeight,
        float $margin = 36.0,
    ): array {
        return match ($this) {
            self::TopLeft => [$margin, $pageHeight - $margin - $contentHeight],
            self::TopCenter => [($pageWidth - $contentWidth) / 2, $pageHeight - $margin - $contentHeight],
            self::TopRight => [$pageWidth - $margin - $contentWidth, $pageHeight - $margin - $contentHeight],
            self::Center => [($pageWidth - $contentWidth) / 2, ($pageHeight - $contentHeight) / 2],
            self::BottomLeft => [$margin, $margin],
            self::BottomCenter => [($pageWidth - $contentWidth) / 2, $margin],
            self::BottomRight => [$pageWidth - $margin - $contentWidth, $margin],
        };
    }
}
