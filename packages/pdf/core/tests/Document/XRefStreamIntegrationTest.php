<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\CrossReferenceStream;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\ObjectStream;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Assembles a hand-rolled PDF 1.5 file whose trailer is carried by a
 * CrossReferenceStream (/Type /XRef) and whose Info dict is packed into
 * an ObjectStream (/Type /ObjStm).
 *
 * This intentionally bypasses PdfWriter (which currently emits a classic
 * xref table + trailer) to validate the new spec objects end-to-end.
 */
#[Group("qpdf")]
class XRefStreamIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/xref_stream.pdf';

    public function testGeneratesPdfWithXrefAndObjectStreams(): void
    {
        // ----- Build the bare object graph ------------------------------
        $catalog = new Catalog();
        $catalog->objectNumber = 1;

        $pageTree = new PageTree();
        $pageTree->objectNumber = 2;

        $page = new Page();
        $page->objectNumber = 3;
        $page->parent = new PdfReference($pageTree->objectNumber);
        $page->mediaBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
        $page->resources = new \Phpdftk\Pdf\Core\Content\Resources();

        $info = new Info();
        $info->objectNumber = 4;
        $info->title = new PdfString('XRef Stream Demo');

        $catalog->pages = new PdfReference($pageTree->objectNumber);
        $pageTree->kids = [new PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        // ----- Pack Info into an ObjectStream ---------------------------
        $objStm = new ObjectStream();
        $objStm->objectNumber = 5;
        $objStm->addObject($info);

        // ----- Assemble the file body -----------------------------------
        $chunks = [];
        $chunks[] = "%PDF-1.5\n";
        $chunks[] = "%\xE2\xE3\xCF\xD3\n";
        $offset = strlen($chunks[0]) + strlen($chunks[1]);

        $offsets = [];
        foreach ([$catalog, $pageTree, $page, $objStm] as $obj) {
            $offsets[$obj->objectNumber] = $offset;
            $chunk = $obj->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        // ----- Cross-reference stream -----------------------------------
        $xref = new CrossReferenceStream();
        $xref->objectNumber = 6;
        $xref->size = 7; // obj 0..6
        $xref->root = new PdfReference($catalog->objectNumber);
        // /Info entry references object 4 — that object lives at index 0 in
        // object stream 5 (type-2 entry).
        $xref->info = new PdfReference($info->objectNumber);

        $xref->addFreeEntry(0, 65535);                // obj 0
        $xref->addInUseEntry($offsets[1]);            // obj 1: Catalog
        $xref->addInUseEntry($offsets[2]);            // obj 2: PageTree
        $xref->addInUseEntry($offsets[3]);            // obj 3: Page
        $xref->addCompressedEntry($objStm->objectNumber, 0); // obj 4: Info (inside ObjStm 5, index 0)
        $xref->addInUseEntry($offsets[5]);            // obj 5: ObjStm
        // obj 6 (the xref stream itself) gets its offset filled in below
        $xrefPlaceholderIndex = null;

        $xrefOffset = $offset;
        $xref->addInUseEntry($xrefOffset);            // obj 6: the xref stream

        $chunks[] = $xref->toIndirectObject() . "\n";

        $chunks[] = "startxref\n" . $xrefOffset . "\n";
        $chunks[] = '%%EOF';

        $pdf = implode('', $chunks);
        $dir = dirname(self::OUTPUT_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(self::OUTPUT_FILE, $pdf);

        // ----- Assertions -----------------------------------------------
        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $contents = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($contents);
        self::assertStringStartsWith('%PDF-1.5', $contents);
        self::assertStringContainsString('/Type /XRef', $contents);
        self::assertStringContainsString('/Type /ObjStm', $contents);
        self::assertMatchesRegularExpression('/\/W \[ 1 \d+ \d+ \]/', $contents);
        self::assertStringContainsString('/Size 7', $contents);
        self::assertStringContainsString('startxref', $contents);
        self::assertStringContainsString('%%EOF', $contents);
    }
}