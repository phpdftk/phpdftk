<?php

declare(strict_types=1);

namespace ApprLabs\Filters;

/**
 * JBIG2Decode filter — ISO 14492 / ITU-T T.88 codec.
 *
 * Encodes/decodes JBIG2-compressed bitonal image data. JBIG2 is a complex
 * multi-segment format supporting symbol dictionaries, text regions,
 * halftone regions, and generic regions with arithmetic or MMR coding.
 *
 * This implementation handles the most common PDF JBIG2 patterns:
 *   1. Generic regions with MMR coding (internally Group 4 fax)
 *   2. Immediate lossless generic regions
 *   3. Page information segments for dimensions
 *
 * Encoding produces MMR-coded immediate lossless generic regions,
 * wrapping CCITTFax Group 4 data in JBIG2 segment structure.
 *
 * For complex JBIG2 streams (symbol dictionaries, arithmetic coding),
 * decoding falls back to the `jbig2dec` CLI tool if available, otherwise
 * returns the raw data unchanged.
 *
 * PDF-embedded JBIG2 streams do NOT include the file header — they
 * contain only segment data. Global segments (symbol dictionaries)
 * are provided separately via /JBIG2Globals in /DecodeParms.
 *
 * @see https://www.itu.int/rec/T-REC-T.88
 */
final class Jbig2Filter implements FilterInterface
{
    private const JBIG2_FILE_SIGNATURE = "\x97\x4A\x42\x32\x0D\x0A\x1A\x0A";

    // Segment types used by the decoder
    private const SEG_IMMEDIATE_GENERIC = 38;
    private const SEG_IMMEDIATE_GENERIC_LOSSLESS = 39;
    private const SEG_PAGE_INFO = 48;
    private const SEG_END_OF_PAGE = 49;
    private const SEG_END_OF_FILE = 51;

    /**
     * @param string $globals Optional JBIG2 global segments data (from /JBIG2Globals)
     * @param int    $width   Image width in pixels (required for encoding)
     * @param int    $height  Image height in pixels (required for encoding)
     */
    public function __construct(
        private string $globals = '',
        private int $width = 0,
        private int $height = 0,
    ) {}

    public function encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        if ($this->width <= 0 || $this->height <= 0) {
            throw new \RuntimeException('JBIG2 encoding requires width and height');
        }

        // Encode pixel data using CCITTFax Group 4 (MMR)
        $ccitt = new CCITTFaxFilter(
            k: -1,
            columns: $this->width,
            rows: $this->height,
            endOfBlock: true,
            blackIs1: true, // JBIG2 convention: 1 = black
        );
        $mmrData = $ccitt->encode($data);

        $output = '';

        // Segment 0: Page Information (type 48, 19 bytes data)
        $pageInfo = pack('N', $this->width);    // width
        $pageInfo .= pack('N', $this->height);  // height
        $pageInfo .= pack('N', 0);              // x resolution
        $pageInfo .= pack('N', 0);              // y resolution
        $pageInfo .= chr(0);                    // flags
        $pageInfo .= pack('n', 0);              // striping
        $output .= $this->buildSegmentHeader(0, self::SEG_PAGE_INFO, 1, strlen($pageInfo));
        $output .= $pageInfo;

        // Segment 1: Immediate Lossless Generic Region (type 39)
        $regionData = pack('N', $this->width);   // region width
        $regionData .= pack('N', $this->height); // region height
        $regionData .= pack('N', 0);             // x offset
        $regionData .= pack('N', 0);             // y offset
        $regionData .= chr(0);                   // combination operator
        $regionData .= pack('n', 1);             // flags: MMR=1
        $regionData .= $mmrData;
        $output .= $this->buildSegmentHeader(1, self::SEG_IMMEDIATE_GENERIC_LOSSLESS, 1, strlen($regionData));
        $output .= $regionData;

        // Segment 2: End of Page (type 49)
        $output .= $this->buildSegmentHeader(2, self::SEG_END_OF_PAGE, 1, 0);

