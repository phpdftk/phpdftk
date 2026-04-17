<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader;

use ApprLabs\Encoding\CMapParser;
use ApprLabs\Encoding\GlyphList;
use ApprLabs\Encoding\WinAnsiTable;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Reader\Parser\ContentStreamOp;
use ApprLabs\Pdf\Reader\Parser\ContentStreamParser;

/**
 * Extracts text content from a PDF page by interpreting content
 * stream operators.
 *
 * Tracks text state (current font, position, spacing) and converts
 * character codes to Unicode using:
 *   1. /ToUnicode CMap (if present on the font)
 *   2. /Encoding + /Differences (if present)
 *   3. WinAnsi → GlyphList fallback (for standard fonts)
 *
 * Text positioning is used to insert spaces and newlines where the
 * PDF moves the text cursor by significant amounts.
 */
final class TextExtractor
{
    private readonly ContentStreamParser $parser;
    private readonly ObjectResolver $resolver;

    /** @var array<string, array<int, int>> Font name → char code → Unicode codepoint */
    private array $fontMaps = [];

    /** @var array<string, bool> Font names that use 2-byte CID encoding */
    private array $cidFonts = [];

    /** @var array<string, float> Font name → space character width in 1/1000 units */
    private array $fontSpaceWidths = [];

    /** Current font name (e.g., "F1") */
    private string $currentFont = '';

    /** Current font size */
    private float $fontSize = 12.0;

    /** Average character width for space detection (rough estimate) */
    private float $spaceWidth = 0.0;

    public function __construct(ObjectResolver $resolver)
    {
        $this->parser = new ContentStreamParser();
        $this->resolver = $resolver;
    }

    /**
     * Extract text from a page dictionary.
     *
     * @param PdfDictionary $page The page dictionary (must have /Contents and /Resources)
     */
    public function extractFromPage(PdfDictionary $page): string
    {
        // Pre-load font maps from page resources
        $this->loadFontMaps($page);

        // Get content stream data
        $data = $this->getContentStreamData($page);
        if ($data === '') {
            return '';
        }

        // Parse content stream into operations
        $ops = $this->parser->parse($data);

        // Walk operations and extract text
        return $this->processOps($ops);
    }

