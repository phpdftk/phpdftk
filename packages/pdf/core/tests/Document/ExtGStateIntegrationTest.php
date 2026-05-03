<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\ExtGState;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;

#[Group("qpdf")]
class ExtGStateIntegrationTest extends TestCase
{
    use QpdfValidationTrait;
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/extgstate.pdf';

    public function testGeneratesExtGStatePdf(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        // Create an ExtGState with several properties including new ones
        $gs = new ExtGState();
        $gs->ca = 0.5;
        $gs->caLower = 0.7;
        $gs->bm = 'Multiply';
        $gs->useBlackPtComp = new PdfName('ON');
        $gsRef = $writer->register($gs);
        $page->corePage()->resources->addExtGState('GS1', $gsRef);

        // Draw content using the graphics state
        $content = $writer->addContentStream($page);
        $content->saveGraphicsState();
        $content->setGraphicsState('GS1');
        $content->beginText()
            ->setFont($fontName, 24)
            ->moveTextPosition(72, 700)
            ->showText('ExtGState with UseBlackPtComp')
            ->endText();
        $content->setFillColorRGB(0.2, 0.4, 0.8);
        $content->rectangle(72, 600, 200, 50);
        $content->fill();
        $content->restoreGraphicsState();

        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $pdfContent = file_get_contents(self::OUTPUT_FILE);
        self::assertStringStartsWith('%PDF', $pdfContent);
        self::assertStringContainsString('/ExtGState', $pdfContent);
        self::assertStringContainsString('/UseBlackPtComp', $pdfContent);
        self::assertStringContainsString('%%EOF', $pdfContent);
    }
}
