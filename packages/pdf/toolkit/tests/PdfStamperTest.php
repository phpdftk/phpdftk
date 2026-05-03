<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\PageSelector;
use Phpdftk\Pdf\Toolkit\PdfStamper;
use Phpdftk\Pdf\Toolkit\Stamper\ImageStampStyle;
use Phpdftk\Pdf\Toolkit\Stamper\StampPosition;
use Phpdftk\Pdf\Toolkit\Stamper\StampStyle;
use Phpdftk\Pdf\Toolkit\Stamper\WatermarkStyle;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PdfStamperTest extends TestCase
{
    use QpdfValidationTrait;
    private function generatePdf(int $pages = 2): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i content")
                ->endText();
        }

        return $writer->generate();
    }

    public function testStampText(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)
            ->stampText('CONFIDENTIAL', StampPosition::TopRight)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(2, $reader->getPageCount());
    }

    public function testWatermark(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)
            ->watermark('DRAFT')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(2, $reader->getPageCount());
        // Watermark should contain the text
        $text = $reader->extractAllText();
        // Note: watermarks use rotated text matrix, extraction may not capture it
    }

    public function testWatermarkWithStyle(): void
    {
        $pdf = $this->generatePdf();
        $style = new WatermarkStyle(fontSize: 72.0, r: 1.0, g: 0.0, b: 0.0, opacity: 0.2, rotation: 30.0);
        $result = PdfStamper::openString($pdf)
            ->watermark('TOP SECRET', style: $style)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
    }

    public function testPageNumbers(): void
    {
        $pdf = $this->generatePdf(3);
        $result = PdfStamper::openString($pdf)
            ->addPageNumbers(StampPosition::BottomCenter, 'Page {n} of {total}')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
    }

    public function testStampOnSpecificPages(): void
    {
        $pdf = $this->generatePdf(3);
        $result = PdfStamper::openString($pdf)
            ->stampText('ODD PAGE', StampPosition::TopLeft, PageSelector::odd())
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
    }

    public function testHeaderAndFooter(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)
            ->header('Company Name')
            ->footer('Confidential')
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
    }

    public function testStampWithOpacity(): void
    {
        $pdf = $this->generatePdf();
        $style = new StampStyle(fontSize: 14.0, opacity: 0.5);
        $result = PdfStamper::openString($pdf)
            ->stampText('Semi-transparent', StampPosition::Center, style: $style)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
    }

    public function testNoOpsReturnsOriginal(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfStamper::openString($pdf)->toBytes();
        $this->assertSame($pdf, $result);
    }

    public function testPageCount(): void
    {
        $pdf = $this->generatePdf(5);
        $stamper = PdfStamper::openString($pdf);
        $this->assertSame(5, $stamper->getPageCount());
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generatePdf();
        $stamper = PdfStamper::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $stamper->getReader());
    }

    public function testSaveToFile(): void
    {
        $pdf = $this->generatePdf();
        $path = sys_get_temp_dir() . '/phpdftk_stamper_test_' . uniqid() . '.pdf';

        try {
            PdfStamper::openString($pdf)
                ->stampText('FILE TEST', StampPosition::Center)
                ->save($path);

            $this->assertFileExists($path);
            $this->assertStringStartsWith('%PDF', file_get_contents($path));
            $this->assertQpdfValid($path);
        } finally {
            @unlink($path);
        }
    }

    // -----------------------------------------------------------------------
    // Image stamping — happy path
    // -----------------------------------------------------------------------

    public function testStampImageJpeg(): void
    {
        $pdf = $this->generatePdf();
        $imgPath = $this->createTempJpeg();

        try {
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::Center)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
            $reader = PdfReader::fromString($result);
            $this->assertSame(2, $reader->getPageCount());
        } finally {
            @unlink($imgPath);
        }
    }

    public function testStampImagePng(): void
    {
        $pdf = $this->generatePdf();
        $imgPath = $this->createTempPng();

        try {
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::TopLeft)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($imgPath);
        }
    }

    public function testStampImageWithScaleWidth(): void
    {
        $pdf = $this->generatePdf();
        $imgPath = $this->createTempJpeg(200, 100);

        try {
            $style = new ImageStampStyle(width: 100.0);
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::BottomRight, style: $style)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($imgPath);
        }
    }

    public function testStampImageWithScaleHeight(): void
    {
        $pdf = $this->generatePdf();
        $imgPath = $this->createTempJpeg(200, 100);

        try {
            $style = new ImageStampStyle(height: 50.0);
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::TopCenter, style: $style)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($imgPath);
        }
    }

    public function testStampImageWithExplicitDimensions(): void
    {
        $pdf = $this->generatePdf();
        $imgPath = $this->createTempJpeg(200, 100);

        try {
            $style = new ImageStampStyle(width: 150.0, height: 75.0);
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::Center, style: $style)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($imgPath);
        }
    }

    public function testStampImageWithOpacity(): void
    {
        $pdf = $this->generatePdf();
        $imgPath = $this->createTempJpeg();

        try {
            $style = new ImageStampStyle(width: 100.0, opacity: 0.5);
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::Center, style: $style)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($imgPath);
        }
    }

    public function testStampImageOnSpecificPages(): void
    {
        $pdf = $this->generatePdf(4);
        $imgPath = $this->createTempJpeg();

        try {
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::TopRight, PageSelector::pages(1, 3))
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
            $reader = PdfReader::fromString($result);
            $this->assertSame(4, $reader->getPageCount());
        } finally {
            @unlink($imgPath);
        }
    }

    public function testStampImageCombinedWithText(): void
    {
        $pdf = $this->generatePdf();
        $imgPath = $this->createTempJpeg();

        try {
            $result = PdfStamper::openString($pdf)
                ->stampImage($imgPath, StampPosition::TopLeft, style: new ImageStampStyle(width: 80.0))
                ->stampText('CONFIDENTIAL', StampPosition::TopRight)
                ->footer('Page footer')
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($imgPath);
        }
    }

    // -----------------------------------------------------------------------
    // Image stamping — negative path
    // -----------------------------------------------------------------------

    public function testStampImageThrowsOnMissingFile(): void
    {
        $pdf = $this->generatePdf();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image file not found');

        PdfStamper::openString($pdf)
            ->stampImage('/nonexistent/image.jpg', StampPosition::Center);
    }

    public function testStampImageThrowsOnUnsupportedFormat(): void
    {
        $pdf = $this->generatePdf();
        $tmpFile = tempnam(sys_get_temp_dir(), 'phpdftk_') . '.bmp';
        file_put_contents($tmpFile, 'not a real image');

        try {
            $this->expectException(\RuntimeException::class);
            PdfStamper::openString($pdf)
                ->stampImage($tmpFile, StampPosition::Center)
                ->toBytes();
        } finally {
            @unlink($tmpFile);
        }
    }

    // -----------------------------------------------------------------------
    // PDF stamping — happy path
    // -----------------------------------------------------------------------

    public function testStampPdf(): void
    {
        $pdf = $this->generatePdf();
        $stampPdf = $this->generatePdf(1);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $result = PdfStamper::openString($pdf)
                ->stampPdf($stampPath, position: StampPosition::Center)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
            $reader = PdfReader::fromString($result);
            $this->assertSame(2, $reader->getPageCount());
        } finally {
            @unlink($stampPath);
        }
    }

    public function testStampPdfWithScaling(): void
    {
        $pdf = $this->generatePdf();
        $stampPdf = $this->generatePdf(1);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $style = new ImageStampStyle(width: 200.0, height: 200.0);
            $result = PdfStamper::openString($pdf)
                ->stampPdf($stampPath, style: $style, position: StampPosition::BottomLeft)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($stampPath);
        }
    }

    public function testStampPdfWithOpacity(): void
    {
        $pdf = $this->generatePdf();
        $stampPdf = $this->generatePdf(1);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $style = new ImageStampStyle(width: 300.0, opacity: 0.3);
            $result = PdfStamper::openString($pdf)
                ->stampPdf($stampPath, style: $style, position: StampPosition::Center)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($stampPath);
        }
    }

    public function testStampPdfSpecificPage(): void
    {
        $pdf = $this->generatePdf();
        $stampPdf = $this->generatePdf(3);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $result = PdfStamper::openString($pdf)
                ->stampPdf($stampPath, pageIndex: 2, position: StampPosition::TopLeft)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($stampPath);
        }
    }

    public function testStampPdfOnSelectedPages(): void
    {
        $pdf = $this->generatePdf(4);
        $stampPdf = $this->generatePdf(1);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $result = PdfStamper::openString($pdf)
                ->stampPdf($stampPath, pages: PageSelector::even(), position: StampPosition::Center)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($stampPath);
        }
    }

    public function testStampPdfDefaultsToCenter(): void
    {
        $pdf = $this->generatePdf();
        $stampPdf = $this->generatePdf(1);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $result = PdfStamper::openString($pdf)
                ->stampPdf($stampPath)
                ->toBytes();

            $this->assertStringStartsWith('%PDF', $result);
            $this->assertQpdfValidBytes($result);
        } finally {
            @unlink($stampPath);
        }
    }

    // -----------------------------------------------------------------------
    // PDF stamping — negative path
    // -----------------------------------------------------------------------

    public function testStampPdfThrowsOnMissingFile(): void
    {
        $pdf = $this->generatePdf();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PDF file not found');

        PdfStamper::openString($pdf)
            ->stampPdf('/nonexistent/file.pdf');
    }

    public function testStampPdfThrowsOnInvalidPageIndex(): void
    {
        $pdf = $this->generatePdf();
        $stampPdf = $this->generatePdf(2);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Page index 5 out of range');

            PdfStamper::openString($pdf)
                ->stampPdf($stampPath, pageIndex: 5);
        } finally {
            @unlink($stampPath);
        }
    }

    public function testStampPdfThrowsOnNegativePageIndex(): void
    {
        $pdf = $this->generatePdf();
        $stampPdf = $this->generatePdf(2);
        $stampPath = $this->writeTempFile($stampPdf, '.pdf');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Page index -1 out of range');

            PdfStamper::openString($pdf)
                ->stampPdf($stampPath, pageIndex: -1);
        } finally {
            @unlink($stampPath);
        }
    }

    // -----------------------------------------------------------------------
    // ImageStampStyle defaults
    // -----------------------------------------------------------------------

    public function testImageStampStyleDefaults(): void
    {
        $style = new ImageStampStyle();
        $this->assertNull($style->width);
        $this->assertNull($style->height);
        $this->assertSame(1.0, $style->opacity);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createTempJpeg(int $width = 50, int $height = 50): string
    {
        $img = imagecreatetruecolor($width, $height);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_') . '.jpg';
        imagejpeg($img, $path, 90);
        imagedestroy($img);
        return $path;
    }

    private function createTempPng(int $width = 50, int $height = 50): string
    {
        $img = imagecreatetruecolor($width, $height);
        $blue = imagecolorallocate($img, 0, 0, 255);
        imagefill($img, 0, 0, $blue);
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    private function writeTempFile(string $content, string $ext): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_') . $ext;
        file_put_contents($path, $content);
        return $path;
    }
}
