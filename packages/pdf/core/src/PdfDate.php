<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core;

/**
 * PDF date string helper — ISO 32000-2 §7.9.4.
 *
 * PDF dates are encoded as `D:YYYYMMDDHHmmSSOHH'mm`, where `O` is `+`,
 * `-`, or `Z`. Producing and consuming these strings by hand is
 * error-prone; this helper wraps PHP's DateTimeInterface.
 */
final class PdfDate
{
    /**
     * Format a PHP DateTime as a PDF date string and return it wrapped
     * in a PdfString.
     */
    public static function fromDateTime(\DateTimeInterface $dt): PdfString
    {
        $base = $dt->format('YmdHis');
        $offsetSeconds = $dt->getOffset();
        if ($offsetSeconds === 0) {
            $tz = 'Z';
        } else {
            $sign = $offsetSeconds >= 0 ? '+' : '-';
            $abs = abs($offsetSeconds);
            $hours = intdiv($abs, 3600);
            $minutes = intdiv($abs % 3600, 60);
            $tz = sprintf("%s%02d'%02d", $sign, $hours, $minutes);
        }
        return new PdfString('D:' . $base . $tz);
    }

    /**
     * Parse a PDF date string back into a DateTimeImmutable. Returns
     * null on unparseable input.
     */
    public static function parse(string $pdfDate): ?\DateTimeImmutable
    {
        if (!preg_match(
            "/^D?:?(\d{4})(\d{2})?(\d{2})?(\d{2})?(\d{2})?(\d{2})?([Z+-])?(\d{2})?'?(\d{2})?'?$/",
            $pdfDate,
            $m,
        )) {
            return null;
        }
        $year  = (int) $m[1];
        $month = (int) ($m[2] ?? 1);
        $day   = (int) ($m[3] ?? 1);
        $hour  = (int) ($m[4] ?? 0);
        $min   = (int) ($m[5] ?? 0);
        $sec   = (int) ($m[6] ?? 0);

        $tz = new \DateTimeZone('UTC');
        if (isset($m[7]) && $m[7] !== '' && $m[7] !== 'Z') {
            $sign = $m[7];
            $offH = (int) ($m[8] ?? 0);
            $offM = (int) ($m[9] ?? 0);
            $tz = new \DateTimeZone(sprintf('%s%02d:%02d', $sign, $offH, $offM));
        }

        try {
            return (new \DateTimeImmutable('now', $tz))
                ->setDate($year, $month, $day)
                ->setTime($hour, $min, $sec);
        } catch (\Throwable) {
            return null;
        }
    }
}
