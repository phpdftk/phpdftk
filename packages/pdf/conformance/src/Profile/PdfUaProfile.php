<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Profile;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * PDF/UA conformance profiles (ISO 14289).
 *
 * PDF/UA-1 (ISO 14289-1): based on PDF 1.7
 * PDF/UA-2 (ISO 14289-2:2024): based on PDF 2.0
 */
enum PdfUaProfile: string implements ConformanceProfile
{
    case UA1 = 'UA-1';
    case UA2 = 'UA-2';

    public function getFamily(): string
    {
        return 'PDF/UA';
    }

    public function getLevel(): string
    {
        return $this->value;
    }

    /** ISO 14289 part number (1 or 2). */
    public function getPart(): int
    {
        return match ($this) {
            self::UA1 => 1,
            self::UA2 => 2,
        };
    }

    public function getPdfVersion(): PdfVersion
    {
        return match ($this) {
            self::UA1 => PdfVersion::V1_7,
            self::UA2 => PdfVersion::V2_0,
        };
    }

    public function getXmpNamespace(): string
    {
        return 'http://www.aiim.org/pdfua/ns/id/';
    }

    public function getXmpPrefix(): string
    {
        return 'pdfuaid';
    }

    public function getXmpProperties(): array
    {
        return ['part' => (string) $this->getPart()];
    }
}
