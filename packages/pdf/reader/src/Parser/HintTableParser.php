<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Parser;

/**
 * Parses the binary hint table data from linearized PDF hint streams.
 *
 * The hint stream body contains the page offset hint table (required)
 * and optionally a shared object hint table, both using bit-packed
 * variable-width fields. Layout defined in ISO 32000-2 Annex F §F.4.
 */
final class HintTableParser
{
    public function __construct(private readonly string $data)
    {
    }

    /**
     * Parse the page offset hint table starting at the given byte offset.
     *
     * The header is 11 four-byte integers (44 bytes), followed by
     * per-page bit-packed entries.
     *
     * @param int $offset Byte offset within the hint stream data
     * @param int $numPages Number of pages in the document
     */
    public function parsePageOffsetTable(int $offset, int $numPages): PageOffsetHintTable
    {
        if (strlen($this->data) < $offset + 44) {
            throw new \RuntimeException('Hint stream too short for page offset table header');
        }

        // Read 11 header values as 32-bit big-endian unsigned integers
        $pos = $offset;
        $read32 = function () use (&$pos): int {
            $val = unpack('N', substr($this->data, $pos, 4));
            $pos += 4;
            return $val[1];
        };

        $minObjectsPerPage       = $read32(); // Item 1
        $firstPageLocation       = $read32(); // Item 2
        $bitsForObjectCount      = $read32(); // Item 3
        $minPageLength           = $read32(); // Item 4
        $bitsForPageLength       = $read32(); // Item 5
        $minSharedRefsPerPage    = $read32(); // Item 6 (offset in shared obj hint table)
        $bitsForSharedRefCount   = $read32(); // Item 7
        $minSharedObjId          = $read32(); // Item 8
        $bitsForSharedObjId      = $read32(); // Item 9
        $bitsForSharedObjNum     = $read32(); // Item 10
        $minContentStreamOffset  = $read32(); // Item 11

        // Read remaining header items if data allows
        $bitsForContentOffset = 0;
        $minContentStreamLength = 0;
        $bitsForContentLength = 0;

        if (strlen($this->data) >= $pos + 12) {
            $bitsForContentOffset   = $read32(); // Item 12
            $minContentStreamLength = $read32(); // Item 13
            $bitsForContentLength   = $read32(); // Item 14
        }

        // Parse per-page entries (bit-packed)
        $reader = new BitReader(substr($this->data, $pos));

        $entries = [];

        // First pass: read object count deltas for all pages
        $objectCountDeltas = [];
        for ($i = 0; $i < $numPages; $i++) {
            $objectCountDeltas[] = $reader->readBits($bitsForObjectCount);
        }
        $reader->alignToByte();

        // Second pass: page length deltas
        $pageLengthDeltas = [];
        for ($i = 0; $i < $numPages; $i++) {
            $pageLengthDeltas[] = $reader->readBits($bitsForPageLength);
        }
        $reader->alignToByte();

        // Third pass: shared ref count deltas
        $sharedRefCountDeltas = [];
        for ($i = 0; $i < $numPages; $i++) {
            $sharedRefCountDeltas[] = $reader->readBits($bitsForSharedRefCount);
        }
        $reader->alignToByte();

        // Fourth pass: shared object IDs (variable count per page)
        $sharedObjIds = [];
        for ($i = 0; $i < $numPages; $i++) {
            $count = $minSharedRefsPerPage + $sharedRefCountDeltas[$i];
            $ids = [];
            for ($j = 0; $j < $count; $j++) {
                $ids[] = $minSharedObjId + $reader->readBits($bitsForSharedObjId);
            }
            $sharedObjIds[] = $ids;
        }
        $reader->alignToByte();

        // Fifth pass: shared object numerator deltas
        $sharedObjNumDeltas = [];
        for ($i = 0; $i < $numPages; $i++) {
            $count = $minSharedRefsPerPage + $sharedRefCountDeltas[$i];
            $delta = 0;
            for ($j = 0; $j < $count; $j++) {
                $delta += $reader->readBits($bitsForSharedObjNum);
            }
            $sharedObjNumDeltas[] = $delta;
        }
        $reader->alignToByte();

        // Sixth pass: content stream offset deltas
        $contentOffsetDeltas = [];
        for ($i = 0; $i < $numPages; $i++) {
            $contentOffsetDeltas[] = $reader->readBits($bitsForContentOffset);
        }
        $reader->alignToByte();

        // Seventh pass: content stream length deltas
        $contentLengthDeltas = [];
        for ($i = 0; $i < $numPages; $i++) {
            $contentLengthDeltas[] = $reader->readBits($bitsForContentLength);
        }

        // Build entries
        for ($i = 0; $i < $numPages; $i++) {
            $entries[] = new PageHintEntry(
                objectCountDelta: $objectCountDeltas[$i],
                pageLengthDelta: $pageLengthDeltas[$i],
                sharedRefCountDelta: $sharedRefCountDeltas[$i],
                sharedObjIds: $sharedObjIds[$i],
                sharedObjNumeratorDelta: $sharedObjNumDeltas[$i],
                contentStreamOffsetDelta: $contentOffsetDeltas[$i],
                contentStreamLengthDelta: $contentLengthDeltas[$i],
            );
        }

        return new PageOffsetHintTable(
            minObjectsPerPage: $minObjectsPerPage,
            firstPageLocation: $firstPageLocation,
            minPageLength: $minPageLength,
            minSharedRefsPerPage: $minSharedRefsPerPage,
            minSharedObjId: $minSharedObjId,
            minContentStreamOffset: $minContentStreamOffset,
            minContentStreamLength: $minContentStreamLength,
            entries: $entries,
        );
    }