    /**
     * Process a list of content stream operations and extract text.
     *
     * @param list<ContentStreamOp> $ops
     */
    private function processOps(array $ops): string
    {
        $text = '';
        $inTextBlock = false;
        $lastX = 0.0;
        $lastY = 0.0;
        $currentX = 0.0;
        $currentY = 0.0;

        /** @var list<array{actualText: string|null}> */
        $markedContentStack = [];
        $suppressText = false;

        foreach ($ops as $op) {
            switch ($op->operator) {
                case 'BT':
                    $inTextBlock = true;
                    $currentX = 0.0;
                    $currentY = 0.0;
                    break;

                case 'ET':
                    $inTextBlock = false;
                    break;

                case 'Tf':
                    // Set font: /FontName fontSize Tf
                    if (count($op->operands) >= 2) {
                        $this->currentFont = ltrim($op->operands[0], '/');
                        $this->fontSize = (float) $op->operands[1];
                        // Use font-specific space width if available, else estimate
                        $fontSpaceW = $this->fontSpaceWidths[$this->currentFont] ?? 0;
                        $this->spaceWidth = $fontSpaceW > 0
                            ? $fontSpaceW * $this->fontSize / 1000
                            : $this->fontSize * 0.25;
                    }
                    break;

                case 'Td':
                    // Move text position: tx ty Td
                    if (count($op->operands) >= 2) {
                        $tx = (float) $op->operands[0];
                        $ty = (float) $op->operands[1];
                        $lastX = $currentX;
                        $lastY = $currentY;
                        $currentX += $tx;
                        $currentY += $ty;
                        $text .= $this->inferSpacing($tx, $ty, $lastY, $currentY);
                    }
                    break;

                case 'TD':
                    // Move text position and set leading: tx ty TD
                    if (count($op->operands) >= 2) {
                        $tx = (float) $op->operands[0];
                        $ty = (float) $op->operands[1];
                        $lastX = $currentX;
                        $lastY = $currentY;
                        $currentX += $tx;
                        $currentY += $ty;
                        $text .= $this->inferSpacing($tx, $ty, $lastY, $currentY);
                    }
                    break;

                case 'Tm':
                    // Set text matrix: a b c d e f Tm
                    if (count($op->operands) >= 6) {
                        $newX = (float) $op->operands[4];
                        $newY = (float) $op->operands[5];
                        if ($text !== '' && abs($newY - $currentY) > $this->fontSize * 0.5) {
                            $text .= "\n";
                        } elseif ($text !== '' && abs($newX - $currentX) > $this->spaceWidth) {
                            $text .= ' ';
                        }
                        $currentX = $newX;
                        $currentY = $newY;
                    }
                    break;

                case 'T*':
                    // Move to start of next line
                    if ($text !== '') {
                        $text .= "\n";
                    }
                    break;

                case 'BMC':
                    // Begin Marked Content (no properties) — push null entry
                    $markedContentStack[] = ['actualText' => null];
                    break;

                case 'BDC':
                    // Begin Marked Content with Properties
                    $actualText = null;
                    if (count($op->operands) >= 2) {
                        $actualText = $this->extractActualText($op->operands[1]);
                    }
                    $markedContentStack[] = ['actualText' => $actualText];
                    if ($actualText !== null) {
                        $suppressText = true;
                    }
                    break;

                case 'EMC':
                    // End Marked Content
                    if (!empty($markedContentStack)) {
                        $entry = array_pop($markedContentStack);
                        if ($entry['actualText'] !== null) {
                            $text .= $entry['actualText'];
                            // Only clear suppress if no other ActualText entry remains on the stack
                            $suppressText = false;
                            foreach ($markedContentStack as $stackEntry) {
                                if ($stackEntry['actualText'] !== null) {
                                    $suppressText = true;
                                    break;
                                }
                            }
                        }
                    }
                    break;

                case 'Tj':
                    // Show text string: (string) Tj
                    if (!$suppressText && count($op->operands) >= 1) {
                        $text .= $this->decodeStringOperand($op->operands[0]);
                    }
                    break;

                case 'TJ':
                    // Show text with individual glyph positioning: [...] TJ
                    if (!$suppressText && count($op->operands) >= 1) {
                        $text .= $this->decodeTJArray($op->operands[0]);
                    }
                    break;

                case "'":
                    // Move to next line and show text: (string) '
                    if ($text !== '') {
                        $text .= "\n";
                    }
                    if (!$suppressText && count($op->operands) >= 1) {
                        $text .= $this->decodeStringOperand($op->operands[0]);
                    }
                    break;

                case '"':
                    // Set word/char spacing, move to next line, show text: aw ac (string) "
                    if ($text !== '') {
                        $text .= "\n";
                    }
                    if (!$suppressText && count($op->operands) >= 3) {
                        $text .= $this->decodeStringOperand($op->operands[2]);
                    }
                    break;
            }
        }

        return trim($text);
    }

    /**
     * Infer whether a text position move implies a space or newline.
     */
    private function inferSpacing(float $tx, float $ty, float $lastY, float $currentY): string
    {
        // Vertical movement larger than half the font size → newline
        if (abs($ty) > $this->fontSize * 0.5) {
            return "\n";
        }
        // Horizontal movement → space (if significant)
        if (abs($tx) > $this->spaceWidth && $tx > 0) {
            return ' ';
        }
        return '';
    }

