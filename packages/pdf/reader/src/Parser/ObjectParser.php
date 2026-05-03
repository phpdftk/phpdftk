<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNull;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Reader\Exception\InvalidPdfException;
use Phpdftk\Pdf\Reader\Tokenizer\Source;
use Phpdftk\Pdf\Reader\Tokenizer\Token;
use Phpdftk\Pdf\Reader\Tokenizer\Tokenizer;
use Phpdftk\Pdf\Reader\Tokenizer\TokenType;

/**
 * Recursive-descent PDF object parser.
 *
 * Consumes tokens from a {@see Tokenizer} and builds the core
 * `PdfDictionary`, `PdfArray`, `PdfName`, `PdfString`, `PdfNumber`,
 * `PdfBoolean`, `PdfNull`, `PdfReference`, and `PdfStream` instances.
 */
final class ObjectParser
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly Source $source,
    ) {
    }

    /**
     * Parse any PDF value.
     */
    public function parseValue(): Serializable
    {
        $token = $this->tokenizer->nextToken();
        return $this->parseTokenValue($token);
    }

    /**
     * Parse a complete indirect object: `X Y obj <value> endobj`.
     *
     * @return array{int, int, Serializable} [objNum, genNum, value]
     */
    public function parseIndirectObject(): array
    {
        $objNumToken = $this->tokenizer->nextToken();
        $this->expect($objNumToken, TokenType::Integer, 'object number');

        $genNumToken = $this->tokenizer->nextToken();
        $this->expect($genNumToken, TokenType::Integer, 'generation number');

        $objToken = $this->tokenizer->nextToken();
        $this->expect($objToken, TokenType::ObjKeyword, 'obj keyword');

        $value = $this->parseValue();

        // After the value, expect `endobj` — but if the value was a dict
        // that was followed by `stream`, it became a PdfStream and we
        // should now see `endobj`.
        $end = $this->tokenizer->nextToken();
        if ($end->type !== TokenType::EndObjKeyword) {
            // Tolerant: some generators put extra data between the value
            // and endobj. Try skipping up to 5 tokens to find endobj.
            if ($end->type !== TokenType::Eof) {
                $found = false;
                for ($skip = 0; $skip < 5; $skip++) {
                    $retry = $this->tokenizer->nextToken();
                    if ($retry->type === TokenType::EndObjKeyword || $retry->type === TokenType::Eof) {
                        $found = true;
                        break;
                    }
                }
                // If we still can't find endobj, just continue — the object
                // value is already parsed. The tokenizer position may be
                // slightly off but the xref table will resync for the next object.
            }
        }

        return [(int) $objNumToken->value, (int) $genNumToken->value, $value];
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    private function parseTokenValue(Token $token): Serializable
    {
        return match ($token->type) {
            TokenType::DictStart      => $this->parseDictionaryOrStream(),
            TokenType::ArrayStart     => $this->parseArray(),
            TokenType::Name           => new PdfName($token->value),
            TokenType::LiteralString  => new PdfString($token->value),
            TokenType::HexString      => new PdfString($token->value, hex: true),
            TokenType::Integer        => $this->parseIntegerOrReference($token),
            TokenType::Real           => new PdfNumber((float) $token->value),
            TokenType::Boolean        => new PdfBoolean($token->value === 'true'),
            TokenType::Null           => new PdfNull(),
            // Unknown keywords: skip and try the next token
            TokenType::Unknown        => $this->parseValue(),
            default                   => throw new InvalidPdfException(
                "Unexpected token {$token->type->name} ('{$token->value}') at offset {$token->offset}"
            ),
        };
    }

    /**
     * After reading an integer, look ahead for `<int> R` (indirect
     * reference) or just return the integer.
     */
    private function parseIntegerOrReference(Token $intToken): Serializable
    {
        $savedPos = $this->tokenizer->tell();
        $next = $this->tokenizer->peek();

        if ($next->type === TokenType::Integer) {
            $this->tokenizer->nextToken(); // consume the gen number
            $rToken = $this->tokenizer->peek();
            if ($rToken->type === TokenType::RKeyword) {
                $this->tokenizer->nextToken(); // consume R
                return new PdfReference((int) $intToken->value, (int) $next->value);
            }
            // Not a reference — push back by seeking to saved position.
            $this->tokenizer->seek($savedPos);
        }

        return new PdfNumber((int) $intToken->value);
    }

    private function parseDictionaryOrStream(): Serializable
    {
        $dict = $this->parseDictionary();

        // Check if the dictionary is followed by a `stream` keyword.
        $next = $this->tokenizer->peek();
        if ($next->type === TokenType::StreamKeyword) {
            $this->tokenizer->nextToken(); // consume 'stream'
            return $this->parseStream($dict);
        }

        return $dict;
    }

    private function parseDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();

        while (true) {
            $token = $this->tokenizer->nextToken();
            if ($token->type === TokenType::DictEnd) {
                break;
            }
            if ($token->type === TokenType::Eof) {
                // Tolerate unclosed dictionaries at EOF
                break;
            }
            // Skip unknown tokens between dictionary entries
            if ($token->type === TokenType::Unknown) {
                continue;
            }
            if ($token->type !== TokenType::Name) {
                // Skip unexpected tokens and try to continue
                continue;
            }

            $key = $token->value;
            $value = $this->parseValue();
            $dict->set($key, $value);
        }

        return $dict;
    }

    private function parseArray(): PdfArray
    {
        $items = [];
        while (true) {
            $token = $this->tokenizer->nextToken();
            if ($token->type === TokenType::ArrayEnd) {
                break;
            }
            if ($token->type === TokenType::Eof) {
                // Tolerate unclosed arrays at EOF
                break;
            }
            $items[] = $this->parseTokenValue($token);
        }
        return new PdfArray($items);
    }

    /**
     * Read stream data after the `stream` keyword has been consumed.
     * The `stream` keyword must be followed by a single EOL (LF or CR+LF).
     * The data length comes from `/Length` in the dictionary.
     */
    private function parseStream(PdfDictionary $dict): PdfStream
    {
        // Skip the mandatory EOL after 'stream'
        $byte = $this->source->readByte();
        if ($byte === "\r") {
            // CR+LF
            if ($this->source->peek() === "\n") {
                $this->source->readByte();
            }
        }
        // If it was already LF, we consumed it. If something else, tolerate.

        $length = $dict->get('Length');
        if ($length instanceof PdfNumber) {
            $streamLength = (int) $length->toPdf();
        } elseif (is_int($length)) {
            $streamLength = $length;
        } else {
            // If Length is an indirect reference, we cannot resolve it here
            // because we don't have the resolver yet. Fall back to scanning
            // for 'endstream'.
            $streamLength = $this->scanForEndstream();
        }

        if ($streamLength >= 0) {
            $data = $this->source->read($streamLength);
        } else {
            $data = '';
        }

        // Consume the trailing EOL + endstream keyword.
        // The spec says data is followed by an EOL then 'endstream'.
        // Tolerate missing EOL.
        $this->skipStreamTrailer();

        $stream = new PdfStream($dict, $data);
        return $stream;
    }

    /**
     * Fallback: scan forward for `endstream` to determine stream length.
     *
     * Limits scan to 64 MB to prevent OOM on corrupted/truncated streams.
     */
    private function scanForEndstream(): int
    {
        $start = $this->source->tell();
        $marker = 'endstream';
        $markerLen = strlen($marker);

        // Use a sliding window instead of accumulating a full buffer to limit memory
        $maxScan = 64 * 1024 * 1024; // 64 MB safety limit
        $scanned = 0;
        $window = '';

        while (!$this->source->isEof() && $scanned < $maxScan) {
            $byte = $this->source->readByte();
            if ($byte === null) {
                break;
            }
            $scanned++;
            $window .= $byte;

            // Keep window just large enough to detect the marker with preceding char
            if (strlen($window) > $markerLen + 1) {
                $window = substr($window, -($markerLen + 1));
            }

            if (str_ends_with($window, $marker)) {
                // Validate boundary: "endstream" must be preceded by
                // whitespace (CR, LF, or space) or be at the start of data.
                $markerStart = strlen($window) - $markerLen;
                if ($markerStart > 0) {
                    $preceding = $window[$markerStart - 1];
                    if ($preceding !== "\n" && $preceding !== "\r" && $preceding !== ' ') {
                        // False match inside binary data — keep scanning
                        continue;
                    }
                }

                $endPos = $this->source->tell() - $markerLen;
                $length = $endPos - $start;
                $this->source->seek($start);
                $data = $this->source->read($length);
                $data = rtrim($data, "\r\n");
                $actualLength = strlen($data);
                $this->source->seek($start);
                return $actualLength;
            }
        }

        $this->source->seek($start);
        return 0;
    }

    private function skipStreamTrailer(): void
    {
        // Skip whitespace/EOL between stream data and 'endstream'
        while (!$this->source->isEof()) {
            $byte = $this->source->peek();
            if ($byte === "\r" || $byte === "\n" || $byte === ' ') {
                $this->source->readByte();
            } else {
                break;
            }
        }

        // Try to consume 'endstream' keyword via the tokenizer
        $token = $this->tokenizer->peek();
        if ($token->type === TokenType::EndStreamKeyword) {
            $this->tokenizer->nextToken();
        }
    }

    private function expect(Token $token, TokenType $expected, string $context): void
    {
        if ($token->type !== $expected) {
            throw new InvalidPdfException(
                "Expected $context ({$expected->name}) at offset {$token->offset}, "
                . "got {$token->type->name} ('{$token->value}')"
            );
        }
    }
}
