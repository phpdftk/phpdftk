<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Profile;

use Phpdftk\Pdf\Core\PdfVersion;

/**
 * PDF/mail conformance profile (ISO 23053-2).
 *
 * Restricted PDF profile for email-safe documents. Prohibits encryption,
 * JavaScript, interactive forms, and multimedia content. All fonts must
 * be embedded.
 */
enum PdfMailProfile: string implements ConformanceProfile
{
    case Mail1 = 'mail-1';

    public function getFamily(): string
    {
        return 'PDF/mail';
    }

    public function getLevel(): string
    {
        return '1';
    }

    public function getPdfVersion(): PdfVersion
    {
        return PdfVersion::V2_0;
    }

    public function getXmpNamespace(): string
    {
        return 'http://www.pdfa.org/pdfmail/ns/id/';
    }

    public function getXmpPrefix(): string
    {
        return 'pdfmailid';
    }

    public function getXmpProperties(): array
    {
        return ['part' => '1'];
    }
}
