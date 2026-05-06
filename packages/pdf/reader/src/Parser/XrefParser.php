<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Reader\Exception\InvalidPdfException;
use Phpdftk\Pdf\Reader\Tokenizer\Source;
use Phpdftk\Pdf\Reader\Tokenizer\Tokenizer;
use Phpdftk\Pdf\Reader\XrefEntry;

/**
 * Parses a classic cross-reference table and its trailer dictionary.
 *
 * Uses raw byte reads for the fixed-format xref section (to avoid
 * interleaving tokenized and raw reads on the same Source), then hands
 * off to the ObjectParser for the trailer dictionary only.
 */
final class XrefParser
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly Source $source,
        private readonly ObjectParser $objectParser,
    ) {}

    /**
     * Parse a classic xref table at the given byte offset.
     *
     * @param list<string> $warnings
     * @return array{0: array<int, XrefEntry>, 1: PdfDictionary}
     */
    public function parseClassicXref(int $offset, bool $strict = true, array &$warnings = []): array
    {
        $this->source->seek($offset);

        // Read and verify "xref" keyword
        $this->skipWhitespace();
        $keyword = $this->readWord();
        if ($keyword !== 'xref') {
            throw new InvalidPdfException(
                "Expected 'xref' at offset $offset, got '$keyword'",
            );
        }

        $entries = [];

        // Parse subsections until we hit "trailer"
        while (true) {
            $this->skipWhitespace();
            $word = $this->readWord();
            if ($word === 'trailer') {
                break;
            }
            if ($word === '' || $this->source->isEof()) {
                throw new InvalidPdfException(
                    "Unexpected end of xref table at offset " . $this->source->tell() . ": expected 'trailer'",
                );
            }

            // $word is the first object number of this subsection
            $firstObj = (int) $word;

            $this->skipWhitespace();
            $countWord = $this->readWord();
            $count = (int) $countWord;

            $this->skipWhitespace();

            for ($i = 0; $i < $count; $i++) {
                // Read an entry line. Spec says exactly 20 bytes, but
                // some producers write 21 (extra space before CRLF).
                // Be tolerant: read up to 24 bytes, then trim and parse.
                $line = $this->readLine(24);
                if (!preg_match('/^(\d{10})\s+(\d{5})\s+([nf])/', $line, $em)) {
                    if ($strict) {
                        throw new InvalidPdfException(
                            "Malformed xref entry at offset " . $this->source->tell() . ": '$line'",
                        );
                    }
                    $warnings[] = "Skipped malformed xref entry for object " . ($firstObj + $i) . ": '$line'";
                    continue;
                }
                $entryOffset = (int) $em[1];
                $gen = (int) $em[2];
                $type = ($em[3] === 'f') ? XrefEntry::TYPE_FREE : XrefEntry::TYPE_IN_USE;
                $entries[$firstObj + $i] = new XrefEntry($type, $entryOffset, $gen);
            }
        }

        // Now the source is positioned right after "trailer".
        // Sync the tokenizer to this position and parse the trailer dict.
        $this->tokenizer->seek($this->source->tell());
        $trailer = $this->objectParser->parseValue();
        if (!$trailer instanceof PdfDictionary) {
            throw new InvalidPdfException('Trailer is not a dictionary');
        }

        return [$entries, $trailer];
    }

    /**
     * Read up to $maxBytes, stopping at (and consuming) the first \n.
     */
    private function readLine(int $maxBytes): string
    {
        $line = '';
        for ($i = 0; $i < $maxBytes; $i++) {
            $byte = $this->source->readByte();
            if ($byte === null) {
                break;
            }
            if ($byte === "\n") {
                break;
            }
            $line .= $byte;
        }
        return rtrim($line, "\r");
    }

    private function skipWhitespace(): void
    {
        while (!$this->source->isEof()) {
            $byte = $this->source->peek();
            if ($byte === '' || ($byte !== "\x00" && $byte !== "\x09" && $byte !== "\x0A"
                && $byte !== "\x0C" && $byte !== "\x0D" && $byte !== "\x20")) {
                return;
            }
            $this->source->readByte();
        }
    }

    /**
     * Read a contiguous run of non-whitespace, non-delimiter bytes.
     */
    private function readWord(): string
    {
        $word = '';
        while (!$this->source->isEof()) {
            $byte = $this->source->peek();
            if ($byte === '' || $byte === "\x00" || $byte === "\x09" || $byte === "\x0A"
                || $byte === "\x0C" || $byte === "\x0D" || $byte === "\x20"
                || $byte === '<' || $byte === '/' || $byte === '[') {
                break;
            }
            $word .= $this->source->readByte();
        }
        return $word;
    }
}
