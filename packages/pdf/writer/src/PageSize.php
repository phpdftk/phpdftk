<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer;

/**
 * Standard page sizes, expressed in PDF user units (points, 1/72 inch).
 */
enum PageSize
{
    case Letter;   // 8.5 × 11 in
    case Legal;    // 8.5 × 14 in
    case Tabloid;  // 11 × 17 in
    case A3;       // 297 × 420 mm
    case A4;       // 210 × 297 mm
    case A5;       // 148 × 210 mm

    public function width(): float
    {
        return match ($this) {
            self::Letter  => 612.0,
            self::Legal   => 612.0,
            self::Tabloid => 792.0,
            self::A3      => 841.89,
            self::A4      => 595.28,
            self::A5      => 419.53,
        };
    }

    public function height(): float
    {
        return match ($this) {
            self::Letter  => 792.0,
            self::Legal   => 1008.0,
            self::Tabloid => 1224.0,
            self::A3      => 1190.55,
            self::A4      => 841.89,
            self::A5      => 595.28,
        };
    }
}
