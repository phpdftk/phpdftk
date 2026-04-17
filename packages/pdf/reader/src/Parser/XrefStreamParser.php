<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Parser;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;
use ApprLabs\Pdf\Reader\Tokenizer\Source;
use ApprLabs\Pdf\Reader\Tokenizer\Tokenizer;
use ApprLabs\Pdf\Reader\XrefEntry;

/**
 * Parses a cross-reference stream (/Type /XRef) — ISO 32000-2 §7.5.8.
 */
final class XrefStreamParser
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly Source $source,
        private readonly ObjectParser $objectParser,
        private readonly StreamParser $streamParser,
    ) {
    }

    /**
     * Parse a cross-reference stream at the given byte offset.
     *
     * @return array{0: array<int, XrefEntry>, 1: PdfDictionary}
     */
    public function parseXrefStream(int $offset): array
    {
        $this->tokenizer->seek($offset);

        [$objNum, $genNum, $value] = $this->objectParser->parseIndirectObject();

        if (!$value instanceof PdfStream) {
            throw new InvalidPdfException(
                "Expected a stream at xref stream offset $offset, got " . $value::class
            );
        }

        $dict = $value->dictionary;

        // Decompress the stream data
        $data = $this->streamParser->decode($value->data, $dict);

        // Read /W (field widths)
        $wArr = $dict->get('W');
        if (!$wArr instanceof PdfArray || count($wArr->items) !== 3) {
            throw new InvalidPdfException('Xref stream missing or invalid /W array');
        }
        $w = [];
        foreach ($wArr->items as $item) {
            $w[] = ($item instanceof PdfNumber) ? (int) $item->toPdf() : 0;
        }

        // Read /Size
        $sizeVal = $dict->get('Size');
        $size = ($sizeVal instanceof PdfNumber) ? (int) $sizeVal->toPdf() : 0;

        // Read /Index (default [0 Size])
        $indexArr = $dict->get('Index');
        if ($indexArr instanceof PdfArray) {
            $indexPairs = [];
            $items = $indexArr->items;
            for ($i = 0; $i < count($items) - 1; $i += 2) {
                $first = ($items[$i] instanceof PdfNumber) ? (int) $items[$i]->toPdf() : 0;
                $count = ($items[$i + 1] instanceof PdfNumber) ? (int) $items[$i + 1]->toPdf() : 0;
                $indexPairs[] = [$first, $count];
            }
        } else {
            $indexPairs = [[0, $size]];
        }

        // Unpack binary entries
        $entries = [];
        $dataPos = 0;
        $dataLen = strlen($data);

        foreach ($indexPairs as [$firstObj, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $fields = [];
                for ($f = 0; $f < 3; $f++) {
                    $val = 0;
                    for ($b = 0; $b < $w[$f]; $b++) {
                        $val = ($val << 8);
                        if ($dataPos < $dataLen) {
                            $val |= ord($data[$dataPos++]);
                        }
                    }
                    // If w[f] is 0, the default for field 0 is 1 (type=inUse), others are 0
                    if ($w[$f] === 0) {
                        $val = ($f === 0) ? 1 : 0;
                    }
                    $fields[] = $val;
                }

                $entries[$firstObj + $i] = new XrefEntry(
                    type: $fields[0],
                    offset: $fields[1],
                    generation: $fields[2],
                );
            }
        }

        return [$entries, $dict];
    }
}
