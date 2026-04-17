<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Graphics\ExtGState;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Writer\PdfWriter;

class ExtGStateIntegrationTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/extgstate.pdf';

    public function testGeneratesExtGStatePdf(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        // Create an ExtGState with several properties including new ones
        $gs = new ExtGState();
        $gs->ca = 0.5;
        $gs->caLower = 0.7;
        $gs->bm = 'Multiply';
        $gs->useBlackPtComp = new PdfName('ON');
        $gsRef = $writer->register($gs);
        $page->resources->addExtGState('GS1', $gsRef);

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
        $pdfContent = file_get_contents(self::OUTPUT_FILE);
        self::assertStringStartsWith('%PDF', $pdfContent);
        self::assertStringContainsString('/ExtGState', $pdfContent);
        self::assertStringContainsString('/UseBlackPtComp', $pdfContent);
        self::assertStringContainsString('%%EOF', $pdfContent);
    }
}
