<?php

declare(strict_types=1);

namespace ApprLabs\FontParser;

use ApprLabs\Encoding\GlyphList;
use ApprLabs\Encoding\StandardEncodingTable;

/**
 * Parses Type 1 font files (PFB binary and PFA ASCII formats).
 *
 * Extracts font metrics, encoding, glyph widths, and segment lengths
 * needed for PDF embedding via Type1FontFile.
 */
class Type1Parser
{
    public function __construct(private readonly string $path) {}

    /**
     * Create a parser from raw font bytes instead of a file path.
     */
    public static function fromBytes(string $fontBytes): self
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_t1_');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file for font data');
        }
        file_put_contents($tmp, $fontBytes);
        return new self($tmp);
    }

    public function parse(): Type1Data
    {
        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read font file: {$this->path}");
        }

        // Detect format and extract segments
        if (strlen($raw) >= 2 && ord($raw[0]) === 0x80) {
            // PFB (binary) format
            [$asciiSegment, $binarySegment, $trailerSegment, $length1, $length2, $length3] = $this->parsePfb($raw);
        } else {
            // PFA (ASCII) format
            [$asciiSegment, $binarySegment, $trailerSegment, $length1, $length2, $length3] = $this->parsePfa($raw);
        }

        // Parse metrics from ASCII header
        $metrics = $this->parseAsciiHeader($asciiSegment);

        // Parse encoding from ASCII header
        $encoding = $this->parseEncoding($asciiSegment);

        // Parse CharStrings to discover available glyph names
        $charStringGlyphs = $this->parseCharStringNames($asciiSegment);

        // Build glyph widths from /CharMetrics or /Metrics if available
        // Type 1 fonts encode widths in the charstrings (encrypted), but
        // many also declare them in the ASCII header via /Metrics or via
        // the font's built-in metrics dictionary.
        $glyphWidths = $this->parseGlyphWidths($asciiSegment);

        // Build character widths and Unicode map from encoding
        $glyphList = GlyphList::getList();
        $charWidths = [];
        $unicodeMap = [];
        foreach ($encoding as $code => $glyphName) {
            if ($glyphName === '.notdef') {
                continue;
            }
            if (isset($glyphWidths[$glyphName])) {
                $charWidths[$code] = $glyphWidths[$glyphName];
            }
            if (isset($glyphList[$glyphName])) {
                $unicodeMap[$code] = $glyphList[$glyphName];
            }
        }

        // Rebuild font bytes in PFB format for embedding
        $fontBytes = $this->buildPfbBytes($asciiSegment, $binarySegment, $trailerSegment);

        // Determine flags
        $flags = $this->buildFlags($metrics);

        return new Type1Data(
            postScriptName: $metrics['fontName'],
            familyName: $metrics['familyName'],
            ascent: $metrics['ascent'],
            descent: $metrics['descent'],
            capHeight: $metrics['capHeight'],
            xHeight: $metrics['xHeight'],
            italicAngle: $metrics['italicAngle'],
            stemV: $metrics['stemV'],
            flags: $flags,
            fontBBox: $metrics['fontBBox'],
            charWidths: $charWidths,
            unicodeMap: $unicodeMap,
            fontBytes: $fontBytes,
            length1: $length1,
            length2: $length2,
            length3: $length3,
            glyphWidths: $glyphWidths,
            encoding: $encoding,
        );
    }

    /**
     * Parse PFB (Printer Font Binary) format.
     *
     * PFB files consist of segments, each with a 6-byte header:
     *   byte 0: 0x80 (start marker)
     *   byte 1: segment type (1=ASCII, 2=binary, 3=EOF)
     *   bytes 2-5: segment length (little-endian uint32)
     *
     * @return array{string, string, string, int, int, int}
     */
    private function parsePfb(string $data): array
    {
        $offset = 0;
        $ascii = '';
        $binary = '';
        $trailer = '';
        $length1 = 0;
        $length2 = 0;
        $length3 = 0;
        $len = strlen($data);

        while ($offset < $len) {
            if (ord($data[$offset]) !== 0x80) {
                break;
            }
            $type = ord($data[$offset + 1]);
            if ($type === 3) {
                // EOF marker
                break;
            }
            $segLen = unpack('V', substr($data, $offset + 2, 4))[1];
            $segData = substr($data, $offset + 6, $segLen);
            $offset += 6 + $segLen;

            if ($type === 1) {
                // ASCII segment
                if ($binary === '') {
                    $ascii .= $segData;
                    $length1 += $segLen;
                } else {
                    $trailer .= $segData;
                    $length3 += $segLen;
                }
            } elseif ($type === 2) {
                // Binary segment
                $binary .= $segData;
                $length2 += $segLen;
            }
        }

        return [$ascii, $binary, $trailer, $length1, $length2, $length3];
    }

    /**
     * Parse PFA (Printer Font ASCII) format.
     *
     * PFA files are plain text. The binary segment is hex-encoded between
     * "eexec" and "cleartomark" (or 512 zeros).
     *
     * @return array{string, string, string, int, int, int}
     */
    private function parsePfa(string $data): array
    {
        // Find eexec marker — marks the boundary between ASCII and encrypted sections
        $eexecPos = strpos($data, 'eexec');
        if ($eexecPos === false) {
            throw new \RuntimeException('Invalid PFA: no eexec marker found');
        }

        // ASCII section includes the "eexec" keyword and trailing whitespace
        $afterEexec = $eexecPos + 5;
        // Skip one whitespace char after eexec
        if ($afterEexec < strlen($data) && ($data[$afterEexec] === "\n" || $data[$afterEexec] === "\r" || $data[$afterEexec] === ' ')) {
            $afterEexec++;
            if ($afterEexec < strlen($data) && $data[$afterEexec - 1] === "\r" && $data[$afterEexec] === "\n") {
                $afterEexec++;
            }
        }

        $asciiSegment = substr($data, 0, $afterEexec);

        // Find the cleartomark/zeros trailer
        $remaining = substr($data, $afterEexec);

        // The trailer starts with 512 zeros (hex "0" characters) or "cleartomark"
        $trailerPos = strrpos($remaining, 'cleartomark');
        if ($trailerPos !== false) {
            // Look for the start of the zeros block before cleartomark
            $zeroBlockStart = $trailerPos;
            // Search backwards for the first non-hex character block of zeros
            $searchBack = $trailerPos;
            while ($searchBack > 0) {
                $ch = $remaining[$searchBack - 1];
                if ($ch === '0' || $ch === "\n" || $ch === "\r" || $ch === ' ') {
                    $searchBack--;
                } else {
                    break;
                }
            }
            $hexPart = substr($remaining, 0, $searchBack);
            $trailerPart = substr($remaining, $searchBack);
        } else {
            // No cleartomark — look for the zero block (512 ASCII zeros)
            if (preg_match('/\n(0{512,})/', $remaining, $m, PREG_OFFSET_CAPTURE)) {
                $hexPart = substr($remaining, 0, $m[0][1]);
                $trailerPart = substr($remaining, $m[0][1]);
            } else {
                $hexPart = $remaining;
                $trailerPart = '';
            }
        }

        // Decode hex to binary
        $hexClean = preg_replace('/\s+/', '', $hexPart);
        $binarySegment = hex2bin($hexClean) ?: '';

        $length1 = strlen($asciiSegment);
        $length2 = strlen($binarySegment);
        $length3 = strlen($trailerPart);

        return [$asciiSegment, $binarySegment, $trailerPart, $length1, $length2, $length3];
    }

    /**
     * Parse font metrics from the ASCII header section.
     *
     * @return array<string, mixed>
     */
    private function parseAsciiHeader(string $ascii): array
    {
        $metrics = [
            'fontName' => 'Unknown',
            'familyName' => 'Unknown',
            'italicAngle' => 0.0,
            'isFixedPitch' => false,
            'fontBBox' => [0, 0, 0, 0],
            'ascent' => 0,
            'descent' => 0,
            'capHeight' => 0,
            'xHeight' => 0,
            'stemV' => 0,
            'underlinePosition' => 0,
            'underlineThickness' => 0,
        ];

        // /FontName
        if (preg_match('/\/FontName\s*\/(\S+)/', $ascii, $m)) {
            $metrics['fontName'] = $m[1];
        }

        // /FullName
        if (preg_match('/\/FullName\s*\(([^)]*)\)/', $ascii, $m)) {
            $metrics['familyName'] = $m[1];
        }
        // /FamilyName as fallback
        if ($metrics['familyName'] === 'Unknown' && preg_match('/\/FamilyName\s*\(([^)]*)\)/', $ascii, $m)) {
            $metrics['familyName'] = $m[1];
        }

        // /ItalicAngle
        if (preg_match('/\/ItalicAngle\s+([-\d.]+)/', $ascii, $m)) {
            $metrics['italicAngle'] = (float) $m[1];
        }

        // /isFixedPitch
        if (preg_match('/\/isFixedPitch\s+(true|false)/i', $ascii, $m)) {
            $metrics['isFixedPitch'] = strtolower($m[1]) === 'true';
        }

        // /FontBBox
        if (preg_match('/\/FontBBox\s*\{?\s*([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s*\}?/', $ascii, $m)) {
            $metrics['fontBBox'] = [(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]];
        }

        // /UnderlinePosition
        if (preg_match('/\/UnderlinePosition\s+([-\d.]+)/', $ascii, $m)) {
            $metrics['underlinePosition'] = (int) $m[1];
        }

        // /UnderlineThickness
        if (preg_match('/\/UnderlineThickness\s+([-\d.]+)/', $ascii, $m)) {
            $metrics['underlineThickness'] = (int) $m[1];
        }

        // Derive ascent/descent/capHeight from FontBBox
        $bbox = $metrics['fontBBox'];
        $metrics['ascent'] = $bbox[3] > 0 ? $bbox[3] : 800;
        $metrics['descent'] = $bbox[1] < 0 ? $bbox[1] : -200;
        // Estimate cap height as ~70% of ascent
        $metrics['capHeight'] = (int) ($metrics['ascent'] * 0.7);

        // Estimate stemV from font name
        $name = strtolower($metrics['fontName']);
        if (str_contains($name, 'bold') || str_contains($name, 'black') || str_contains($name, 'heavy')) {
            $metrics['stemV'] = 120;
        } elseif (str_contains($name, 'light') || str_contains($name, 'thin')) {
            $metrics['stemV'] = 50;
        } else {
            $metrics['stemV'] = 80;
        }

        return $metrics;
    }

    /**
     * Parse the Encoding array from the ASCII header.
     *
     * Type 1 fonts can define encoding as:
     *   - StandardEncoding (default reference)
     *   - ISOLatin1Encoding
     *   - A custom encoding with "dup N /glyphname put" entries
     *
     * @return array<int, string> byte => glyph name
     */
    private function parseEncoding(string $ascii): array
    {
        // Check for standard encoding reference
        if (preg_match('/\/Encoding\s+StandardEncoding\s+def/', $ascii)) {
            return StandardEncodingTable::getTable();
        }

        // Check for ISOLatin1Encoding (maps to WinAnsi-like)
        if (preg_match('/\/Encoding\s+ISOLatin1Encoding\s+def/', $ascii)) {
            return \ApprLabs\Encoding\WinAnsiTable::getTable();
        }

        // Parse custom encoding array
        // Format: /Encoding 256 array
        //         0 1 255 { 1 index exch /.notdef put } for
        //         dup N /glyphname put
        //         ...
        //         readonly def
        if (preg_match('/\/Encoding\s+(\d+)\s+array\b/s', $ascii, $m)) {
            // Start with all .notdef
            $encoding = array_fill(0, 256, '.notdef');

            // Find all "dup N /glyphname put" entries
            if (preg_match_all('/dup\s+(\d+)\s+\/(\S+)\s+put/', $ascii, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $code = (int) $match[1];
                    $glyph = $match[2];
                    if ($code >= 0 && $code <= 255) {
                        $encoding[$code] = $glyph;
                    }
                }
            }

            return $encoding;
        }

        // Default: use StandardEncoding
        return StandardEncodingTable::getTable();
    }

    /**
     * Parse CharString glyph names from the ASCII header.
     *
     * The charstrings section looks like:
     *   /CharStrings N dict dup begin
     *   /glyphname N RD ... ND
     *
     * We only extract the names, not the encrypted charstring data.
     *
     * @return list<string>
     */
    private function parseCharStringNames(string $ascii): array
    {
        $names = [];
        if (preg_match_all('/^\s*\/(\S+)\s+\d+\s+(?:RD|R|-|)\s/m', $ascii, $matches)) {
            foreach ($matches[1] as $name) {
                if ($name !== 'CharStrings' && $name !== 'Encoding' && $name !== 'FontName') {
                    $names[] = $name;
                }
            }
        }
        return $names;
    }

    /**
     * Parse glyph widths from the ASCII header.
     *
     * Looks for /Metrics or /CharMetrics dictionaries, or extracts widths
     * from the font's built-in data. Many Type 1 fonts don't expose widths
     * in the ASCII section (they're in the encrypted charstrings), so this
     * returns what it can find.
     *
     * @return array<string, int> glyph name => width in 1000 units/em
     */
    private function parseGlyphWidths(string $ascii): array
    {
        $widths = [];

        // Try /Metrics dictionary: /glyphname [wx wy] or /glyphname N
        if (preg_match('/\/Metrics\s+\d+\s+dict\s+(?:dup\s+)?begin\s+(.*?)(?:end|readonly)/s', $ascii, $metricsBlock)) {
            if (preg_match_all('/\/(\S+)\s+\[\s*([-\d.]+)/', $metricsBlock[1], $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $widths[$match[1]] = (int) round((float) $match[2]);
                }
            }
        }

        return $widths;
    }

    /**
     * Build PFB-format bytes from the three segments.
     *
     * For embedding in PDF, the font program must be in PFB-like format
     * (raw segments without the PFB headers, but with correct Length1/2/3).
     * PDF expects the concatenated raw segments without PFB segment markers.
     */
    private function buildPfbBytes(string $ascii, string $binary, string $trailer): string
    {
        return $ascii . $binary . $trailer;
    }

    /**
     * Build PDF font flags from parsed metrics.
     *
     * ISO 32000-2, Table 123:
     *   Bit 1: FixedPitch
     *   Bit 2: Serif (assume serif unless name says otherwise)
     *   Bit 3: Symbolic
     *   Bit 4: Script
     *   Bit 6: Nonsymbolic
     *   Bit 7: Italic
     */
    /** @param array<string, mixed> $metrics */
    private function buildFlags(array $metrics): int
    {
        $flags = 0;

        if ($metrics['isFixedPitch']) {
            $flags |= (1 << 0); // FixedPitch
        }

        // Assume non-symbolic (standard Latin encoding) unless font name indicates otherwise
        $name = strtolower($metrics['fontName']);
        if (str_contains($name, 'symbol') || str_contains($name, 'zapf') || str_contains($name, 'dingbat') || str_contains($name, 'wingding')) {
            $flags |= (1 << 2); // Symbolic
        } else {
            $flags |= (1 << 5); // Nonsymbolic
        }

        // Serif detection
        if (str_contains($name, 'sans') || str_contains($name, 'arial') || str_contains($name, 'helvetica') || str_contains($name, 'gothic') || str_contains($name, 'futura')) {
            // Sans-serif — don't set serif flag
        } else {
            $flags |= (1 << 1); // Serif
        }

        // Italic
        if ($metrics['italicAngle'] != 0.0 || str_contains($name, 'italic') || str_contains($name, 'oblique')) {
            $flags |= (1 << 6); // Italic
        }

        return $flags;
    }
}
