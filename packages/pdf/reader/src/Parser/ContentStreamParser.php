<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Parser;

/**
 * Parses decoded content stream data into a sequence of operations.
 *
 * Each operation is a list of operands followed by an operator keyword.
 * The parser handles all PDF content stream token types: numbers, names,
 * literal strings, hex strings, arrays, dictionaries, and inline images.
 */
final class ContentStreamParser
{
    /**
     * Parse content stream data into operations.
     *
     * @return list<ContentStreamOp>
     */
    public function parse(string $data): array
    {
        $ops = [];
        $operands = [];
        $len = strlen($data);
        $pos = 0;

        while ($pos < $len) {
            // Skip whitespace
            while ($pos < $len && $this->isWhitespace($data[$pos])) {
                $pos++;
            }
            if ($pos >= $len) {
                break;
            }

            $ch = $data[$pos];

            // Comment — skip to end of line
            if ($ch === '%') {
                while ($pos < $len && $data[$pos] !== "\n" && $data[$pos] !== "\r") {
                    $pos++;
                }
                continue;
            }

            // Literal string (...)
            if ($ch === '(') {
                $str = $this->readLiteralString($data, $pos);
                $operands[] = $str;
                continue;
            }

            // Hex string <...>
            if ($ch === '<') {
                // Could be hex string or dict start <<
                if ($pos + 1 < $len && $data[$pos + 1] === '<') {
                    // Inline dict — read until >>
                    $dictStr = $this->readInlineDict($data, $pos);
                    $operands[] = $dictStr;
                    continue;
                }
                $hex = $this->readHexString($data, $pos);
                $operands[] = $hex;
                continue;
            }

            // Array [...]
            if ($ch === '[') {
                $arr = $this->readArray($data, $pos);
                $operands[] = $arr;
                continue;
            }

            // Name /...
            if ($ch === '/') {
                $name = $this->readName($data, $pos);
                $operands[] = $name;
                continue;
            }

            // Number or keyword
            if ($this->isNumberStart($ch)) {
                $num = $this->readNumber($data, $pos);
                $operands[] = $num;
                continue;
            }

            // Keyword (operator or boolean/null)
            $keyword = $this->readKeyword($data, $pos);

            // Handle inline image: BI ... ID <data> EI
            if ($keyword === 'BI') {
                $ops[] = $this->readInlineImage($data, $pos, $operands);
                $operands = [];
                continue;
            }

            // true/false/null are operands, not operators
            if ($keyword === 'true' || $keyword === 'false' || $keyword === 'null') {
                $operands[] = $keyword;
                continue;
            }

            // Everything else is an operator
            $ops[] = new ContentStreamOp($operands, $keyword);
            $operands = [];
        }

        return $ops;
    }

    private function isWhitespace(string $ch): bool
    {
        return $ch === ' ' || $ch === "\n" || $ch === "\r"
            || $ch === "\t" || $ch === "\x00" || $ch === "\x0C";
    }

    private function isNumberStart(string $ch): bool
    {
        return ($ch >= '0' && $ch <= '9') || $ch === '+' || $ch === '-' || $ch === '.';
    }

    private function isDelimiter(string $ch): bool
    {
        return $ch === '(' || $ch === ')' || $ch === '<' || $ch === '>'
            || $ch === '[' || $ch === ']' || $ch === '/' || $ch === '%';
    }

    private function readLiteralString(string $data, int &$pos): string
    {
        $result = '(';
        $pos++; // skip opening (
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

        return $result . ')';
    }

    private function readHexString(string $data, int &$pos): string
    {
        $result = '<';
        $pos++; // skip <
        $len = strlen($data);

        while ($pos < $len && $data[$pos] !== '>') {
            $result .= $data[$pos];
            $pos++;
        }
        if ($pos < $len) {
            $pos++; // skip >
        }
        return $result . '>';
    }

    private function readName(string $data, int &$pos): string
    {
        $result = '/';
        $pos++; // skip /
        $len = strlen($data);

        while ($pos < $len) {
            $ch = $data[$pos];
            if ($this->isWhitespace($ch) || $this->isDelimiter($ch)) {
                break;
            }
            $result .= $ch;
            $pos++;
        }
        return $result;
    }

    private function readNumber(string $data, int &$pos): string
    {
        $result = '';
        $len = strlen($data);

        while ($pos < $len) {
            $ch = $data[$pos];
            if (($ch >= '0' && $ch <= '9') || $ch === '.' || $ch === '+' || $ch === '-') {
                $result .= $ch;
                $pos++;
            } else {
                break;
            }
        }
        return $result;
    }

    private function readKeyword(string $data, int &$pos): string
    {
        $result = '';
        $len = strlen($data);

        while ($pos < $len) {
            $ch = $data[$pos];
            if ($this->isWhitespace($ch) || $this->isDelimiter($ch)) {
                break;
            }
            $result .= $ch;
            $pos++;
        }
        return $result;
    }

    private function readArray(string $data, int &$pos): string
    {
        $result = '[';
        $pos++; // skip [
        $depth = 1;
        $len = strlen($data);

        while ($pos < $len && $depth > 0) {
            $ch = $data[$pos];
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $pos++;
                    break;
                }
            }
            // Handle nested strings
            if ($ch === '(') {
                $result .= $this->readLiteralString($data, $pos);
                continue;
            }
            if ($ch === '<' && $pos + 1 < $len && $data[$pos + 1] !== '<') {
                $result .= $this->readHexString($data, $pos);
                continue;
            }
            $result .= $ch;
            $pos++;
        }

        return $result . ']';
    }

    private function readInlineDict(string $data, int &$pos): string
    {
        $result = '<<';
        $pos += 2; // skip <<
        $len = strlen($data);

        while ($pos < $len) {
            if ($data[$pos] === '>' && $pos + 1 < $len && $data[$pos + 1] === '>') {
                $pos += 2;
                return $result . '>>';
            }
            if ($data[$pos] === '(') {
                $result .= $this->readLiteralString($data, $pos);
                continue;
            }
            $result .= $data[$pos];
            $pos++;
        }
        return $result . '>>';
    }

    /**
     * Read an inline image: operands already consumed.
     * We're positioned after "BI". Read key-value pairs until "ID",
     * then read raw image data until "EI".
     */
    private function readInlineImage(string $data, int &$pos, array $operands): ContentStreamOp
    {
        $len = strlen($data);

        // Skip whitespace after BI
        while ($pos < $len && $this->isWhitespace($data[$pos])) {
            $pos++;
        }

        // Read key-value pairs until ID keyword
        $params = '';
        while ($pos < $len) {
            // Check for ID keyword (must be followed by single whitespace byte)
            if ($data[$pos] === 'I' && $pos + 1 < $len && $data[$pos + 1] === 'D') {
                $pos += 2; // skip "ID"
                if ($pos < $len && ($data[$pos] === ' ' || $data[$pos] === "\n")) {
                    $pos++; // skip single whitespace after ID
                }
                break;
            }
            $params .= $data[$pos];
            $pos++;
        }

        // Read image data until EI
        $imageData = '';
        while ($pos < $len) {
            // Look for \nEI or \rEI or space+EI
            if ($pos + 2 <= $len
                && ($data[$pos] === "\n" || $data[$pos] === "\r" || $data[$pos] === ' ')
                && $data[$pos + 1] === 'E' && $data[$pos + 2] === 'I'
            ) {
                $pos += 3; // skip whitespace+EI
                break;
            }
            $imageData .= $data[$pos];
            $pos++;
        }

        return new ContentStreamOp(
            [trim($params), $imageData],
            'BI',
        );
    }
}
