<?php

declare(strict_types=1);

namespace Phpdftk\Html\TreeConstruction;

/**
 * Tree-construction insertion modes per WHATWG HTML §13.2.6.
 *
 * The full enum is declared even though Phase 1B.3 implements only the
 * subset needed for typical flow content. Unimplemented modes throw a
 * structured exception so it's clear which spec scenario hasn't landed yet.
 */
enum InsertionMode
{
    case Initial;
    case BeforeHtml;
    case BeforeHead;
    case InHead;
    case InHeadNoscript;
    case AfterHead;
    case InBody;
    case Text;
    case InTable;
    case InTableText;
    case InCaption;
    case InColumnGroup;
    case InTableBody;
    case InRow;
    case InCell;
    case InSelect;
    case InSelectInTable;
    case InTemplate;
    case AfterBody;
    case InFrameset;
    case AfterFrameset;
    case AfterAfterBody;
    case AfterAfterFrameset;
}