    /**
     * Extract /ActualText value from a BDC properties operand.
     *
     * The operand is either an inline dict like "<< /ActualText (text) /MCID 0 >>"
     * or a name reference. Only inline dicts with /ActualText are handled.
     */
    private function extractActualText(string $operand): ?string
    {
        $operand = trim($operand);

        // Only parse inline dictionaries
        if (!str_starts_with($operand, '<<')) {
            return null;
        }

        // Try literal string: /ActualText (...)
        if (preg_match('/\/ActualText\s+\(/', $operand, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = (int) $matches[0][1];
            // Find the opening paren
            $parenPos = strpos($operand, '(', $startPos);
            if ($parenPos !== false) {
                $pos = $parenPos;
                $str = $this->extractLiteralString($operand, $pos);
                return $this->unescapeLiteralString($str);
            }
        }

        // Try hex string: /ActualText <hex>
        if (preg_match('/\/ActualText\s+<([0-9A-Fa-f\s]+)>/', $operand, $matches)) {
            $hex = preg_replace('/\s+/', '', $matches[1]) ?? $matches[1];
            $bytes = hex2bin($hex);
            return $bytes !== false ? $bytes : null;
        }

        return null;
    }

    /**
     * Decode a string operand from a Tj or ' or " operator.
     *
     * The operand is in raw PDF syntax: "(escaped text)" or "<hex>".
     */
    private function decodeStringOperand(string $operand): string
    {
        $bytes = $this->parseStringOperand($operand);
        return $this->mapBytesToUnicode($bytes);
    }

    /**
     * Decode a TJ array operand: [<hex> -80 (text) 40 ...]
     *
     * String elements produce text. Numeric elements adjust positioning;
     * large negative values (< -100) are treated as word spaces.
     */
    private function decodeTJArray(string $arrayStr): string
    {
        $result = '';
        $arrayStr = trim($arrayStr, '[] ');
        $len = strlen($arrayStr);
        $pos = 0;

        while ($pos < $len) {
            // Skip whitespace
            while ($pos < $len && ($arrayStr[$pos] === ' ' || $arrayStr[$pos] === "\n"
                || $arrayStr[$pos] === "\r" || $arrayStr[$pos] === "\t")) {
                $pos++;
            }
            if ($pos >= $len) {
                break;
            }

            $ch = $arrayStr[$pos];

            if ($ch === '(') {
                // Literal string
                $str = $this->extractLiteralString($arrayStr, $pos);
                $bytes = $this->unescapeLiteralString($str);
                $result .= $this->mapBytesToUnicode($bytes);
            } elseif ($ch === '<') {
                // Hex string
                $hex = $this->extractHexString($arrayStr, $pos);
                $bytes = hex2bin($hex) ?: '';
                $result .= $this->mapBytesToUnicode($bytes);
            } elseif ($ch === '-' || $ch === '+' || $ch === '.' || ($ch >= '0' && $ch <= '9')) {
                // Number — large negative = space
                $numStr = '';
                while ($pos < $len && ($arrayStr[$pos] === '-' || $arrayStr[$pos] === '+'
                    || $arrayStr[$pos] === '.' || ($arrayStr[$pos] >= '0' && $arrayStr[$pos] <= '9'))) {
                    $numStr .= $arrayStr[$pos];
                    $pos++;
                }
                $num = (float) $numStr;
                if ($num < -100) {
                    $result .= ' ';
                }
            } else {
                $pos++;
            }
        }

        return $result;
    }

    /**
     * Parse a PDF string operand into raw bytes.
     */
    private function parseStringOperand(string $operand): string
    {
        $operand = trim($operand);

        if (str_starts_with($operand, '<') && str_ends_with($operand, '>')) {
            // Hex string
            $hex = substr($operand, 1, -1);
            $hex = preg_replace('/\s+/', '', $hex) ?? $hex;
            return hex2bin($hex) ?: '';
        }

        if (str_starts_with($operand, '(') && str_ends_with($operand, ')')) {
            $inner = substr($operand, 1, -1);
            return $this->unescapeLiteralString($inner);
        }

        return $operand;
    }

    /**
     * Unescape a PDF literal string (content between outer parens).
     */
    private function unescapeLiteralString(string $str): string
    {
        $result = '';
        $len = strlen($str);
        $i = 0;

        while ($i < $len) {
            $ch = $str[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $i++;
                $next = $str[$i];
                $result .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0C",
                    '(' => '(',
                    ')' => ')',
                    '\\' => '\\',
                    default => $this->readOctalOrLiteral($str, $i, $next),
                };
            } else {
                $result .= $ch;
            }
            $i++;
        }

        return $result;
    }

    private function readOctalOrLiteral(string $str, int &$i, string $ch): string
    {
        if ($ch >= '0' && $ch <= '7') {
            $octal = $ch;
            $len = strlen($str);
            for ($j = 0; $j < 2 && $i + 1 < $len; $j++) {
                $next = $str[$i + 1];
                if ($next >= '0' && $next <= '7') {
                    $octal .= $next;
                    $i++;
                } else {
                    break;
                }
            }
            return chr((int) octdec($octal));
        }
        return $ch;
    }

