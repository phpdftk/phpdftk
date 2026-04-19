<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

use ApprLabs\Pdf\Core\Document\NumberTree;
use ApprLabs\Pdf\Core\Document\PageLabel;
use ApprLabs\Pdf\Core\File\IncrementalWriter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\Label\LabelStyle;

/**
 * Set page numbering labels on a PDF.
 *
 * Usage:
 *   PageLabeler::open('report.pdf')
 *       ->setRomanNumerals(1, 4)        // pages 1-4: i, ii, iii, iv
 *       ->setArabic(5, null, 1)         // pages 5+: 1, 2, 3, ...
 *       ->save('labeled.pdf');
 */
final class PageLabeler
{
    private string $originalBytes;

    /**
     * Pending label ranges: 0-based page index => [style, prefix, startNumber]
     *
     * @var array<int, array{style: LabelStyle, prefix: string, startNumber: int}>
     */
    private array $labelSpecs = [];

    private bool $removeAll = false;

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
    }

    public static function open(string $path, string $password = ''): self
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read file: $path");
        }
        return new self(PdfReader::fromString($bytes, $password), $bytes);
    }

    public static function openString(string $pdfBytes, string $password = ''): self
    {
        return new self(PdfReader::fromString($pdfBytes, $password), $pdfBytes);
    }

    // -----------------------------------------------------------------------
    // Label configuration (fluent, 1-based page numbers)
    // -----------------------------------------------------------------------

    /**
     * Set a label range starting at the given page.
     *
     * @param int $startPage 1-based page number where this label range begins
     */
    public function setLabels(int $startPage, LabelStyle $style, string $prefix = '', int $startNumber = 1): self
    {
        $this->removeAll = false;
        $this->labelSpecs[$startPage - 1] = [
            'style' => $style,
            'prefix' => $prefix,
            'startNumber' => $startNumber,
        ];
        return $this;
    }

    /**
     * Set roman numeral labels for a page range.
     *
     * @param int $fromPage 1-based start page
     * @param int $toPage 1-based end page
     */
    public function setRomanNumerals(int $fromPage, int $toPage, bool $uppercase = false): self
    {
        $style = $uppercase ? LabelStyle::RomanUpper : LabelStyle::RomanLower;
        $this->setLabels($fromPage, $style);

        // If there's a page after toPage, set arabic numbering to "reset"
        // unless the caller explicitly sets something else there
        $nextPage = $toPage + 1;
        $pageCount = $this->reader->getPageCount();
        if ($nextPage <= $pageCount && !isset($this->labelSpecs[$nextPage - 1])) {
            $this->labelSpecs[$nextPage - 1] = [
                'style' => LabelStyle::Arabic,
                'prefix' => '',
                'startNumber' => $nextPage,
            ];
        }

        return $this;
    }

    /**
     * Set alphabetic labels for a page range.
     *
     * @param int $fromPage 1-based start page
     * @param int $toPage 1-based end page
     */
    public function setAlphabetic(int $fromPage, int $toPage, bool $uppercase = false): self
    {
        $style = $uppercase ? LabelStyle::AlphaUpper : LabelStyle::AlphaLower;
        $this->setLabels($fromPage, $style);

        $nextPage = $toPage + 1;
        $pageCount = $this->reader->getPageCount();
        if ($nextPage <= $pageCount && !isset($this->labelSpecs[$nextPage - 1])) {
            $this->labelSpecs[$nextPage - 1] = [
                'style' => LabelStyle::Arabic,
                'prefix' => '',
                'startNumber' => $nextPage,
            ];
        }

        return $this;
    }

    /**
     * Set arabic numeral labels starting at a page.
     *
     * @param int $fromPage 1-based start page
     * @param int|null $toPage 1-based end page, or null for all remaining
     */
    public function setArabic(int $fromPage, ?int $toPage = null, int $startNumber = 1): self
    {
        $this->setLabels($fromPage, LabelStyle::Arabic, '', $startNumber);

        if ($toPage !== null) {
            $nextPage = $toPage + 1;
            $pageCount = $this->reader->getPageCount();
            if ($nextPage <= $pageCount && !isset($this->labelSpecs[$nextPage - 1])) {
                $this->labelSpecs[$nextPage - 1] = [
                    'style' => LabelStyle::Arabic,
                    'prefix' => '',
                    'startNumber' => $nextPage,
                ];
            }
        }

        return $this;
    }

    /**
     * Remove all page labels from the document.
     */
    public function removeLabels(): self
    {
        $this->removeAll = true;
        $this->labelSpecs = [];
        return $this;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $this->toBytes());
    }

    public function toBytes(): string
    {
        if (empty($this->labelSpecs) && !$this->removeAll) {
            return $this->originalBytes;
        }

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
        $catalog = $this->reader->getCatalog();
        $catalogDict = $this->cloneDictionary($catalog);

        if ($this->removeAll) {
            unset($catalogDict->entries['PageLabels']);
        } else {
            // Build the number tree
            $numberTree = new NumberTree();
            $numsItems = [];

            // Sort by page index
            ksort($this->labelSpecs);

            foreach ($this->labelSpecs as $pageIndex => $spec) {
                $label = new PageLabel();
                $label->s = new PdfName($spec['style']->value);
                if ($spec['prefix'] !== '') {
                    $label->p = new PdfString($spec['prefix']);
                }
                $label->st = $spec['startNumber'];

                $numsItems[] = new PdfNumber($pageIndex);
                $numsItems[] = $label;
            }

            $numberTree->nums = new PdfArray($numsItems);
            $numberTreeRef = $writer->addNewObject($numberTree);
            $catalogDict->set('PageLabels', $numberTreeRef);
        }

        // Wrap and register modified catalog
        $catalogObj = $this->wrapDictionary($catalogDict);
        $rootRef = $this->reader->getTrailer()->get('Root');
        if ($rootRef instanceof PdfReference) {
            $catalogObj->objectNumber = $rootRef->objectNumber;
            $catalogObj->generationNumber = 0;
        }
        $writer->addModifiedObject($catalogObj);

        return $writer->generate();
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    public function getReader(): PdfReader
    {
        return $this->reader;
    }

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    // -----------------------------------------------------------------------
    // Internal
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
