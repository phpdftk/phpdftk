<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parsed CFF (Compact Font Format) table structure.
 *
 * Stores the parsed components of a CFF table for subsetting.
 * Charstrings are stored as opaque byte arrays — no charstring
 * interpretation is performed.
 */
readonly class CffData
{
    /**
     * @param int      $major              CFF major version (always 1)
     * @param int      $minor              CFF minor version
     * @param int      $hdrSize            Header size in bytes
     * @param string   $nameIndexData      Raw Name INDEX bytes
     * @param array<int|string, int|float|array<int, int|float>> $topDictOperators Top DICT operator => operand(s)
     * @param string   $stringIndexData    Raw String INDEX bytes
     * @param string   $globalSubrIndexData Raw Global Subr INDEX bytes
     * @param array<int, string> $charStrings GID => raw charstring bytes
     * @param string   $privateDictData    Raw Private DICT bytes
     * @param string   $localSubrIndexData Raw Local Subr INDEX bytes (may be empty)
     * @param array<int, int> $charset     GID => SID/CID mapping (GID 0 = .notdef always)
     * @param ?string  $fdArrayData        Raw FDArray INDEX bytes (CIDFont only)
     * @param ?string  $fdSelectData       Raw FDSelect bytes (CIDFont only)
     */
    public function __construct(
        public int $major,
        public int $minor,
        public int $hdrSize,
        public string $nameIndexData,
        public array $topDictOperators,
        public string $stringIndexData,
        public string $globalSubrIndexData,
        public array $charStrings,
        public string $privateDictData,
        public string $localSubrIndexData,
        public array $charset,
        public ?string $fdArrayData = null,
        public ?string $fdSelectData = null,
    ) {}
}