    /**
     * Parse the shared object hint table starting at the given byte offset.
     *
     * @param int $offset Byte offset within the hint stream data
     */
    public function parseSharedObjectTable(int $offset): SharedObjectHintTable
    {
        if (strlen($this->data) < $offset + 20) {
            throw new \RuntimeException('Hint stream too short for shared object table header');
        }

        $pos = $offset;
        $read32 = function () use (&$pos): int {
            $val = unpack('N', substr($this->data, $pos, 4));
            $pos += 4;
            return $val[1];
        };

        $firstSharedObjNumber = $read32(); // Item 1
        $firstSharedObjOffset = $read32(); // Item 2
        $numSharedGroups      = $read32(); // Item 3
        $minGroupLength       = $read32(); // Item 4
        $bitsForGroupLength   = $read32(); // Item 5

        // Parse per-group entries
        $reader = new BitReader(substr($this->data, $pos));
        $entries = [];

        // First pass: length deltas
        $lengthDeltas = [];
        for ($i = 0; $i < $numSharedGroups; $i++) {
            $lengthDeltas[] = $reader->readBits($bitsForGroupLength);
        }
        $reader->alignToByte();

        // Second pass: signature flags (1 bit each)
        $sigFlags = [];
        for ($i = 0; $i < $numSharedGroups; $i++) {
            $sigFlags[] = $reader->readBits(1) === 1;
        }
        $reader->alignToByte();

        // Third pass: number of objects per group (if signature flag set)
        for ($i = 0; $i < $numSharedGroups; $i++) {
            $numObjects = $sigFlags[$i] ? 0 : 1; // Simplified — full spec has more logic
            $entries[] = new SharedObjectHintEntry(
                lengthDelta: $lengthDeltas[$i],
                isSignatureObject: $sigFlags[$i],
                numObjects: $numObjects,
            );
        }

        return new SharedObjectHintTable(
            firstSharedObjNumber: $firstSharedObjNumber,
            firstSharedObjOffset: $firstSharedObjOffset,
            numSharedGroups: $numSharedGroups,
            minGroupLength: $minGroupLength,
            entries: $entries,
        );
    }
}
