<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

/**
 * PDF specification versions — ISO 32000-1 (1.x) and ISO 32000-2 (2.0).
 *
 * Used to declare minimum version requirements for PDF features and to
 * control the version written to the %PDF-X.Y file header.
 */
enum PdfVersion: string
{
    case V1_0 = '1.0';
    case V1_1 = '1.1';
    case V1_2 = '1.2';
    case V1_3 = '1.3';
    case V1_4 = '1.4';
    case V1_5 = '1.5';
    case V1_6 = '1.6';
    case V1_7 = '1.7';
    case V2_0 = '2.0';

    public function isAtLeast(self $other): bool
    {
        return version_compare($this->value, $other->value, '>=');
    }

    public function isGreaterThan(self $other): bool
    {
        return version_compare($this->value, $other->value, '>');
    }

    /** Return the higher of two versions. */
    public function max(self $other): self
    {
        return $this->isAtLeast($other) ? $this : $other;
    }

    /** Parse a version string like '1.7' into an enum case, or null. */
    public static function fromString(string $version): ?self
    {
        return self::tryFrom($version);
    }
}
