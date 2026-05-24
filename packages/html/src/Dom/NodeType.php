<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Node type discriminator. Mirrors the WHATWG DOM constants but exposed as a PHP enum.
 */
enum NodeType
{
    case Element;
    case Text;
    case Comment;
    case Document;
    case DocumentType;
    case DocumentFragment;
    case ProcessingInstruction;
}