        return $output;
    }

    public function decode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        // Prepend globals if provided
        $fullData = $this->globals . $data;

        // Check for file header (standalone JBIG2 files)
        $offset = 0;
        if (strlen($fullData) >= 8 && substr($fullData, 0, 8) === self::JBIG2_FILE_SIGNATURE) {
            // Skip file header
            $flags = ord($fullData[8]);
            $offset = 9;
            if (($flags & 0x01) === 0) {
                // Known page count
                $offset += 4;
            }
        }

        // Parse segments to find page info and generic region data
        $pageWidth = 0;
        $pageHeight = 0;
        $pageBitmap = null;

        while ($offset < strlen($fullData)) {
            $segment = $this->parseSegmentHeader($fullData, $offset);
            if ($segment === null) {
                break;
            }

            $segData = substr($fullData, $segment['dataOffset'], $segment['dataLength']);

            switch ($segment['type']) {
                case self::SEG_PAGE_INFO:
                    if (strlen($segData) >= 19) {
                        $pageWidth = unpack('N', $segData, 0)[1];
                        $pageHeight = unpack('N', $segData, 4)[1];
                        // Remaining: xRes (4), yRes (4), flags (1), striping (2)
                    }
                    break;

                case self::SEG_IMMEDIATE_GENERIC:
                case self::SEG_IMMEDIATE_GENERIC_LOSSLESS:
                    // Generic region segment
                    $pageBitmap = $this->decodeGenericRegion($segData, $pageWidth, $pageHeight);
                    break;

                case self::SEG_END_OF_PAGE:
                case self::SEG_END_OF_FILE:
                    break 2;
            }

            $offset = $segment['dataOffset'] + $segment['dataLength'];
        }

        if ($pageBitmap !== null) {
            return $pageBitmap;
        }

        // Fallback: try jbig2dec CLI
        $result = $this->tryJbig2dec($data);
        if ($result !== null) {
            return $result;
        }

        // Cannot decode — return raw data (pass-through)
        return $data;
    }

    /**
     * Parse a JBIG2 segment header.
     *
     * @return array{number: int, type: int, pageAssoc: int, dataOffset: int, dataLength: int}|null
     */
    private function parseSegmentHeader(string $data, int $offset): ?array
    {
        if ($offset + 6 > strlen($data)) {
            return null;
        }

        // Segment number (4 bytes)
        $segNum = unpack('N', $data, $offset)[1];
        $offset += 4;

        // Flags (1 byte)
        $flags = ord($data[$offset]);
        $segType = $flags & 0x3F;
        $pageAssocSize = ($flags & 0x40) ? 4 : 1;
        $deferredFlag = ($flags & 0x80) !== 0;
        $offset += 1;

        // Referred-to segment count
        $refCountByte = ord($data[$offset]);
        $refCount = ($refCountByte >> 5) & 0x07;
        $offset += 1;

        if ($refCount === 7) {
            // Long-form: next 4 bytes have actual count
            if ($offset + 4 > strlen($data)) {
                return null;
            }
            $refCount = unpack('N', $data, $offset)[1] & 0x1FFFFFFF;
            $offset += 4;
        }

        // Skip referred-to segment numbers
        $refNumSize = ($segNum <= 256) ? 1 : (($segNum <= 65536) ? 2 : 4);
        $offset += $refCount * $refNumSize;

        // Page association
        if ($offset + $pageAssocSize > strlen($data)) {
            return null;
        }
        $pageAssoc = ($pageAssocSize === 4) ? unpack('N', $data, $offset)[1] : ord($data[$offset]);
        $offset += $pageAssocSize;

        // Data length (4 bytes)
        if ($offset + 4 > strlen($data)) {
            return null;
        }
        $dataLength = unpack('N', $data, $offset)[1];
        $offset += 4;

        // Handle unknown data length (0xFFFFFFFF)
        if ($dataLength === 0xFFFFFFFF) {
            // Scan for end of data — use remaining data
            $dataLength = strlen($data) - $offset;
        }

        return [
            'number' => $segNum,
            'type' => $segType,
            'pageAssoc' => $pageAssoc,
            'dataOffset' => $offset,
            'dataLength' => $dataLength,
        ];
    }

    /**
     * Decode a generic region segment.
     *
     * Handles MMR-coded regions (internally Group 4 fax encoding).
     */
    private function decodeGenericRegion(string $data, int $pageWidth, int $pageHeight): ?string
    {
        if (strlen($data) < 19) {
            return null;
        }

        // Region segment information field (17 bytes per ISO 14492 §7.4.1):
        //   width (4) + height (4) + x offset (4) + y offset (4) + flags (1)
        $regionWidth = unpack('N', $data, 0)[1];
        $regionHeight = unpack('N', $data, 4)[1];
        $offset = 17;

        // Generic region segment flags (2 bytes)
        $flags = unpack('n', $data, $offset)[1];
        $mmr = ($flags & 0x0001) !== 0; // bit 0: MMR coding
        $offset += 2;

        if (!$mmr) {
            // Arithmetic coding — too complex for pure PHP, skip
            // Template and AT pixels would need to be parsed
            $offset += 3; // skip typical template + GB AT flags
            return null;
        }

        // Skip GBAT pixels (not used in MMR mode)

        // MMR-coded data = CCITT Group 4 encoding
        $mmrData = substr($data, $offset);
        $width = $regionWidth > 0 ? $regionWidth : ($pageWidth > 0 ? $pageWidth : 1);
        $height = $regionHeight > 0 ? $regionHeight : ($pageHeight > 0 ? $pageHeight : 0);

        $ccitt = new CCITTFaxFilter(
            k: -1,            // Group 4
            columns: $width,
            rows: $height,
            endOfBlock: true,
            blackIs1: true,   // JBIG2 convention: 1 = black
        );

        return $ccitt->decode($mmrData);
    }

    /**
     * Try the jbig2dec CLI tool for complex JBIG2 streams.
     */
    private function tryJbig2dec(string $data): ?string
    {
        // Find jbig2dec
        $paths = ['/opt/homebrew/bin/jbig2dec', '/usr/local/bin/jbig2dec', '/usr/bin/jbig2dec'];
        $jbig2dec = null;
        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $jbig2dec = $path;
                break;
            }
        }
        if ($jbig2dec === null) {
            $which = trim(shell_exec('which jbig2dec 2>/dev/null') ?? '');
            if ($which !== '' && file_exists($which)) {
                $jbig2dec = $which;
            }
        }
        if ($jbig2dec === null) {
            return null;
        }

        // Write input to temp file
        $tmpIn = tempnam(sys_get_temp_dir(), 'phpdftk_jbig2_');
        $tmpOut = $tmpIn . '.pbm';

        // Build complete JBIG2 file with header
        $jbig2File = self::JBIG2_FILE_SIGNATURE;
        $jbig2File .= "\x01"; // flags: sequential, known page count
        $jbig2File .= pack('N', 1); // 1 page
        if ($this->globals !== '') {
            $jbig2File .= $this->globals;
        }
        $jbig2File .= $data;

        file_put_contents($tmpIn, $jbig2File);

        $cmd = escapeshellarg($jbig2dec) . ' -t pbm -o ' . escapeshellarg($tmpOut) . ' ' . escapeshellarg($tmpIn) . ' 2>/dev/null';
        exec($cmd, $output, $exitCode);

        @unlink($tmpIn);

        if ($exitCode !== 0 || !file_exists($tmpOut)) {
            @unlink($tmpOut);
            return null;
        }

        $pbmData = file_get_contents($tmpOut);
        @unlink($tmpOut);

        if ($pbmData === false) {
            return null;
        }

        // Convert PBM to raw bitmap
        return $this->parsePbm($pbmData);
    }

    /**
     * Parse a PBM (Portable Bitmap) file to raw pixel bytes.
     */
    private function parsePbm(string $data): ?string
    {
        // PBM format: P4\n width height\n binary-data
        if (!str_starts_with($data, 'P4')) {
            return null;
        }

        // Skip header
        $pos = 2;
        // Skip whitespace and comments
        while ($pos < strlen($data)) {
            if ($data[$pos] === '#') {
                $pos = (int) strpos($data, "\n", $pos) + 1;
            } elseif ($data[$pos] === ' ' || $data[$pos] === "\n" || $data[$pos] === "\r" || $data[$pos] === "\t") {
                $pos++;
            } else {
                break;
            }
        }

        // Read width and height
        if (preg_match('/(\d+)\s+(\d+)\s/', $data, $m, 0, $pos)) {
            $pos += strlen($m[0]);
            // Raw binary data follows
            return substr($data, $pos);
        }

        return null;
    }

    /**
     * Build a JBIG2 segment header.
     */
    private function buildSegmentHeader(int $segNum, int $type, int $pageAssoc, int $dataLength): string
    {
        $header = pack('N', $segNum);       // segment number (4 bytes)
        $header .= chr($type);             // flags: type in low 6 bits (1 byte)
        $header .= chr(0);                 // referred-to count = 0 (1 byte)
        $header .= chr($pageAssoc);        // page association (1 byte)
        $header .= pack('N', $dataLength); // data length (4 bytes)

        return $header;
    }
}