    /**
     * Map raw bytes to Unicode string using the current font's encoding.
     *
     * If the raw bytes contain valid multi-byte UTF-8 sequences, they
     * are passed through directly. This handles the common case where
     * PDF producers embed UTF-8 in content streams regardless of the
     * declared encoding (technically non-conforming, but widespread).
     */
    private function mapBytesToUnicode(string $bytes): string
    {
        // If bytes contain multi-byte UTF-8 sequences, pass through
        // directly regardless of font encoding. This handles PDFs that
        // embed UTF-8 in content streams (FPDF, many other producers).
        if ($this->containsMultibyte($bytes) && mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }

        $fontMap = $this->fontMaps[$this->currentFont] ?? null;
        $isCid = $this->cidFonts[$this->currentFont] ?? false;

        if ($fontMap !== null && $isCid) {
            // CID font: process bytes in pairs (2-byte GID codes)
            $result = '';
            $len = strlen($bytes);
            for ($i = 0; $i + 1 < $len; $i += 2) {
                $code = (ord($bytes[$i]) << 8) | ord($bytes[$i + 1]);
                if (isset($fontMap[$code])) {
                    $result .= mb_chr($fontMap[$code], 'UTF-8');
                } else {
                    $result .= "\u{FFFD}"; // replacement character
                }
            }
            return $result;
        }

        if ($fontMap !== null) {
            $result = '';
            $len = strlen($bytes);
            for ($i = 0; $i < $len; $i++) {
                $code = ord($bytes[$i]);
                if (isset($fontMap[$code])) {
                    $result .= mb_chr($fontMap[$code], 'UTF-8');
                } else {
                    // Unmapped — use raw byte as Latin-1
                    $result .= mb_chr($code, 'UTF-8');
                }
            }
            return $result;
        }

        return $this->winAnsiFallback($bytes);
    }

    /**
     * Check if a string contains any multi-byte UTF-8 sequences.
     */
    private function containsMultibyte(string $bytes): bool
    {
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            if (ord($bytes[$i]) > 127) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fallback: convert bytes using WinAnsi encoding → GlyphList → Unicode.
     */
    private function winAnsiFallback(string $bytes): string
    {
        static $winAnsi = null;
        static $glyphList = null;
        if ($winAnsi === null) {
            $winAnsi = WinAnsiTable::getTable();
            $glyphList = GlyphList::getList();
        }

        $result = '';
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($bytes[$i]);
            $glyphName = $winAnsi[$code] ?? null;
            if ($glyphName !== null && isset($glyphList[$glyphName])) {
                $result .= mb_chr($glyphList[$glyphName], 'UTF-8');
            } else {
                // Direct byte → character
                $result .= mb_chr($code, 'UTF-8');
            }
        }
        return $result;
    }

    /**
     * Load font-to-Unicode mappings from the page's /Resources/Font dictionary.
     */
    private function loadFontMaps(PdfDictionary $page): void
    {
        $this->fontMaps = [];
        $this->cidFonts = [];
        $this->fontSpaceWidths = [];

        $resources = $this->resolveValue($page->get('Resources'));
        if (!$resources instanceof PdfDictionary) {
            return;
        }

        $fonts = $this->resolveValue($resources->get('Font'));
        if (!$fonts instanceof PdfDictionary) {
            return;
        }

        $cmapParser = new CMapParser();

        foreach ($fonts->entries as $fontName => $fontRef) {
            $fontDict = $this->resolveValue($fontRef);
            if (!$fontDict instanceof PdfDictionary) {
                continue;
            }

            // Detect CID/Type0 fonts (use 2-byte character codes)
            $subtype = $fontDict->get('Subtype');
            if ($subtype instanceof PdfName && $subtype->value === 'Type0') {
                $this->cidFonts[$fontName] = true;
            }

            // Extract space width from font metrics
            $this->extractSpaceWidth($fontName, $fontDict);

            // Try ToUnicode CMap first
            $toUnicode = $fontDict->get('ToUnicode');
            if ($toUnicode !== null) {
                $toUnicodeStream = $this->resolveValue($toUnicode);
                if ($toUnicodeStream instanceof PdfStream && $toUnicodeStream->data !== '') {
                    $map = $cmapParser->parse($toUnicodeStream->data);
                    if (!empty($map)) {
                        $this->fontMaps[$fontName] = $map;
                        continue;
                    }
                }
            }

            // Try /Encoding with /Differences
            $encoding = $fontDict->get('Encoding');
            if ($encoding !== null) {
                $map = $this->buildEncodingMap($encoding);
                if (!empty($map)) {
                    $this->fontMaps[$fontName] = $map;
                    continue;
                }
            }
        }
    }

