<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PageContext;
use Phpdftk\Pdf\Writer\Theme;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PageDecoratorTest extends TestCase
{
    use QpdfValidationTrait;

    public function testHeaderClosureRunsOncePerPage(): void
    {
        $pdf = new Pdf(theme: new Theme(headerHeight: 36.0), compressStreams: false);
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));
        $invocations = [];
        $pdf->setHeader(function (PageContext $ctx) use (&$invocations, $font): void {
            $invocations[] = $ctx->pageNumber;
            $ctx->page->drawText(
                "Header pg {$ctx->pageNumber}/{$ctx->totalPages}",
                72.0,
                $ctx->pageHeight - 24.0,
                $font,
                10.0,
            );
        });
        $pdf->addPage();
        $pdf->addPage();
        $pdf->addPage();

        $bytes = $pdf->toBytes();

        self::assertSame([1, 2, 3], $invocations, 'Header should fire once per page in order');
        self::assertStringContainsString('Header pg 1/3', $bytes);
        self::assertStringContainsString('Header pg 3/3', $bytes);
    }

    public function testFooterClosureReceivesTotalPages(): void
    {
        $pdf = new Pdf(theme: new Theme(footerHeight: 36.0), compressStreams: false);
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));
        $totalsSeen = [];
        $pdf->setFooter(function (PageContext $ctx) use (&$totalsSeen, $font): void {
            $totalsSeen[] = $ctx->totalPages;
            $ctx->page->drawText(
                "Page {$ctx->pageNumber} of {$ctx->totalPages}",
                72.0,
                24.0,
                $font,
                10.0,
            );
        });
        $pdf->addPage();
        $pdf->addPage();

        $bytes = $pdf->toBytes();

        self::assertSame([2, 2], $totalsSeen, 'totalPages should be known and stable across pages');
        self::assertStringContainsString('Page 1 of 2', $bytes);
        self::assertStringContainsString('Page 2 of 2', $bytes);
    }

    public function testStringWatermarkRendersOnEveryPage(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setWatermark('DRAFT');
        $pdf->addPage();
        $pdf->addPage();

        $bytes = $pdf->toBytes();

        // Each watermark emits a graphics-state save/restore plus the
        // text 'DRAFT'. With two pages, the literal should appear twice.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, '(DRAFT)'));
    }

    public function testClosureWatermarkReceivesPageContext(): void
    {
        $pdf = new Pdf();
        $contexts = [];
        $pdf->setWatermark(function (PageContext $ctx) use (&$contexts): void {
            $contexts[] = [$ctx->pageNumber, $ctx->totalPages, $ctx->pageWidth, $ctx->pageHeight];
        });
        $pdf->addPage();
        $pdf->addPage();
        $pdf->toBytes();

        self::assertCount(2, $contexts);
        self::assertSame(1, $contexts[0][0]);
        self::assertSame(2, $contexts[0][1]);
        self::assertSame(612.0, $contexts[0][2]);
        self::assertSame(792.0, $contexts[0][3]);
    }

    public function testBodyRegionShrinksByHeaderAndFooterHeight(): void
    {
        $theme = new Theme(margin: 50.0, headerHeight: 30.0, footerHeight: 30.0);
        $pdf = new Pdf(theme: $theme, compressStreams: false);
        $pdf->addPage();
        $pdf->addText('Body text starts under the reserved header area.');

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Body text starts under the reserved header area.', $bytes);
        // One page only (literal "/Type /Page" appears once, never "/Type /Pages\n" plural).
        self::assertSame(1, substr_count($bytes, "/Type /Page\n"));
    }

    public function testDecoratorsAreIdempotentAcrossOutputCalls(): void
    {
        $pdf = new Pdf();
        $invocations = 0;
        $pdf->setFooter(function () use (&$invocations): void {
            $invocations++;
        });
        $pdf->addPage();
        $pdf->addPage();

        $pdf->toBytes();
        $pdf->toBytes();
        $pdf->toBytes();

        self::assertSame(2, $invocations, 'Decorator pass must run only once even if output is requested multiple times');
    }

    public function testDecoratorPassWithNoPagesIsSafe(): void
    {
        // No pages added — the pass should be a no-op without errors.
        $pdf = new Pdf();
        $pdf->setHeader(fn() => null);
        $pdf->setFooter(fn() => null);
        $pdf->setWatermark('DRAFT');

        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testDecoratorPassWithNoDecoratorsIsSkipped(): void
    {
        // No decorators registered: applyDecorators short-circuits.
        $pdf = new Pdf();
        $pdf->addPage();
        $pdf->addPage();

        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Two pages, no decorator content streams should appear.
        self::assertSame(2, substr_count($bytes, "/Type /Page\n"));
    }

    public function testReplacingHeaderClosureKeepsLastRegistration(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));
        $calls = [];

        $pdf->setHeader(function (PageContext $ctx) use (&$calls, $font): void {
            $calls[] = 'first';
            $ctx->page->drawText('FIRST', 72.0, 750.0, $font, 10.0);
        });
        // Second registration replaces the first.
        $pdf->setHeader(function (PageContext $ctx) use (&$calls, $font): void {
            $calls[] = 'second';
            $ctx->page->drawText('SECOND', 72.0, 750.0, $font, 10.0);
        });
        $pdf->addPage();
        $bytes = $pdf->toBytes();

        self::assertSame(['second'], $calls);
        self::assertStringContainsString('SECOND', $bytes);
        self::assertStringNotContainsString('FIRST', $bytes);
    }

    public function testClosureWatermarkAcrossDifferentPageSizes(): void
    {
        $pdf = new Pdf(\Phpdftk\Pdf\Writer\PageSize::Letter, compressStreams: false);
        $sizes = [];
        $pdf->setWatermark(function (PageContext $ctx) use (&$sizes): void {
            $sizes[] = [$ctx->pageWidth, $ctx->pageHeight];
        });
        $pdf->addPage(\Phpdftk\Pdf\Writer\PageSize::Letter);
        $pdf->addPage(\Phpdftk\Pdf\Writer\PageSize::A4);
        $pdf->addPage(\Phpdftk\Pdf\Writer\PageSize::Legal);

        $pdf->toBytes();

        self::assertCount(3, $sizes);
        self::assertSame([612.0, 792.0], $sizes[0]);   // Letter
        self::assertEqualsWithDelta(595.0, $sizes[1][0], 0.5); // A4 width
        self::assertEqualsWithDelta(842.0, $sizes[1][1], 0.5); // A4 height
        self::assertSame([612.0, 1008.0], $sizes[2]);  // Legal
    }

    public function testDefaultWatermarkUsesNonBlackFillColor(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->setWatermark('DRAFT', opacity: 0.5);
        $pdf->addPage();

        $bytes = $pdf->toBytes();
        // A 0.5-opacity grey approximation should not be solid black.
        // We look for a non-zero/non-one fill color (gray ~0.5) just before BT.
        self::assertMatchesRegularExpression(
            '/0\.5\d* 0\.5\d* 0\.5\d* rg/',
            $bytes,
            'Watermark fill should be a mid-grey approximation of opacity 0.5',
        );
    }

    public function testPagesAddedAfterFirstOutputDoNotRetroactivelyDecorate(): void
    {
        $pdf = new Pdf();
        $invocations = 0;
        $pdf->setFooter(function () use (&$invocations): void {
            $invocations++;
        });
        $pdf->addPage();
        $pdf->toBytes(); // First output — decorator fires once.

        $pdf->addPage(); // Page added AFTER decorator pass ran.
        $pdf->toBytes(); // Second output — decorator pass is short-circuited.

        // Documented behavior: decorators run exactly once per document
        // lifetime. A user who adds pages after output gets an undecorated
        // tail. This test pins that behavior so a future change can't
        // silently re-fire closures on the same pages.
        self::assertSame(1, $invocations);
    }
}
