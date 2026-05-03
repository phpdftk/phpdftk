<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Bookmark\BookmarkEntry;
use Phpdftk\Pdf\Toolkit\BookmarkEditor;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class BookmarkEditorTest extends TestCase
{
    use QpdfValidationTrait;
    private function generateMultiPagePdf(int $pages = 5): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i")
                ->endText();
        }

        return $writer->generate();
    }

    public function testSetBookmarks(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = BookmarkEditor::openString($pdf)
            ->setBookmarks(
                new BookmarkEntry('Chapter 1', 1),
                new BookmarkEntry('Chapter 2', 3),
                new BookmarkEntry('Chapter 3', 5),
            )
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);

        // Round-trip: verify bookmarks can be read back
        $editor = BookmarkEditor::openString($result);
        $this->assertTrue($editor->hasBookmarks());

        $bookmarks = $editor->getBookmarks();
        $this->assertCount(3, $bookmarks);
        $this->assertSame('Chapter 1', $bookmarks[0]->title);
        $this->assertSame(1, $bookmarks[0]->pageNumber);
        $this->assertSame('Chapter 2', $bookmarks[1]->title);
        $this->assertSame(3, $bookmarks[1]->pageNumber);
        $this->assertSame('Chapter 3', $bookmarks[2]->title);
        $this->assertSame(5, $bookmarks[2]->pageNumber);
    }

    public function testSetBookmarksWithChildren(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = BookmarkEditor::openString($pdf)
            ->setBookmarks(
                new BookmarkEntry('Part 1', 1, [
                    new BookmarkEntry('Section 1.1', 1),
                    new BookmarkEntry('Section 1.2', 2),
                ]),
                new BookmarkEntry('Part 2', 3, [
                    new BookmarkEntry('Section 2.1', 3),
                    new BookmarkEntry('Section 2.2', 4),
                ]),
            )
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $editor = BookmarkEditor::openString($result);
        $bookmarks = $editor->getBookmarks();

        $this->assertCount(2, $bookmarks);
        $this->assertSame('Part 1', $bookmarks[0]->title);
        $this->assertCount(2, $bookmarks[0]->children);
        $this->assertSame('Section 1.1', $bookmarks[0]->children[0]->title);
        $this->assertSame(1, $bookmarks[0]->children[0]->pageNumber);
        $this->assertSame('Section 1.2', $bookmarks[0]->children[1]->title);
        $this->assertSame(2, $bookmarks[0]->children[1]->pageNumber);

        $this->assertSame('Part 2', $bookmarks[1]->title);
        $this->assertCount(2, $bookmarks[1]->children);
        $this->assertSame('Section 2.1', $bookmarks[1]->children[0]->title);
        $this->assertSame('Section 2.2', $bookmarks[1]->children[1]->title);
    }

    public function testAddBookmark(): void
    {
        $pdf = $this->generateMultiPagePdf();

        // First, set some bookmarks
        $withBookmarks = BookmarkEditor::openString($pdf)
            ->setBookmarks(new BookmarkEntry('Existing', 1))
            ->toBytes();

        $this->assertQpdfValidBytes($withBookmarks);

        // Then append one
        $result = BookmarkEditor::openString($withBookmarks)
            ->addBookmark('Appended', 3)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $bookmarks = BookmarkEditor::openString($result)->getBookmarks();
        $this->assertCount(2, $bookmarks);
        $this->assertSame('Existing', $bookmarks[0]->title);
        $this->assertSame('Appended', $bookmarks[1]->title);
        $this->assertSame(3, $bookmarks[1]->pageNumber);
    }

    public function testHasBookmarksReturnsFalseForCleanPdf(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $editor = BookmarkEditor::openString($pdf);
        $this->assertFalse($editor->hasBookmarks());
        $this->assertSame([], $editor->getBookmarks());
    }

    public function testRemoveBookmarks(): void
    {
        $pdf = $this->generateMultiPagePdf();

        // Add bookmarks
        $withBookmarks = BookmarkEditor::openString($pdf)
            ->setBookmarks(
                new BookmarkEntry('To be removed', 1),
            )
            ->toBytes();

        // Verify they exist
        $this->assertTrue(BookmarkEditor::openString($withBookmarks)->hasBookmarks());

        // Remove them
        $result = BookmarkEditor::openString($withBookmarks)
            ->removeBookmarks()
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $this->assertFalse(BookmarkEditor::openString($result)->hasBookmarks());
    }

    public function testGetPageCount(): void
    {
        $pdf = $this->generateMultiPagePdf(7);
        $editor = BookmarkEditor::openString($pdf);
        $this->assertSame(7, $editor->getPageCount());
    }

    public function testGetReader(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $editor = BookmarkEditor::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $editor->getReader());
    }

    public function testSaveToFile(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $outputPath = sys_get_temp_dir() . '/phpdftk_bookmark_test_' . uniqid() . '.pdf';

        try {
            BookmarkEditor::openString($pdf)
                ->setBookmarks(new BookmarkEntry('Saved', 1))
                ->save($outputPath);

            $this->assertFileExists($outputPath);
            $this->assertStringStartsWith('%PDF', file_get_contents($outputPath));
            $this->assertQpdfValid($outputPath);

            // Verify round-trip from file
            $editor = BookmarkEditor::open($outputPath);
            $bookmarks = $editor->getBookmarks();
            $this->assertCount(1, $bookmarks);
            $this->assertSame('Saved', $bookmarks[0]->title);
        } finally {
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function testNoBytesChangedWhenNoOperations(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $editor = BookmarkEditor::openString($pdf);
        $this->assertSame($pdf, $editor->toBytes());
    }

    public function testReplaceExistingBookmarks(): void
    {
        $pdf = $this->generateMultiPagePdf();

        // Set initial bookmarks
        $v1 = BookmarkEditor::openString($pdf)
            ->setBookmarks(
                new BookmarkEntry('Old A', 1),
                new BookmarkEntry('Old B', 2),
            )
            ->toBytes();

        // Replace with new bookmarks
        $v2 = BookmarkEditor::openString($v1)
            ->setBookmarks(
                new BookmarkEntry('New X', 3),
            )
            ->toBytes();

        $this->assertQpdfValidBytes($v2);
        $bookmarks = BookmarkEditor::openString($v2)->getBookmarks();
        $this->assertCount(1, $bookmarks);
        $this->assertSame('New X', $bookmarks[0]->title);
        $this->assertSame(3, $bookmarks[0]->pageNumber);
    }
}
