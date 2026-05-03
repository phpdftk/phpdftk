<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tokenizer;

/**
 * PDF lexical token types produced by the tokenizer -- one case per
 * distinct syntactic element in the PDF file format (ISO 32000-2 S7.2).
 */
enum TokenType
{
    case Name;              // /SomeName
    case LiteralString;     // (text)
    case HexString;         // <48656C6C6F>
    case Integer;           // 123, +4, -2
    case Real;              // 34.5, -.002
    case Boolean;           // true, false
    case Null;              // null
    case ArrayStart;        // [
    case ArrayEnd;          // ]
    case DictStart;         // <<
    case DictEnd;           // >>
    case StreamKeyword;     // stream
    case EndStreamKeyword;  // endstream
    case ObjKeyword;        // obj
    case EndObjKeyword;     // endobj
    case RKeyword;          // R
    case XrefKeyword;       // xref
    case TrailerKeyword;    // trailer
    case StartXrefKeyword;  // startxref
    case Unknown;            // unrecognized keyword (skipped in lenient mode)
    case Eof;               // end of input
}
