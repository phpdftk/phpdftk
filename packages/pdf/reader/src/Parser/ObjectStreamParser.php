<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Parser;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;
use ApprLabs\Pdf\Reader\Tokenizer\StringSource;
use ApprLabs\Pdf\Reader\Tokenizer\Tokenizer;

/**
 * Unpacks objects stored inside a /Type /ObjStm stream —
 * ISO 32000-2 §7.5.7.
 */
final class ObjectStreamParser
{
    public function __construct(
        private readonly StreamParser $streamParser,
    ) {
    }

    /**
     * Unpack all objects from an ObjStm.
     *
     * @return array<int, Serializable> objNum => parsed value
     */
    public function unpack(PdfStream $objStm): array
    {
        $dict = $objStm->dictionary;

        // Decompress
        $data = $this->streamParser->decode($objStm->data, $dict);

        $nVal = $dict->get('N');
        $n = ($nVal instanceof PdfNumber) ? (int) $nVal->toPdf() : 0;

        $firstVal = $dict->get('First');
        $first = ($firstVal instanceof PdfNumber) ? (int) $firstVal->toPdf() : 0;

        if ($n === 0 || $first === 0) {
            return [];
        }

        // Parse the header: N pairs of (objNum offset)
        $headerSource = new StringSource(substr($data, 0, $first));
        $headerTokenizer = new Tokenizer($headerSource);
        $headerParser = new ObjectParser($headerTokenizer, $headerSource);

        $objNums = [];
        $offsets = [];
        for ($i = 0; $i < $n; $i++) {
            $numToken = $headerTokenizer->nextToken();
            $offToken = $headerTokenizer->nextToken();
            $objNums[] = (int) $numToken->value;
            $offsets[] = (int) $offToken->value;
        }

        // Parse each embedded object from the body (after /First)
        $body = substr($data, $first);
        $result = [];

        for ($i = 0; $i < $n; $i++) {
            $start = $offsets[$i];
            $end = ($i + 1 < $n) ? $offsets[$i + 1] : strlen($body);
            $slice = substr($body, $start, $end - $start);

            $objSource = new StringSource($slice);
            $objTokenizer = new Tokenizer($objSource);
            $objParser = new ObjectParser($objTokenizer, $objSource);

            try {
                $result[$objNums[$i]] = $objParser->parseValue();
            } catch (\Throwable $e) {
                throw new InvalidPdfException(
                    "Failed to parse object {$objNums[$i]} inside ObjStm: {$e->getMessage()}"
                );
            }
        }

        return $result;
    }
}
