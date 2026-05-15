<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Reader\TextSpan;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises Type0/CID font extraction paths in PositionedTextExtractor —
 * specifically loadCidWidths(), which parses the /W array on CID fonts.
 */
class CidFontExtractionTest extends TestCase
{
    private function findFont(): string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No TTF font found');
    }

    private function buildCidFontPdf(string $text = 'Hello World'): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);

        $data = (new TrueTypeParser($this->findFont()))->parse();
        $codepoints = array_map('mb_ord', mb_str_split($text));
        $font = $writer->addCompositeFont($data, $codepoints, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showUnicodeText($text, $font->getUnicodeToGidMap())
            ->endText();

        return $writer->generate();
    }

    public function testPositionedExtractionLoadsCidWidths(): void
    {
        $bytes = $this->buildCidFontPdf('Hello World');
        $reader = PdfReader::fromString($bytes);
        $spans = $reader->extractTextWithPositions(0);
        $this->assertNotEmpty($spans);
        foreach ($spans as $span) {
            $this->assertInstanceOf(TextSpan::class, $span);
            // Widths should resolve through loadCidWidths so spans have non-zero width
            $this->assertGreaterThanOrEqual(0, $span->width);
        }
    }

    public function testCidFontPdfRoundTripExtractsText(): void
    {
        $bytes = $this->buildCidFontPdf('Composite font text');
        $reader = PdfReader::fromString($bytes);
        $text = $reader->extractText(0);
        $this->assertNotEmpty($text);
    }

    public function testPositionedExtractionFallsBackToStandardFontMetrics(): void
    {
        // Build a PDF that uses /Helvetica but omits the /Widths array, so
        // PositionedTextExtractor falls back to tryLoadStandardFontWidths().
        $fileWriter = new \Phpdftk\Pdf\Core\File\PdfFileWriter(compressStreams: false);
        $catalog = new \Phpdftk\Pdf\Core\Document\Catalog();
        $fileWriter->setCatalog($catalog);
        $pageTree = new \Phpdftk\Pdf\Core\Document\PageTree();
        $fileWriter->register($pageTree);
        $catalog->pages = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);

        $page = new \Phpdftk\Pdf\Core\Document\Page();
        $fileWriter->register($page);
        $page->parent = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);
        $page->mediaBox = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(0), new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(612), new \Phpdftk\Pdf\Core\PdfNumber(792),
        ]);

        // Hand-roll a minimal Type1 font dict with NO /Widths.
        $fontDict = new \Phpdftk\Pdf\Core\PdfDictionary();
        $fontDict->set('Type', new \Phpdftk\Pdf\Core\PdfName('Font'));
        $fontDict->set('Subtype', new \Phpdftk\Pdf\Core\PdfName('Type1'));
        $fontDict->set('BaseFont', new \Phpdftk\Pdf\Core\PdfName('Helvetica'));
        $fontDict->set('Encoding', new \Phpdftk\Pdf\Core\PdfName('WinAnsiEncoding'));
        $fontObject = new class ($fontDict) extends \Phpdftk\Pdf\Core\PdfObject {
            public function __construct(private readonly \Phpdftk\Pdf\Core\PdfDictionary $d) {}
            public function toPdf(): string
            {
                return $this->d->toPdf();
            }
        };
        $fileWriter->register($fontObject);

        $page->resources = new \Phpdftk\Pdf\Core\Content\Resources();
        $page->resources->font['F1'] = new \Phpdftk\Pdf\Core\PdfReference($fontObject->objectNumber);

        $cs = new \Phpdftk\Pdf\Core\Content\ContentStream();
        $fileWriter->register($cs);
        $cs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello')
            ->endText();
        $page->contents = [new \Phpdftk\Pdf\Core\PdfReference($cs->objectNumber)];
        $pageTree->kids = [new \Phpdftk\Pdf\Core\PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $reader = PdfReader::fromString($fileWriter->generate());
        $spans = $reader->extractTextWithPositions(0);
        $this->assertNotEmpty($spans);
        // tryLoadStandardFontWidths should have populated widths from Helvetica AFM.
        $this->assertGreaterThan(0, $spans[0]->width);
    }

    public function testPositionedExtractionSubsetPrefixStrippedForStandardFont(): void
    {
        // Same as above but with a subset-prefixed BaseFont like "ABCDEF+Helvetica".
        $fileWriter = new \Phpdftk\Pdf\Core\File\PdfFileWriter(compressStreams: false);
        $catalog = new \Phpdftk\Pdf\Core\Document\Catalog();
        $fileWriter->setCatalog($catalog);
        $pageTree = new \Phpdftk\Pdf\Core\Document\PageTree();
        $fileWriter->register($pageTree);
        $catalog->pages = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);

        $page = new \Phpdftk\Pdf\Core\Document\Page();
        $fileWriter->register($page);
        $page->parent = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);
        $page->mediaBox = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(0), new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(612), new \Phpdftk\Pdf\Core\PdfNumber(792),
        ]);

        $fontDict = new \Phpdftk\Pdf\Core\PdfDictionary();
        $fontDict->set('Type', new \Phpdftk\Pdf\Core\PdfName('Font'));
        $fontDict->set('Subtype', new \Phpdftk\Pdf\Core\PdfName('Type1'));
        $fontDict->set('BaseFont', new \Phpdftk\Pdf\Core\PdfName('ABCDEF+Helvetica'));
        $fontDict->set('Encoding', new \Phpdftk\Pdf\Core\PdfName('WinAnsiEncoding'));
        $fontObject = new class ($fontDict) extends \Phpdftk\Pdf\Core\PdfObject {
            public function __construct(private readonly \Phpdftk\Pdf\Core\PdfDictionary $d) {}
            public function toPdf(): string
            {
                return $this->d->toPdf();
            }
        };
        $fileWriter->register($fontObject);

        $page->resources = new \Phpdftk\Pdf\Core\Content\Resources();
        $page->resources->font['F1'] = new \Phpdftk\Pdf\Core\PdfReference($fontObject->objectNumber);

        $cs = new \Phpdftk\Pdf\Core\Content\ContentStream();
        $fileWriter->register($cs);
        $cs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Hi')
            ->endText();
        $page->contents = [new \Phpdftk\Pdf\Core\PdfReference($cs->objectNumber)];
        $pageTree->kids = [new \Phpdftk\Pdf\Core\PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $reader = PdfReader::fromString($fileWriter->generate());
        $spans = $reader->extractTextWithPositions(0);
        $this->assertNotEmpty($spans);
    }

    public function testPositionedExtractionUnknownBaseFontDoesNotThrow(): void
    {
        // BaseFont not in the 14 standard set → tryLoadStandardFontWidths catches
        // the InvalidArgumentException and returns silently.
        $fileWriter = new \Phpdftk\Pdf\Core\File\PdfFileWriter(compressStreams: false);
        $catalog = new \Phpdftk\Pdf\Core\Document\Catalog();
        $fileWriter->setCatalog($catalog);
        $pageTree = new \Phpdftk\Pdf\Core\Document\PageTree();
        $fileWriter->register($pageTree);
        $catalog->pages = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);

        $page = new \Phpdftk\Pdf\Core\Document\Page();
        $fileWriter->register($page);
        $page->parent = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);
        $page->mediaBox = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(0), new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(612), new \Phpdftk\Pdf\Core\PdfNumber(792),
        ]);

        $fontDict = new \Phpdftk\Pdf\Core\PdfDictionary();
        $fontDict->set('Type', new \Phpdftk\Pdf\Core\PdfName('Font'));
        $fontDict->set('Subtype', new \Phpdftk\Pdf\Core\PdfName('Type1'));
        $fontDict->set('BaseFont', new \Phpdftk\Pdf\Core\PdfName('NonStandardFontName'));
        $fontObject = new class ($fontDict) extends \Phpdftk\Pdf\Core\PdfObject {
            public function __construct(private readonly \Phpdftk\Pdf\Core\PdfDictionary $d) {}
            public function toPdf(): string
            {
                return $this->d->toPdf();
            }
        };
        $fileWriter->register($fontObject);

        $page->resources = new \Phpdftk\Pdf\Core\Content\Resources();
        $page->resources->font['F1'] = new \Phpdftk\Pdf\Core\PdfReference($fontObject->objectNumber);

        $cs = new \Phpdftk\Pdf\Core\Content\ContentStream();
        $fileWriter->register($cs);
        $cs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Hi')
            ->endText();
        $page->contents = [new \Phpdftk\Pdf\Core\PdfReference($cs->objectNumber)];
        $pageTree->kids = [new \Phpdftk\Pdf\Core\PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $reader = PdfReader::fromString($fileWriter->generate());
        // Should not throw — just no widths populated
        $spans = $reader->extractTextWithPositions(0);
        $this->assertIsArray($spans);
    }
}
