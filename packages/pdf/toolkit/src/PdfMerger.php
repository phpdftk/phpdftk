<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Internal\PageCopier;

/**
 * Combine multiple PDFs into one document.
 *
 * Usage:
 *   PdfMerger::create()
 *       ->addFile('chapter1.pdf')
 *       ->addFile('chapter2.pdf')
 *       ->save('book.pdf');
 *
 * @api
 */
final class PdfMerger
{
    /** @var list<array{reader: PdfReader, pages: ?PageSelector}> */
    private array $sources = [];

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }

    // -----------------------------------------------------------------------
    // Add sources
    // -----------------------------------------------------------------------

    public function addFile(string $path, string $password = ''): self
    {
        $bytes = LocalFilesystem::readFile($path);
        $this->sources[] = ['reader' => PdfReader::fromString($bytes, $password), 'pages' => null];
        return $this;
    }

    public function addString(string $pdfBytes, string $password = ''): self
    {
        $this->sources[] = ['reader' => PdfReader::fromString($pdfBytes, $password), 'pages' => null];
        return $this;
    }

    public function addPages(string $path, PageSelector $pages, string $password = ''): self
    {
        $bytes = LocalFilesystem::readFile($path);
        $this->sources[] = ['reader' => PdfReader::fromString($bytes, $password), 'pages' => $pages];
        return $this;
    }

    // -----------------------------------------------------------------------
    // Info
    // -----------------------------------------------------------------------

    public function getSourceCount(): int
    {
        return count($this->sources);
    }

    public function getTotalPageCount(): int
    {
        $total = 0;
        foreach ($this->sources as $source) {
            if ($source['pages'] !== null) {
                $total += count($source['pages']->resolve($source['reader']->getPageCount()));
            } else {
                $total += $source['reader']->getPageCount();
            }
        }
        return $total;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        LocalFilesystem::writeFile($path, $this->toBytes(), createDirectories: true);
    }

    /**
     * Build the merged PDF.
     *
     * Creates a new PdfFileWriter with a fresh page tree, then uses PageCopier
     * for each source to deep-copy pages and their dependent objects (fonts,
     * images, ExtGState, etc.) with reference remapping so cross-document
     * object numbers do not collide.
     */
    public function toBytes(): string
    {
        if (empty($this->sources)) {
            throw new \RuntimeException('No source PDFs added');
        }

        $fw = new PdfFileWriter();
        $catalog = new Catalog();
        $fw->setCatalog($catalog);

        $pageTree = new PageTree();
        $fw->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $allPageRefs = [];

        foreach ($this->sources as $source) {
            $reader = $source['reader'];
            $pageCount = $reader->getPageCount();

            if ($source['pages'] !== null) {
                $indices = $source['pages']->resolve($pageCount);
            } else {
                $indices = range(0, $pageCount - 1);
            }

            $copier = new PageCopier($reader, $fw);
            $pageRefs = $copier->copyPages($indices, new PdfReference($pageTree->objectNumber));
            $allPageRefs = array_merge($allPageRefs, $pageRefs);
        }

        $pageTree->kids = $allPageRefs;
        $pageTree->count = count($allPageRefs);

        $result = $fw->generate();
        $this->lastVersionWarnings = $fw->getVersionWarnings();
        return $result;
    }

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->lastVersionWarnings;
    }
}
