<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * Synthesised block-level wrapper used to hold runs of inline children that
 * appear alongside block children of a block-level parent.
 *
 * Per CSS Display 3 §3.4, when a block container has both inline and block
 * children, each contiguous run of inlines gets wrapped in an anonymous
 * block box. This keeps the formatting contexts clean — every BFC contains
 * only block-level children, every IFC only inline-level.
 *
 * `$element` is always null; the box is parser-generated, not author-marked.
 */
final class AnonymousBlockBox extends Box {}
