<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Profile;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * PDF/X conformance profiles (ISO 15930).
 *
 * PDF/X-1a:2003 (ISO 15930-4): CMYK+spot only, no transparency, PDF 1.3
 * PDF/X-3:2003  (ISO 15930-6): ICC-based color allowed, PDF 1.3
 * PDF/X-4       (ISO 15930-7): transparency allowed, PDF 1.6
 * PDF/X-5g      (ISO 15930-8): external graphical content, PDF 1.6
 * PDF/X-5pg     (ISO 15930-8): partial external graphical content, PDF 1.6
 * PDF/X-5n      (ISO 15930-9): n-color external content, PDF 1.6
 */
enum PdfXProfile: string implements ConformanceProfile
{
    case X1a2003 = 'X-1a:2003';
    case X32003  = 'X-3:2003';
    case X4      = 'X-4';
    case X5g     = 'X-5g';
    case X5pg    = 'X-5pg';
    case X5n     = 'X-5n';

    public function getFamily(): string
    {
        return 'PDF/X';
    }

    public function getLevel(): string
    {
        return $this->value;
    }

    public function getPdfVersion(): PdfVersion
    {
        return match ($this) {
            self::X1a2003, self::X32003 => PdfVersion::V1_3,
            self::X4, self::X5g, self::X5pg, self::X5n => PdfVersion::V1_6,
        };
    }

    public function getXmpNamespace(): string
    {
        return 'http://www.npes.org/pdfx/ns/id/';
    }

    public function getXmpPrefix(): string
    {
        return 'pdfxid';
    }

    public function getXmpProperties(): array
    {
        return ['GTS_PDFXVersion' => match ($this) {
            self::X1a2003 => 'PDF/X-1a:2003',
            self::X32003  => 'PDF/X-3:2003',
            self::X4      => 'PDF/X-4',
            self::X5g     => 'PDF/X-5g',
            self::X5pg    => 'PDF/X-5pg',
            self::X5n     => 'PDF/X-5n',
        }];
    }

    /** Whether this profile prohibits transparency. */
    public function prohibitsTransparency(): bool
    {
        return match ($this) {
            self::X1a2003, self::X32003 => true,
            self::X4, self::X5g, self::X5pg, self::X5n => false,
        };
    }

    /** The required OutputIntent /S subtype value. */
    public function getOutputIntentSubtype(): string
    {
        return 'GTS_PDFX';
    }

    /** Whether this profile supports reference XObjects for external content. */
    public function supportsReferenceXObjects(): bool
    {
        return match ($this) {
            self::X5g, self::X5pg, self::X5n => true,
            default => false,
        };
    }
}
