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
 * Extract, reorder, remove, and split pages from a PDF.
 *
 * Uses PdfFileWriter (full rewrite) since page tree restructuring
 * cannot be done incrementally.
 *
 * Usage:
 *   PageSlicer::open('large.pdf')
 *       ->keepRange(1, 5)
 *       ->save('first-five.pdf');
 *
 *   PageSlicer::open('report.pdf')
 *       ->reorder(3, 1, 2)
 *       ->save('reordered.pdf');
 *
 * @api
 */
final class PageSlicer
{
    private string $originalBytes;

    /** @var ?list<int> 0-based page indices to output (null = not set yet) */
    private ?array $selectedIndices = null;

    /** @var list<string> */
    private array $lastVersionWarnings = [];

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
    // Extract
    // -----------------------------------------------------------------------

    public function keep(PageSelector $pages): self
    {
        $this->selectedIndices = $pages->resolve($this->reader->getPageCount());
        return $this;
    }

    public function keepPages(int ...$pageNumbers): self
    {
        return $this->keep(PageSelector::pages(...$pageNumbers));
    }

    public function keepRange(int $from, int $to): self
    {
        return $this->keep(PageSelector::range($from, $to));
    }

    // -----------------------------------------------------------------------
    // Remove
    // -----------------------------------------------------------------------

    public function remove(PageSelector $pages): self
    {
        $total = $this->reader->getPageCount();
        $removeIndices = $pages->resolve($total);
        $this->selectedIndices = array_values(array_diff(range(0, $total - 1), $removeIndices));
        return $this;
    }

    public function removePages(int ...$pageNumbers): self
    {
        return $this->remove(PageSelector::pages(...$pageNumbers));
    }

    // -----------------------------------------------------------------------
    // Reorder
    // -----------------------------------------------------------------------

    /**
     * Reorder pages. Arguments are 1-based page numbers in desired order.
     */
    public function reorder(int ...$pageOrder): self
    {
        $this->selectedIndices = array_map(fn(int $n) => $n - 1, $pageOrder);
        return $this;
    }

    public function reverse(): self
    {
        $total = $this->reader->getPageCount();
        $this->selectedIndices = array_reverse(range(0, $total - 1));
        return $this;
    }

    // -----------------------------------------------------------------------
    // Split
    // -----------------------------------------------------------------------

    /**
     * Split the PDF at a given page number.
     *
     * @param int $atPage 1-based page number where the split occurs.
     *                     Pages 1..(atPage-1) go to first result,
     *                     pages atPage..end go to second result.
     * @return array{string, string} Two PDF byte strings
     */
    public function split(int $atPage): array
    {
        $total = $this->reader->getPageCount();
        $first = clone $this;
        $first->selectedIndices = range(0, $atPage - 2);
        $second = clone $this;
        $second->selectedIndices = range($atPage - 1, $total - 1);
        return [$first->toBytes(), $second->toBytes()];
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
        $indices = $this->selectedIndices ?? range(0, $this->reader->getPageCount() - 1);

        $fw = new PdfFileWriter();
        $catalog = new Catalog();
        $fw->setCatalog($catalog);

        $pageTree = new PageTree();
        $fw->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $copier = new PageCopier($this->reader, $fw);
        $pageRefs = $copier->copyPages($indices, new PdfReference($pageTree->objectNumber));

        $pageTree->kids = $pageRefs;
        $pageTree->count = count($pageRefs);

        $result = $fw->generate();
        $this->lastVersionWarnings = $fw->getVersionWarnings();
        return $result;
    }

    // -----------------------------------------------------------------------
    // Info
    // -----------------------------------------------------------------------

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->lastVersionWarnings;
    }

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    public function getReader(): PdfReader
    {
        return $this->reader;
    }
}
