<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

/**
 * A single content stream operation: zero or more operands followed
 * by an operator keyword.
 *
 * Examples:
 *   "BT"              → operator="BT", operands=[]
 *   "/F1 12 Tf"       → operator="Tf", operands=["/F1", "12"]
 *   "72 720 Td"       → operator="Td", operands=["72", "720"]
 *   "(Hello World) Tj" → operator="Tj", operands=["(Hello World)"]
 */
final class ContentStreamOp
{
    /**
     * @param list<string> $operands Raw operand strings
     * @param string $operator The operator keyword (e.g., "BT", "Tf", "Tj")
     */
    public function __construct(
        public readonly array $operands,
        public readonly string $operator,
    ) {
    }
}
