<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tokenizer;

use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;

/**
 * PDF tokenizer — converts a byte stream into a sequence of typed tokens.
 *
 * Handles all PDF syntax per ISO 32000-2 §7.2–7.3: whitespace, comments,
 * names (with `#XX` escaping), literal strings (balanced parens,
 * backslash escapes, octal), hex strings, integers, reals, booleans,
 * null, delimiters (`[`, `]`, `<<`, `>>`), and keywords (`obj`,
 * `endobj`, `stream`, `endstream`, `R`, `xref`, `trailer`, `startxref`).
 */
final class Tokenizer
{
    private ?Token $peeked = null;

    public function __construct(private readonly Source $source)
    {
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function nextToken(): Token
    {
        if ($this->peeked !== null) {
            $token = $this->peeked;
            $this->peeked = null;
            return $token;
        }
        return $this->readToken();
    }

    public function peek(): Token
    {
        if ($this->peeked === null) {
            $this->peeked = $this->readToken();
        }
        return $this->peeked;
    }

    public function seek(int $offset): void
    {
        $this->peeked = null;
        $this->source->seek($offset);
    }

    public function tell(): int
    {
        if ($this->peeked !== null) {
            return $this->peeked->offset;
        }
        return $this->source->tell();
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    private function readToken(): Token
    {
        $this->skipWhitespaceAndComments();

        if ($this->source->isEof()) {
            return new Token(TokenType::Eof, '', $this->source->tell());
        }

        $offset = $this->source->tell();
        $byte = $this->source->readByte();
        if ($byte === null) {
            return new Token(TokenType::Eof, '', $offset);
        }

        return match ($byte) {
            '/'     => $this->readName($offset),
            '('     => $this->readLiteralString($offset),
            '<'     => $this->readAngleBracketToken($offset),
            '>'     => $this->readDictEnd($offset),
            '['     => new Token(TokenType::ArrayStart, '[', $offset),
            ']'     => new Token(TokenType::ArrayEnd, ']', $offset),
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'
                    => $this->readNumber($byte, $offset),
            '+', '-' => $this->readNumber($byte, $offset),
            '.'     => $this->readNumber($byte, $offset),
            default => $this->readKeyword($byte, $offset),
        };
    }

    private function skipWhitespaceAndComments(): void
    {
        while (!$this->source->isEof()) {
            $byte = $this->source->peek();
            if ($byte === '') {
                return;
            }

            // PDF whitespace: NUL, HT, LF, FF, CR, SP
            if ($byte === "\x00" || $byte === "\x09" || $byte === "\x0A"
                || $byte === "\x0C" || $byte === "\x0D" || $byte === "\x20") {
                $this->source->readByte();
                continue;
            }

            // Comment: skip to end of line
            if ($byte === '%') {
                $this->source->readByte();
                while (!$this->source->isEof()) {
                    $c = $this->source->readByte();
                    if ($c === "\x0A" || $c === "\x0D") {
                        break;
                    }
                }
                continue;
            }

            return;
        }
    }

    private function readName(int $offset): Token
    {
        $name = '';
        while (!$this->source->isEof()) {
            $byte = $this->source->peek();
            if ($byte === '' || $this->isDelimiterOrWhitespace($byte)) {
                break;
            }
            $this->source->readByte();
            if ($byte === '#') {
                // #XX hex escape
                $hex = $this->source->read(2);
                if (strlen($hex) === 2) {
                    $name .= chr((int) hexdec($hex));
                }
            } else {
                $name .= $byte;
            }
        }
        return new Token(TokenType::Name, $name, $offset);
    }

    private function readLiteralString(int $offset): Token
    {
        $result = '';
        $depth = 1;
        while ($depth > 0 && !$this->source->isEof()) {
            $byte = $this->source->readByte();
            if ($byte === null) {
                break;
            }

            if ($byte === '(') {
                $depth++;
                $result .= '(';
            } elseif ($byte === ')') {
                $depth--;
                if ($depth > 0) {
                    $result .= ')';
                }
            } elseif ($byte === '\\') {
                $result .= $this->readEscapeSequence();
            } else {
                $result .= $byte;
            }
        }
        return new Token(TokenType::LiteralString, $result, $offset);
    }

    private function readEscapeSequence(): string
    {
        $next = $this->source->readByte();
        if ($next === null) {
            return '';
        }
        return match ($next) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'b' => "\x08",
            'f' => "\x0C",
            '(' => '(',
            ')' => ')',
            '\\' => '\\',
            "\r" => $this->handleLineContinuation(),
            "\n" => '',  // line continuation
            default => $this->readOctalOrLiteral($next),
        };
    }

    private function handleLineContinuation(): string
    {
        // \r\n is a single line continuation
        if ($this->source->peek() === "\n") {
            $this->source->readByte();
        }
        return '';
    }

    private function readOctalOrLiteral(string $firstChar): string
    {
        if ($firstChar >= '0' && $firstChar <= '7') {
            $octal = $firstChar;
            for ($i = 0; $i < 2; $i++) {
                $next = $this->source->peek();
                if ($next >= '0' && $next <= '7') {
                    $octal .= $this->source->readByte();
                } else {
                    break;
                }
            }
            return chr((int) octdec($octal));
        }
        // Unknown escape: the spec says the backslash is ignored
        return $firstChar;
    }

    private function readAngleBracketToken(int $offset): Token
    {
        $next = $this->source->peek();
        if ($next === '<') {
            $this->source->readByte();
            return new Token(TokenType::DictStart, '<<', $offset);
        }
        return $this->readHexString($offset);
    }

    private function readHexString(int $offset): Token
    {
        $hex = '';
        while (!$this->source->isEof()) {
            $byte = $this->source->readByte();
            if ($byte === null || $byte === '>') {
                break;
            }
            // Skip whitespace inside hex strings
            if ($byte === "\x00" || $byte === "\x09" || $byte === "\x0A"
                || $byte === "\x0C" || $byte === "\x0D" || $byte === "\x20") {
                continue;
            }
            $hex .= $byte;
        }
        // Odd length: append trailing 0
        if (strlen($hex) % 2 !== 0) {
            $hex .= '0';
        }
        $decoded = hex2bin($hex);
        return new Token(TokenType::HexString, $decoded === false ? '' : $decoded, $offset);
    }

    private function readDictEnd(int $offset): Token
    {
        $next = $this->source->peek();
        if ($next === '>') {
            $this->source->readByte();
            return new Token(TokenType::DictEnd, '>>', $offset);
        }
        throw new InvalidPdfException("Unexpected '>' at offset $offset without matching '>>'");
    }

    private function readNumber(string $first, int $offset): Token
    {
        $num = $first;
        $isReal = ($first === '.');
        while (!$this->source->isEof()) {
            $byte = $this->source->peek();
            if ($byte >= '0' && $byte <= '9') {
                $num .= $this->source->readByte();
            } elseif ($byte === '.' && !$isReal) {
                $isReal = true;
                $num .= $this->source->readByte();
            } else {
                break;
            }
        }
        return new Token(
            $isReal ? TokenType::Real : TokenType::Integer,
            $num,
            $offset
        );
    }

    private function readKeyword(string $first, int $offset): Token
    {
        $word = $first;
        while (!$this->source->isEof()) {
            $byte = $this->source->peek();
            if ($byte === '' || $this->isDelimiterOrWhitespace($byte)) {
                break;
            }
            $word .= $this->source->readByte();
        }
        $type = match ($word) {
            'true', 'false' => TokenType::Boolean,
            'null'          => TokenType::Null,
            'obj'           => TokenType::ObjKeyword,
            'endobj'        => TokenType::EndObjKeyword,
            'stream'        => TokenType::StreamKeyword,
            'endstream'     => TokenType::EndStreamKeyword,
            'R'             => TokenType::RKeyword,
            'xref'          => TokenType::XrefKeyword,
            'trailer'       => TokenType::TrailerKeyword,
            'startxref'     => TokenType::StartXrefKeyword,
            default         => throw new InvalidPdfException("Unknown keyword '$word' at offset $offset"),
        };
        return new Token($type, $word, $offset);
    }

    private function isDelimiterOrWhitespace(string $byte): bool
    {
        return match ($byte) {
            // Whitespace
            "\x00", "\x09", "\x0A", "\x0C", "\x0D", "\x20",
            // Delimiters
            '(', ')', '<', '>', '[', ']', '{', '}', '/', '%'
                => true,
            default => false,
        };
    }
}