    /**
     * Extract the space character width from font metrics.
     */
    private function extractSpaceWidth(string $fontName, PdfDictionary $fontDict): void
    {
        // Try /Widths array (simple fonts)
        $widths = $fontDict->get('Widths');
        $firstChar = $fontDict->get('FirstChar');
        if ($widths instanceof PdfArray && $firstChar instanceof PdfNumber) {
            $fc = (int) $firstChar->toPdf();
            $spaceIndex = 32 - $fc; // space = char code 32
            if ($spaceIndex >= 0 && isset($widths->items[$spaceIndex])) {
                $w = $widths->items[$spaceIndex];
                if ($w instanceof PdfNumber) {
                    $this->fontSpaceWidths[$fontName] = (float) $w->toPdf();
                    return;
                }
            }
        }

        // Try /DW (default width for CID fonts) as fallback
        $dw = $fontDict->get('DW');
        if ($dw instanceof PdfNumber) {
            $this->fontSpaceWidths[$fontName] = (float) $dw->toPdf();
        }
    }

    /**
     * Build a character map from an /Encoding entry.
     *
     * @return array<int, int> char code → Unicode codepoint
     */
    private function buildEncodingMap(mixed $encoding): array
    {
        $glyphList = GlyphList::getList();
        $map = [];

        if ($encoding instanceof PdfName) {
            // Named encoding (e.g., /WinAnsiEncoding)
            if ($encoding->value === 'WinAnsiEncoding') {
                $winAnsi = WinAnsiTable::getTable();
                foreach ($winAnsi as $code => $glyphName) {
                    if (isset($glyphList[$glyphName])) {
                        $map[$code] = $glyphList[$glyphName];
                    }
                }
            }
            return $map;
        }

        $encodingDict = $this->resolveValue($encoding);
        if (!$encodingDict instanceof PdfDictionary) {
            return $map;
        }

        // Start with base encoding
        $baseEnc = $encodingDict->get('BaseEncoding');
        if ($baseEnc instanceof PdfName && $baseEnc->value === 'WinAnsiEncoding') {
            $winAnsi = WinAnsiTable::getTable();
            foreach ($winAnsi as $code => $glyphName) {
                if (isset($glyphList[$glyphName])) {
                    $map[$code] = $glyphList[$glyphName];
                }
            }
        }

        // Apply /Differences
        $diffs = $encodingDict->get('Differences');
        if ($diffs instanceof PdfArray) {
            $code = 0;
            foreach ($diffs->items as $item) {
                if ($item instanceof PdfNumber) {
                    $code = (int) $item->toPdf();
                } elseif ($item instanceof PdfName) {
                    if (isset($glyphList[$item->value])) {
                        $map[$code] = $glyphList[$item->value];
                    }
                    $code++;
                }
            }
        }

        return $map;
    }

    /**
     * Resolve a value that might be a PdfReference.
     */
    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof PdfReference) {
            return $this->resolver->resolveReference($value);
        }
        return $value;
    }

    /**
     * Get the concatenated content stream data from a page.
     */
    private function getContentStreamData(PdfDictionary $page): string
    {
        $contents = $page->get('Contents');
        if ($contents === null) {
            return '';
        }

        if ($contents instanceof PdfReference) {
            $obj = $this->resolver->resolveReference($contents);
            if ($obj instanceof PdfStream) {
                return $obj->data;
            }
            // Could be an array of content stream refs
            if ($obj instanceof PdfArray) {
                $contents = $obj;
            } else {
                return '';
            }
        }

        if ($contents instanceof PdfArray) {
            $data = '';
            foreach ($contents->items as $ref) {
                if ($ref instanceof PdfReference) {
                    $stream = $this->resolver->resolveReference($ref);
                    if ($stream instanceof PdfStream) {
                        $data .= $stream->data . "\n";
                    }
                }
            }
            return $data;
        }

        return '';
    }

    private function extractLiteralString(string $data, int &$pos): string
    {
        $pos++; // skip (
        $result = '';
        $depth = 1;
        $len = strlen($data);

        while ($pos < $len && $depth > 0) {
            $ch = $data[$pos];
            if ($ch === '(') {
                $depth++;
                $result .= '(';
            } elseif ($ch === ')') {
                $depth--;
                if ($depth > 0) {
                    $result .= ')';
                }
            } elseif ($ch === '\\') {
                $result .= '\\';
                $pos++;
                if ($pos < $len) {
                    $result .= $data[$pos];
                }
            } else {
                $result .= $ch;
            }
            $pos++;
        }

        return $result;
    }

    private function extractHexString(string $data, int &$pos): string
    {
        $pos++; // skip <
        $hex = '';
        $len = strlen($data);

        while ($pos < $len && $data[$pos] !== '>') {
            if (!ctype_space($data[$pos])) {
                $hex .= $data[$pos];
            }
            $pos++;
        }
        if ($pos < $len) {
            $pos++; // skip >
        }
        return $hex;
    }
}
