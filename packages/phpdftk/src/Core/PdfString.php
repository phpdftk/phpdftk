<?php

declare(strict_types=1);

namespace Phpdftk\Core;

/**
 * Represents a PDF literal string (text) or a hex string <hex>.
 */
class PdfString implements Serializable
{
    public function __construct(
        public readonly string $value,
        public readonly bool $hex = false
    ) {
    }

    public function toPdf(): string
    {
        if ($this->hex) {
            return '<' . bin2hex($this->value) . '>';
        }

        // Literal string: escape backslash, parentheses, and control chars
        $escaped = '';
        $len = strlen($this->value);
        for ($i = 0; $i < $len; $i++) {
            $c = $this->value[$i];
            $escaped .= match ($c) {
                '\\' => '\\\\',
                '('  => '\\(',
                ')'  => '\\)',
                "\n" => '\\n',
                "\r" => '\\r',
                "\t" => '\\t',
                "\x08" => '\\b',
                "\x0C" => '\\f',
                default => $c,
            };
        }

        return '(' . $escaped . ')';
    }
}
