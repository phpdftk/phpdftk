<?php

declare(strict_types=1);

namespace Phpdftk\Core;

/**
 * Represents a PDF name literal, e.g. /MediaBox, /Type, /Font.
 * Names beginning with / are the canonical PDF name syntax.
 */
class PdfName implements Serializable
{
    public function __construct(public readonly string $value)
    {
    }

    /**
     * Returns the PDF name token, escaping characters outside the printable
     * ASCII range (except #) with #XX notation as required by the spec.
     */
    public function toPdf(): string
    {
        $escaped = '';
        $len = strlen($this->value);
        for ($i = 0; $i < $len; $i++) {
            $c = $this->value[$i];
            $ord = ord($c);
            // Must escape: delimiters, whitespace, #, and non-printable/high bytes
            if (
                $ord < 0x21
                || $ord > 0x7E
                || $c === '#'
                || $c === '('
                || $c === ')'
                || $c === '<'
                || $c === '>'
                || $c === '['
                || $c === ']'
                || $c === '{'
                || $c === '}'
                || $c === '/'
                || $c === '%'
            ) {
                $escaped .= sprintf('#%02X', $ord);
            } else {
                $escaped .= $c;
            }
        }

        return '/' . $escaped;
    }
}
