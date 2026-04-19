<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Document\Destination;
use ApprLabs\Pdf\Core\Document\Outline;
use ApprLabs\Pdf\Core\Document\OutlineItem;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Writer\PdfWriter;

/**
 * Generates a PDF with a nested bookmark tree (outlines) and verifies validity.
 */
class BookmarksTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/bookmarks.pdf';

    public function testGeneratesBookmarksPdf(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        $boldName = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

        // Create 6 pages: Cover, Ch1, Ch1.1, Ch1.2, Ch2, Appendix
        $pages = [];
        $titles = ['Cover Page', 'Chapter 1: Introduction', '1.1 Background', '1.2 Motivation', 'Chapter 2: Methods', 'Appendix A'];
        foreach ($titles as $i => $title) {
            $page = $writer->addPage(612, 792);
            $pages[] = $page;

            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($i === 0 ? $boldName : $fontName, $i === 0 ? 24 : 18)
               ->moveTextPosition(72, 720)
               ->showText($title)
               ->endText();

            $cs->beginText()
               ->setFont($fontName, 11)
               ->moveTextPosition(72, 680)
               ->showText(sprintf('This is page %d of %d.', $i + 1, count($titles)))
               ->moveTextPosition(0, -20)
               ->showText('Lorem ipsum dolor sit amet, consectetur adipiscing elit.')
               ->endText();
        }

        // Build outline tree:
        //   Cover
        //   Chapter 1
        //     1.1 Background
        //     1.2 Motivation
        //   Chapter 2
        //   Appendix A
        $outline = $writer->setOutline(new Outline());

        $cover = new OutlineItem('Cover Page');
        $cover->dest = Destination::fit(new PdfReference($pages[0]->corePage()->objectNumber));
        $coverRef = $writer->addOutlineItem($cover);
        $cover->parent = new PdfReference($outline->objectNumber);

        $ch1 = new OutlineItem('Chapter 1: Introduction');
        $ch1->dest = Destination::xyz(new PdfReference($pages[1]->corePage()->objectNumber), 72, 720, null);
        $ch1->c = new PdfArray([new PdfNumber(0.0), new PdfNumber(0.0), new PdfNumber(0.8)]); // blue
        $ch1->f = 2; // bold
        $ch1Ref = $writer->addOutlineItem($ch1);
        $ch1->parent = new PdfReference($outline->objectNumber);
        $ch1->prev = $coverRef;
        $cover->next = $ch1Ref;

        // Children of Chapter 1
        $s11 = new OutlineItem('1.1 Background');
        $s11->dest = Destination::fitH(new PdfReference($pages[2]->corePage()->objectNumber), 720);
        $s11Ref = $writer->addOutlineItem($s11);
        $s11->parent = $ch1Ref;

        $s12 = new OutlineItem('1.2 Motivation');
        $s12->dest = Destination::fitH(new PdfReference($pages[3]->corePage()->objectNumber), 720);
        $s12Ref = $writer->addOutlineItem($s12);
        $s12->parent = $ch1Ref;
        $s12->prev = $s11Ref;
        $s11->next = $s12Ref;

        $ch1->first = $s11Ref;
        $ch1->last = $s12Ref;
        $ch1->count = 2;

        $ch2 = new OutlineItem('Chapter 2: Methods');
        $ch2->dest = Destination::fit(new PdfReference($pages[4]->corePage()->objectNumber));
        $ch2->c = new PdfArray([new PdfNumber(0.0), new PdfNumber(0.0), new PdfNumber(0.8)]);
        $ch2->f = 2;
        $ch2Ref = $writer->addOutlineItem($ch2);
        $ch2->parent = new PdfReference($outline->objectNumber);
        $ch2->prev = $ch1Ref;
        $ch1->next = $ch2Ref;

        $appendix = new OutlineItem('Appendix A');
        $appendix->dest = Destination::fit(new PdfReference($pages[5]->corePage()->objectNumber));
        $appendix->f = 1; // italic
        $appendixRef = $writer->addOutlineItem($appendix);
        $appendix->parent = new PdfReference($outline->objectNumber);
        $appendix->prev = $ch2Ref;
        $ch2->next = $appendixRef;

        $outline->first = $coverRef;
        $outline->last = $appendixRef;
        $outline->count = 6; // 4 top-level + 2 children

        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('/Type /Outlines', $content);
        self::assertStringContainsString('/Title (Cover Page)', $content);
        self::assertStringContainsString('/Title (Chapter 1: Introduction)', $content);
        self::assertStringContainsString('/Title (1.1 Background)', $content);
        self::assertStringContainsString('/Title (1.2 Motivation)', $content);
        self::assertStringContainsString('/Title (Chapter 2: Methods)', $content);
        self::assertStringContainsString('/Title (Appendix A)', $content);
        self::assertStringContainsString('/Fit', $content);
        self::assertStringContainsString('/XYZ', $content);
        self::assertStringContainsString('/FitH', $content);
        self::assertStringContainsString('%%EOF', $content);
    }
}
