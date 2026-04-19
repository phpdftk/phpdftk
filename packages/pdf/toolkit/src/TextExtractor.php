<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

use ApprLabs\Pdf\Reader\PdfReader;

/**
 * Extract text from PDFs — per page, full document, or with search.
 *
 * Wraps PdfReader's text extraction with a friendly, toolkit-level API.
 * All page numbers are 1-based.
 *
 * Usage:
 *   $text = TextExtractor::open('report.pdf')->allPages();
 *
 *   $results = TextExtractor::open('contract.pdf')->search('indemnification');
 *   foreach ($results as $match) {
 *       echo "Page {$match->pageNumber}: {$match->text}\n";
 *   }
 */
final class TextExtractor
{
    private function __construct(
        private readonly PdfReader $reader,
    ) {}

    public static function open(string $path, string $password = ''): self
    {
        return new self(PdfReader::fromFile($path, $password));
    }

    public static function openString(string $pdfBytes, string $password = ''): self
    {
        return new self(PdfReader::fromString($pdfBytes, $password));
    }

    // -----------------------------------------------------------------------
    // Simple extraction
    // -----------------------------------------------------------------------

    /**
     * Extract text from a single page.
     *
     * @param int $pageNumber 1-based page number
     */
    public function page(int $pageNumber): string
    {
        return $this->reader->extractText($pageNumber - 1);
    }

    /**
     * Extract text from all pages, joined by a separator.
     */
    public function allPages(string $separator = "\n\n"): string
    {
        return $this->reader->extractAllText($separator);
    }

    /**
     * Extract text per page.
     *
     * @return array<int, string> 1-based page number => text
     */
    public function perPage(): array
    {
        $result = [];
        $count = $this->reader->getPageCount();
        for ($i = 0; $i < $count; $i++) {
            $result[$i + 1] = $this->reader->extractText($i);
        }
        return $result;
    }

    // -----------------------------------------------------------------------
    // Search
    // -----------------------------------------------------------------------

    /**
     * Check if a text string appears anywhere in the document.
     */
    public function contains(string $text): bool
    {
        $count = $this->reader->getPageCount();
        for ($i = 0; $i < $count; $i++) {
            $pageText = $this->reader->extractText($i);
            if (str_contains($pageText, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search for a text string across all pages.
     */
    public function search(string $text): TextSearchResults
    {
        $matches = [];
        $count = $this->reader->getPageCount();

        for ($i = 0; $i < $count; $i++) {
            $pageText = $this->reader->extractText($i);
            $offset = 0;
            while (($pos = strpos($pageText, $text, $offset)) !== false) {
                $matches[] = new TextMatch(
                    pageNumber: $i + 1,
                    text: $text,
                    offset: $pos,
                );
                $offset = $pos + strlen($text);
            }
        }

        return new TextSearchResults($matches);
    }

    /**
     * Search for a regex pattern across all pages.
     */
    public function searchPattern(string $regex): TextSearchResults
    {
        $matches = [];
        $count = $this->reader->getPageCount();

        for ($i = 0; $i < $count; $i++) {
            $pageText = $this->reader->extractText($i);
            if (preg_match_all($regex, $pageText, $m, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($m[0] as [$matchText, $offset]) {
                    $matches[] = new TextMatch(
                        pageNumber: $i + 1,
                        text: $matchText,
                        offset: $offset,
                    );
                }
            }
        }

        return new TextSearchResults($matches);
    }

    // -----------------------------------------------------------------------
    // Info
    // -----------------------------------------------------------------------

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    // -----------------------------------------------------------------------
    // Escape hatch
    // -----------------------------------------------------------------------

    public function getReader(): PdfReader
    {
        return $this->reader;
    }
}
