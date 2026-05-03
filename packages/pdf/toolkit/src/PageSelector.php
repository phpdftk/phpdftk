<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

/**
 * Selects which pages an operation applies to.
 *
 * Used by PdfStamper, AnnotationFlattener, PageTransformer, TextRedactor, etc.
 * Page numbers are 1-based throughout the toolkit API.
 */
final class PageSelector
{
    private function __construct(
        private readonly string $mode,
        /** @var list<int> */
        private readonly array $pages = [],
        private readonly int $from = 0,
        private readonly int $to = 0,
    ) {}

    public static function all(): self
    {
        return new self('all');
    }

    public static function pages(int ...$pageNumbers): self
    {
        return new self('pages', array_values($pageNumbers));
    }

    public static function range(int $from, int $to): self
    {
        return new self('range', from: $from, to: $to);
    }

    public static function even(): self
    {
        return new self('even');
    }

    public static function odd(): self
    {
        return new self('odd');
    }

    /**
     * Check if a page number matches this selector.
     *
     * @param int $pageNumber 1-based page number
     * @param int $totalPages Total pages in the document
     */
    public function matches(int $pageNumber, int $totalPages): bool
    {
        return match ($this->mode) {
            'all' => true,
            'pages' => in_array($pageNumber, $this->pages, true),
            'range' => $pageNumber >= $this->from && $pageNumber <= $this->to,
            'even' => $pageNumber % 2 === 0,
            'odd' => $pageNumber % 2 === 1,
            default => false,
        };
    }

    /**
     * Resolve matching page indices (0-based) for a document.
     *
     * @return list<int> 0-based page indices
     */
    public function resolve(int $totalPages): array
    {
        $indices = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($this->matches($i, $totalPages)) {
                $indices[] = $i - 1;
            }
        }
        return $indices;
    }
}
