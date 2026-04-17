<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer;

/**
 * Horizontal text alignment within the content column.
 * (Justify is intentionally omitted in Phase 1 — adding it cleanly
 * requires inter-word spacing adjustments the simple word-wrap
 * algorithm does not yet perform.)
 */
enum Alignment
{
    case Left;
    case Center;
    case Right;
}
