<?php

declare(strict_types=1);

namespace Phpdftk\Core;

use Phpdftk\Filters\FilterInterface;

/**
 * Represents a PDF stream object: a dictionary + binary/text data.
 *
 * The /Length entry in the dictionary is set automatically when toPdf() is called.
 */
class PdfStream extends PdfObject
{
    public PdfDictionary $dictionary;
    public string $data = '';

    private ?FilterInterface $filter = null;
    private ?string $pdfFilterName = null;

    public function __construct(PdfDictionary $dictionary = new PdfDictionary(), string $data = '')
    {
        $this->dictionary = $dictionary;
        $this->data = $data;
    }

    /**
     * Set a filter to encode/decode the stream data.
     *
     * @param FilterInterface $filter       The filter implementation
     * @param string          $pdfFilterName The PDF filter name (e.g. 'FlateDecode', 'ASCII85Decode')
     */
    public function setFilter(FilterInterface $filter, string $pdfFilterName): void
    {
        $this->filter = $filter;
        $this->pdfFilterName = $pdfFilterName;
    }

    /**
     * Returns the stream body: dictionary, stream keyword, data, endstream.
     * /Length is injected into the dictionary at serialization time.
     * If a filter is set, the data is encoded before writing.
     */
    public function toPdf(): string
    {
        $streamData = $this->data;

        if ($this->filter !== null && $this->pdfFilterName !== null) {
            $streamData = $this->filter->encode($streamData);
            $this->dictionary->set('Filter', new PdfName($this->pdfFilterName));
        }

        // Set the length based on current (possibly encoded) data
        $this->dictionary->set('Length', strlen($streamData));

        return $this->dictionary->toPdf()
            . "\nstream\n"
            . $streamData
            . "\nendstream";
    }

    public function toIndirectObject(): string
    {
        return sprintf(
            "%d %d obj\n%s\nendobj",
            $this->objectNumber,
            $this->generationNumber,
            $this->toPdf()
        );
    }
}
