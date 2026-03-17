<?php declare(strict_types=1);
namespace Phpdftk\Encoding;

final class CMapParser {
    /**
     * Parse a PDF CMap stream and return character code to Unicode codepoint mapping.
     *
     * @return array<int, int> character code => Unicode codepoint
     */
    public function parse(string $cmapStream): array {
        $result = [];

        // Parse beginbfchar/endbfchar sections
        if (preg_match_all('/beginbfchar\s+(.*?)\s+endbfchar/s', $cmapStream, $matches)) {
            foreach ($matches[1] as $section) {
                $lines = preg_split('/\r?\n/', trim($section));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    // Format: <srcCode> <dstCode>
                    if (preg_match('/^<([0-9A-Fa-f]+)>\s+<([0-9A-Fa-f]+)>/', $line, $m)) {
                        $srcCode  = hexdec($m[1]);
                        $dstCode  = hexdec($m[2]);
                        $result[(int)$srcCode] = (int)$dstCode;
                    }
                }
            }
        }

        // Parse beginbfrange/endbfrange sections
        if (preg_match_all('/beginbfrange\s+(.*?)\s+endbfrange/s', $cmapStream, $matches)) {
            foreach ($matches[1] as $section) {
                $lines = preg_split('/\r?\n/', trim($section));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    // Format: <startCode> <endCode> <startDst>
                    if (preg_match('/^<([0-9A-Fa-f]+)>\s+<([0-9A-Fa-f]+)>\s+<([0-9A-Fa-f]+)>/', $line, $m)) {
                        $startCode = (int)hexdec($m[1]);
                        $endCode   = (int)hexdec($m[2]);
                        $startDst  = (int)hexdec($m[3]);
                        for ($code = $startCode; $code <= $endCode; $code++) {
                            $result[$code] = $startDst + ($code - $startCode);
                        }
                    }
                    // Format: <startCode> <endCode> [<dst1> <dst2> ...]
                    elseif (preg_match('/^<([0-9A-Fa-f]+)>\s+<([0-9A-Fa-f]+)>\s+\[(.+)\]/', $line, $m)) {
                        $startCode = (int)hexdec($m[1]);
                        $endCode   = (int)hexdec($m[2]);
                        preg_match_all('/<([0-9A-Fa-f]+)>/', $m[3], $dstMatches);
                        $dsts = $dstMatches[1];
                        $idx = 0;
                        for ($code = $startCode; $code <= $endCode; $code++) {
                            if (isset($dsts[$idx])) {
                                $result[$code] = (int)hexdec($dsts[$idx]);
                            }
                            $idx++;
                        }
                    }
                }
            }
        }

        return $result;
    }
}
