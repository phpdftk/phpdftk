<?php

declare(strict_types=1);

namespace ApprLabs\Encoding;

/**
 * Predefined CMap names for CJK font encoding — ISO 32000-2 §9.7.5.
 *
 * These CMap names are built into conforming PDF viewers and don't need
 * to be embedded as CMap streams. They map character codes to CIDs for
 * the four Adobe CJK character collections.
 *
 * Use these with Type0Font as the /Encoding value when working with
 * CJK fonts that follow the standard Adobe CID collections.
 */
final class PredefinedCMap
{
    // -----------------------------------------------------------------------
    // Adobe-Japan1 (Japanese)
    // -----------------------------------------------------------------------
    public const JAPAN_EUC = '83pv-RKSJ-H';
    public const JAPAN_SJIS_H = '90ms-RKSJ-H';
    public const JAPAN_SJIS_V = '90ms-RKSJ-V';
    public const JAPAN_UCS2_H = 'UniJIS-UCS2-H';
    public const JAPAN_UCS2_V = 'UniJIS-UCS2-V';
    public const JAPAN_UTF16_H = 'UniJIS-UTF16-H';
    public const JAPAN_UTF16_V = 'UniJIS-UTF16-V';

    // -----------------------------------------------------------------------
    // Adobe-Korea1 (Korean)
    // -----------------------------------------------------------------------
    public const KOREA_UHC_H = 'KSCms-UHC-H';
    public const KOREA_UHC_V = 'KSCms-UHC-V';
    public const KOREA_UCS2_H = 'UniKS-UCS2-H';
    public const KOREA_UCS2_V = 'UniKS-UCS2-V';
    public const KOREA_UTF16_H = 'UniKS-UTF16-H';
    public const KOREA_UTF16_V = 'UniKS-UTF16-V';

    // -----------------------------------------------------------------------
    // Adobe-GB1 (Simplified Chinese)
    // -----------------------------------------------------------------------
    public const GB_GBK_H = 'GBK-EUC-H';
    public const GB_GBK_V = 'GBK-EUC-V';
    public const GB_UCS2_H = 'UniGB-UCS2-H';
    public const GB_UCS2_V = 'UniGB-UCS2-V';
    public const GB_UTF16_H = 'UniGB-UTF16-H';
    public const GB_UTF16_V = 'UniGB-UTF16-V';

    // -----------------------------------------------------------------------
    // Adobe-CNS1 (Traditional Chinese)
    // -----------------------------------------------------------------------
    public const CNS_BIG5_H = 'ETen-B5-H';
    public const CNS_BIG5_V = 'ETen-B5-V';
    public const CNS_UCS2_H = 'UniCNS-UCS2-H';
    public const CNS_UCS2_V = 'UniCNS-UCS2-V';
    public const CNS_UTF16_H = 'UniCNS-UTF16-H';
    public const CNS_UTF16_V = 'UniCNS-UTF16-V';

    // -----------------------------------------------------------------------
    // Identity mappings (already used, listed for completeness)
    // -----------------------------------------------------------------------
    public const IDENTITY_H = 'Identity-H';
    public const IDENTITY_V = 'Identity-V';

    /**
     * Get the CIDSystemInfo registry/ordering/supplement for a CMap name.
     *
     * @return array{registry: string, ordering: string, supplement: int}|null
     */
    public static function getCIDSystemInfo(string $cmapName): ?array
    {
        if (str_contains($cmapName, 'Japan') || str_contains($cmapName, 'JIS') || str_contains($cmapName, 'RKSJ') || str_contains($cmapName, 'pv-RKSJ')) {
            return ['registry' => 'Adobe', 'ordering' => 'Japan1', 'supplement' => 6];
        }
        if (str_contains($cmapName, 'Korea') || str_contains($cmapName, 'KS') || str_contains($cmapName, 'UniKS')) {
            return ['registry' => 'Adobe', 'ordering' => 'Korea1', 'supplement' => 2];
        }
        if (str_contains($cmapName, 'GB') || str_contains($cmapName, 'UniGB')) {
            return ['registry' => 'Adobe', 'ordering' => 'GB1', 'supplement' => 5];
        }
        if (str_contains($cmapName, 'CNS') || str_contains($cmapName, 'B5') || str_contains($cmapName, 'UniCNS')) {
            return ['registry' => 'Adobe', 'ordering' => 'CNS1', 'supplement' => 7];
        }
        if ($cmapName === 'Identity-H' || $cmapName === 'Identity-V') {
            return ['registry' => 'Adobe', 'ordering' => 'Identity', 'supplement' => 0];
        }
        return null;
    }

    /**
     * Check if a CMap name is a predefined CMap (built into PDF viewers).
     */
    public static function isPredefined(string $cmapName): bool
    {
        return self::getCIDSystemInfo($cmapName) !== null;
    }
}
