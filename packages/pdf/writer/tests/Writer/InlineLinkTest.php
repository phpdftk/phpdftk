<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class InlineLinkTest extends TestCase
{
    use QpdfValidationTrait;

    public function testAddTextWithLinkStyleProducesLinkAnnotation(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('Visit our site.', new TextStyle(link: 'https://example.com/'));

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Subtype /Link', $bytes);
        self::assertStringContainsString('/S /URI', $bytes);
        self::assertStringContainsString('example.com', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testLinkSpansEveryWrappedLine(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $longText = 'This is a longer paragraph designed to wrap across multiple lines '
            . 'inside the default content column, so the link should yield one annotation per line.';
        $pdf->addText($longText, new TextStyle(link: 'https://example.com/'));

        $bytes = $pdf->toBytes();
        // Each wrapped line gets its own Link annotation.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, '/Subtype /Link'));
    }

    public function testAddTextWithoutLinkStyleDoesNotEmitLinkAnnotation(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('Plain text, no link.');

        $bytes = $pdf->toBytes();
        self::assertStringNotContainsString('/Subtype /Link', $bytes);
    }

    public function testLinkAnnotationsLandOnTheCorrectPage(): void
    {
        // Force a page break in the middle of a linked paragraph.
        // Set tiny page size so wrapping crosses the page boundary.
        $pdf = new Pdf(compressStreams: false);
        $pdf->addPage(\Phpdftk\Pdf\Writer\PageSize::A5);

        // Fill most of the page first.
        for ($i = 0; $i < 30; $i++) {
            $pdf->addText('Filler line ' . $i);
        }
        // Now the linked paragraph should land near or below the page bottom.
        $pdf->addText(
            'Final linked paragraph at the bottom of one page that continues onto the next page.',
            new TextStyle(link: 'https://example.com/'),
        );

        $bytes = $pdf->toBytes();
        // Should have multiple pages and at least one link annotation
        // attached to each page's annots array.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "/Type /Page\n"));
        self::assertStringContainsString('/Subtype /Link', $bytes);
    }

    public function testEmptyLinkedTextDoesNotEmitAnnotation(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('', new TextStyle(link: 'https://example.com/'));

        $bytes = $pdf->toBytes();
        // An empty paragraph has no lines to link.
        self::assertStringNotContainsString('/Subtype /Link', $bytes);
    }

    public function testLinkStyleCanCombineWithOtherStyleFields(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText(
            'Bold blue link',
            new TextStyle(
                bold: true,
                color: [0.0, 0.0, 0.8],
                link: 'https://example.com/',
            ),
        );

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Subtype /Link', $bytes);
        // Bold variant used
        self::assertStringContainsString('Helvetica-Bold', $bytes);
        // Blue fill emitted (0 0 0.8 rg)
        self::assertMatchesRegularExpression('/0 0 0\.8 rg/', $bytes);
    }
}
