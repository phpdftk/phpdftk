<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Profile;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * PDF/A conformance profiles (ISO 19005).
 *
 * PDF/A-1 (ISO 19005-1): based on PDF 1.4
 * PDF/A-2 (ISO 19005-2): based on PDF 1.7
 * PDF/A-3 (ISO 19005-3): based on PDF 1.7, allows embedded files
 * PDF/A-4 (ISO 19005-4): based on PDF 2.0
 */
enum PdfAProfile: string implements ConformanceProfile
{
    case A1a = '1a';
    case A1b = '1b';
    case A2a = '2a';
    case A2b = '2b';
    case A2u = '2u';
    case A3a = '3a';
    case A3b = '3b';
    case A3u = '3u';
    case A4  = '4';
    case A4e = '4e';
    case A4f = '4f';

    public function getFamily(): string
    {
        return 'PDF/A';
    }

    public function getLevel(): string
    {
        return $this->value;
    }

    /** ISO 19005 part number (1, 2, 3, or 4). */
    public function getPart(): int
    {
        return match ($this) {
            self::A1a, self::A1b => 1,
            self::A2a, self::A2b, self::A2u => 2,
            self::A3a, self::A3b, self::A3u => 3,
            self::A4, self::A4e, self::A4f => 4,
        };
    }

    /** Conformance level letter (a, b, u, e, f) or null for PDF/A-4 base. */
    public function getConformanceLevel(): ?string
    {
        return match ($this) {
            self::A1a, self::A2a, self::A3a => 'A',
            self::A1b, self::A2b, self::A3b => 'B',
            self::A2u, self::A3u => 'U',
            self::A4e => 'E',
            self::A4f => 'F',
            self::A4 => null,
        };
    }

    public function getPdfVersion(): PdfVersion
    {
        return match ($this->getPart()) {
            1 => PdfVersion::V1_4,
            2, 3 => PdfVersion::V1_7,
            4 => PdfVersion::V2_0,
        };
    }

    public function getXmpNamespace(): string
    {
        return 'http://www.aiim.org/pdfa/ns/id/';
    }

    public function getXmpPrefix(): string
    {
        return 'pdfaid';
    }

    public function getXmpProperties(): array
    {
        $props = ['part' => (string) $this->getPart()];
        $level = $this->getConformanceLevel();
        if ($level !== null) {
            $props['conformance'] = $level;
        }
        return $props;
    }

    /** Whether this profile requires Level A (tagged PDF). */
    public function requiresTaggedStructure(): bool
    {
        return match ($this) {
            self::A1a, self::A2a, self::A3a => true,
            default => false,
        };
    }

    /** Whether this profile prohibits transparency (PDF/A-1 only). */
    public function prohibitsTransparency(): bool
    {
        return $this->getPart() === 1;
    }

    /** Whether this profile allows embedded files (PDF/A-3+ only). */
    public function allowsEmbeddedFiles(): bool
    {
        return $this->getPart() >= 3;
    }
}
