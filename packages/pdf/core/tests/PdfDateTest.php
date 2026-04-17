<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests;

use ApprLabs\Pdf\Core\PdfDate;
use PHPUnit\Framework\TestCase;

class PdfDateTest extends TestCase
{
    public function testFromDateTimeUtc(): void
    {
        $dt = new \DateTimeImmutable('2026-04-11T12:30:00', new \DateTimeZone('UTC'));
        $s = PdfDate::fromDateTime($dt);
        self::assertSame('(D:20260411123000Z)', $s->toPdf());
    }

    public function testFromDateTimeWithOffset(): void
    {
        $dt = new \DateTimeImmutable('2026-04-11T08:15:30', new \DateTimeZone('-05:30'));
        $s = PdfDate::fromDateTime($dt);
        self::assertStringContainsString("-05'30", $s->toPdf());
    }

    public function testParseRoundTrip(): void
    {
        $input = 'D:20260411123000Z';
        $parsed = PdfDate::parse($input);
        self::assertNotNull($parsed);
        self::assertSame('2026-04-11 12:30:00', $parsed->format('Y-m-d H:i:s'));
    }

    public function testParseWithOffset(): void
    {
        $parsed = PdfDate::parse("D:20260411081530-05'30");
        self::assertNotNull($parsed);
        self::assertSame('2026-04-11 08:15:30', $parsed->format('Y-m-d H:i:s'));
        self::assertSame('-05:30', $parsed->format('P'));
    }

    public function testParseReturnsNullForGarbage(): void
    {
        self::assertNull(PdfDate::parse('not a date'));
    }
}
