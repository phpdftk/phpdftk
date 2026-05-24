<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\Theme;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PageNumbersTest extends TestCase
{
    use QpdfValidationTrait;

    public function testShowPageNumbersDefaultFormat(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->showPageNumbers();
        $pdf->addPage();
        $pdf->addPage();
        $pdf->addPage();

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Page 1 of 3', $bytes);
        self::assertStringContainsString('Page 2 of 3', $bytes);
        self::assertStringContainsString('Page 3 of 3', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testCustomFormatUsesSprintfPlaceholders(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->showPageNumbers('— %d / %d —');
        $pdf->addPage();
        $pdf->addPage();

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('1 / 2', $bytes);
        self::assertStringContainsString('2 / 2', $bytes);
    }

    public function testShowPageNumbersCalledTwiceKeepsLastFormat(): void
    {
        // showPageNumbers wraps setFooter, which overwrites on each call.
        $pdf = new Pdf(compressStreams: false);
        $pdf->showPageNumbers('First %d/%d');
        $pdf->showPageNumbers('Second %d/%d');
        $pdf->addPage();

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Second 1/1', $bytes);
        self::assertStringNotContainsString('First 1/1', $bytes);
    }

    public function testShowPageNumbersWorksWithZeroFooterHeight(): void
    {
        // No footerHeight reserve set; page number renders inside the bottom margin.
        $pdf = new Pdf(compressStreams: false);
        $pdf->showPageNumbers();
        $pdf->addPage();
        $pdf->addText('Body text');

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Page 1 of 1', $bytes);
        self::assertStringContainsString('Body text', $bytes);
    }

    public function testShowPageNumbersRespectsExplicitFooterHeight(): void
    {
        $theme = new Theme(footerHeight: 30.0);
        $pdf = new Pdf(theme: $theme, compressStreams: false);
        $pdf->showPageNumbers();
        $pdf->addPage();

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Page 1 of 1', $bytes);
    }

    public function testShowPageNumbersAcceptsCustomAlignment(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->showPageNumbers(align: Alignment::Right);
        $pdf->addPage();

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Page 1 of 1', $bytes);
        // Right-aligned footer: x position should be near the right margin.
        // Letter is 612 wide, 72 margin, footer text approx 60pt wide → x ≈ 480.
        self::assertMatchesRegularExpression(
            '/4\d\d(?:\.\d+)? \d+(?:\.\d+)? Td\s+\(Page 1 of 1\)/',
            $bytes,
        );
    }
}
