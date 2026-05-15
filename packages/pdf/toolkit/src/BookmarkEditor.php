<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Pdf\Core\Document\NumberTree;
use Phpdftk\Pdf\Core\Document\Outline;
use Phpdftk\Pdf\Core\Document\OutlineItem;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Bookmark\BookmarkEntry;

/**
 * Add, replace, read, or remove PDF bookmarks (outlines).
 *
 * Usage:
 *   BookmarkEditor::open('report.pdf')
 *       ->setBookmarks(
 *           new BookmarkEntry('Chapter 1', 1),
 *           new BookmarkEntry('Chapter 2', 5, [
 *               new BookmarkEntry('Section 2.1', 5),
 *               new BookmarkEntry('Section 2.2', 8),
 *           ]),
 *       )
 *       ->save('bookmarked.pdf');
 *
 * @api
 */
final class BookmarkEditor
{
    private string $originalBytes;

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    /** @var list<BookmarkEntry>|null Pending bookmark set (null = no change) */
    private ?array $pendingBookmarks = null;

    /** @var list<BookmarkEntry> Bookmarks to append */
    private array $appendBookmarks = [];

    private bool $removeAll = false;

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
    }

    public static function open(string $path, string $password = ''): self
    {
        $bytes = LocalFilesystem::readFile($path);
        return new self(PdfReader::fromString($bytes, $password), $bytes);
    }

    public static function openString(string $pdfBytes, string $password = ''): self
    {
        return new self(PdfReader::fromString($pdfBytes, $password), $pdfBytes);
    }

    // -----------------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------------

    /**
     * Read existing bookmarks from the PDF.
     *
     * @return list<BookmarkEntry>
     */
    public function getBookmarks(): array
    {
        $catalog = $this->reader->getCatalog();
        $outlinesRef = $catalog->get('Outlines');
        if (!$outlinesRef instanceof PdfReference) {
            return [];
        }

        $outlinesDict = $this->reader->resolveReference($outlinesRef);
        if (!$outlinesDict instanceof PdfDictionary) {
            return [];
        }

        $pageRefs = $this->collectPageReferences();
        return $this->readOutlineChildren($outlinesDict, $pageRefs);
    }

    public function hasBookmarks(): bool
    {
        return $this->getBookmarks() !== [];
    }

    // -----------------------------------------------------------------------
    // Write (fluent)
    // -----------------------------------------------------------------------

    /**
     * Replace all bookmarks with the given entries.
     */
    public function setBookmarks(BookmarkEntry ...$entries): self
    {
        $this->pendingBookmarks = array_values($entries);
        $this->removeAll = false;
        $this->appendBookmarks = [];
        return $this;
    }

    /**
     * Add a single bookmark (appended to existing bookmarks).
     */
    public function addBookmark(string $title, int $pageNumber): self
    {
        $this->appendBookmarks[] = new BookmarkEntry($title, $pageNumber);
        return $this;
    }

    /**
     * Remove all bookmarks from the document.
     */
    public function removeBookmarks(): self
    {
        $this->removeAll = true;
        $this->pendingBookmarks = null;
        $this->appendBookmarks = [];
        return $this;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        LocalFilesystem::writeFile($path, $this->toBytes(), createDirectories: true);
    }

    public function toBytes(): string
    {
        // Determine the effective bookmark list
        $entries = $this->resolveEffectiveBookmarks();
        if ($entries === null && !$this->removeAll) {
            return $this->originalBytes;
        }

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
        $pageRefs = $this->collectPageReferences();

        // Build the catalog modification
        $catalog = $this->reader->getCatalog();
        $catalogDict = $this->cloneDictionary($catalog);

        if ($this->removeAll && ($entries === null || $entries === [])) {
            // Remove /Outlines from catalog
            unset($catalogDict->entries['Outlines']);
        } else {
            // Build outline tree
            $outlineRoot = new Outline();
            $outlineRootRef = $writer->addNewObject($outlineRoot);

            /** @var list<BookmarkEntry> $entries */
            $this->buildOutlineTree($writer, $outlineRoot, $outlineRootRef, $entries ?? [], $pageRefs);

            $catalogDict->set('Outlines', $outlineRootRef);
        }

        // Wrap catalog dict in a PdfObject and register as modified
        $catalogObj = $this->wrapDictionary($catalogDict);
        $rootRef = $this->reader->getTrailer()->get('Root');
        if ($rootRef instanceof PdfReference) {
            $catalogObj->objectNumber = $rootRef->objectNumber;
            $catalogObj->generationNumber = 0;
        }
        $writer->addModifiedObject($catalogObj);

        $result = $writer->generate();
        $this->lastVersionWarnings = $writer->getVersionWarnings();
        return $result;
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->lastVersionWarnings;
    }

    public function getReader(): PdfReader
    {
        return $this->reader;
    }

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    // -----------------------------------------------------------------------
    // Internal — reading
    // -----------------------------------------------------------------------

    /**
     * Collect PdfReference objects for each page in order.
     *
     * @return list<PdfReference> 0-indexed; pageRefs[0] = page 1
     */
    private function collectPageReferences(): array
    {
        $catalog = $this->reader->getCatalog();
        $pagesRef = $catalog->get('Pages');
        if (!$pagesRef instanceof PdfReference) {
            return [];
        }
        $pagesDict = $this->reader->resolveReference($pagesRef);
        if (!$pagesDict instanceof PdfDictionary) {
            return [];
        }
        $refs = [];
        $this->collectPageRefs($pagesDict, $refs);
        return $refs;
    }

    /**
     * @param list<PdfReference> $refs
     */
    private function collectPageRefs(PdfDictionary $node, array &$refs): void
    {
        $kids = $node->get('Kids');
        if (!$kids instanceof PdfArray) {
            return;
        }
        foreach ($kids->items as $kidRef) {
            if (!$kidRef instanceof PdfReference) {
                continue;
            }
            $kid = $this->reader->resolveReference($kidRef);
            if (!$kid instanceof PdfDictionary) {
                continue;
            }
            $type = $kid->get('Type');
            if ($type instanceof PdfName && $type->value === 'Pages') {
                $this->collectPageRefs($kid, $refs);
            } else {
                $refs[] = $kidRef;
            }
        }
    }

    /**
     * Read outline items from an outline dict following /First chain.
     *
     * @param list<PdfReference> $pageRefs
     * @return list<BookmarkEntry>
     */
    private function readOutlineChildren(PdfDictionary $parentDict, array $pageRefs): array
    {
        $entries = [];
        $firstRef = $parentDict->get('First');
        if (!$firstRef instanceof PdfReference) {
            return [];
        }

        $currentRef = $firstRef;
        $visited = [];
        while ($currentRef instanceof PdfReference) {
            // Guard against circular references
            if (isset($visited[$currentRef->objectNumber])) {
                break;
            }
            $visited[$currentRef->objectNumber] = true;

            $itemDict = $this->reader->resolveReference($currentRef);
            if (!$itemDict instanceof PdfDictionary) {
                break;
            }

            $title = $this->extractTitle($itemDict);
            $pageNumber = $this->resolveDestPageNumber($itemDict, $pageRefs);
            $children = $this->readOutlineChildren($itemDict, $pageRefs);

            $entries[] = new BookmarkEntry($title, $pageNumber, $children);

            $currentRef = $itemDict->get('Next');
        }

        return $entries;
    }

    private function extractTitle(PdfDictionary $dict): string
    {
        $title = $dict->get('Title');
        if ($title instanceof PdfString) {
            return $title->value;
        }
        return '';
    }

    /**
     * Resolve a /Dest entry to a 1-based page number.
     *
     * @param list<PdfReference> $pageRefs
     */
    private function resolveDestPageNumber(PdfDictionary $itemDict, array $pageRefs): int
    {
        $dest = $itemDict->get('Dest');
        if ($dest instanceof PdfArray && count($dest->items) >= 1) {
            $pageRef = $dest->items[0];
            if ($pageRef instanceof PdfReference) {
                foreach ($pageRefs as $index => $ref) {
                    if ($ref->objectNumber === $pageRef->objectNumber) {
                        return $index + 1;
                    }
                }
            }
        }
        // Default to page 1 if destination cannot be resolved
        return 1;
    }

    // -----------------------------------------------------------------------
    // Internal — writing
    // -----------------------------------------------------------------------

    /**
     * Determine what bookmarks to write, or null if nothing changed.
     *
     * @return list<BookmarkEntry>|null
     */
    private function resolveEffectiveBookmarks(): ?array
    {
        if ($this->pendingBookmarks !== null) {
            return $this->pendingBookmarks;
        }
        if ($this->removeAll) {
            return null;
        }
        if ($this->appendBookmarks !== []) {
            $existing = $this->getBookmarks();
            return array_merge($existing, $this->appendBookmarks);
        }
        return null;
    }

    /**
     * Build the outline tree from BookmarkEntry objects.
     *
     * @param list<BookmarkEntry> $entries
     * @param list<PdfReference> $pageRefs
     */
    private function buildOutlineTree(
        IncrementalWriter $writer,
        Outline $outlineRoot,
        PdfReference $outlineRootRef,
        array $entries,
        array $pageRefs,
    ): void {
        if (empty($entries)) {
            return;
        }

        $totalCount = $this->countAllEntries($entries);
        $outlineRoot->count = $totalCount;

        $items = $this->createOutlineItems($writer, $entries, $outlineRootRef, $pageRefs);

        if (!empty($items)) {
            $outlineRoot->first = $items[0]['ref'];
            $outlineRoot->last = $items[count($items) - 1]['ref'];
        }
    }

    /**
     * Create OutlineItem objects for a list of entries at one level.
     *
     * @param list<BookmarkEntry> $entries
     * @param list<PdfReference> $pageRefs
     * @return list<array{ref: PdfReference, item: OutlineItem}>
     */
    private function createOutlineItems(
        IncrementalWriter $writer,
        array $entries,
        PdfReference $parentRef,
        array $pageRefs,
    ): array {
        $items = [];

        foreach ($entries as $entry) {
            $item = new OutlineItem($entry->title);
            $item->parent = $parentRef;

            // Set destination
            $pageIndex = max(0, min($entry->pageNumber - 1, count($pageRefs) - 1));
            if (isset($pageRefs[$pageIndex])) {
                $item->dest = new PdfArray([$pageRefs[$pageIndex], new PdfName('Fit')]);
            }

            $ref = $writer->addNewObject($item);
            $items[] = ['ref' => $ref, 'item' => $item];
        }

        // Wire prev/next doubly-linked list
        for ($i = 0; $i < count($items); $i++) {
            if ($i > 0) {
                $items[$i]['item']->prev = $items[$i - 1]['ref'];
            }
            if ($i < count($items) - 1) {
                $items[$i]['item']->next = $items[$i + 1]['ref'];
            }
        }

        // Recursively build children
        foreach ($entries as $idx => $entry) {
            if (!empty($entry->children)) {
                $childItems = $this->createOutlineItems(
                    $writer,
                    $entry->children,
                    $items[$idx]['ref'],
                    $pageRefs,
                );
                if (!empty($childItems)) {
                    $items[$idx]['item']->first = $childItems[0]['ref'];
                    $items[$idx]['item']->last = $childItems[count($childItems) - 1]['ref'];
                    $items[$idx]['item']->count = $this->countAllEntries($entry->children);
                }
            }
        }

        return $items;
    }

    /**
     * Count total visible entries recursively.
     *
     * @param list<BookmarkEntry> $entries
     */
    private function countAllEntries(array $entries): int
    {
        $count = count($entries);
        foreach ($entries as $entry) {
            $count += $this->countAllEntries($entry->children);
        }
        return $count;
    }

    // -----------------------------------------------------------------------
    // Internal — helpers
    // -----------------------------------------------------------------------

    private function cloneDictionary(PdfDictionary $dict): PdfDictionary
    {
        $clone = new PdfDictionary();
        foreach ($dict->entries as $key => $value) {
            $clone->set($key, $value);
        }
        return $clone;
    }

    private function wrapDictionary(PdfDictionary $dict): PdfObject
    {
        return new class ($dict) extends PdfObject {
            public function __construct(private readonly PdfDictionary $dict) {}
            public function toPdf(): string
            {
                return $this->dict->toPdf();
            }
        };
    }
}
