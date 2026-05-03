<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core;

/**
 * Contract for anything that can emit raw PDF syntax via `toPdf()`.
 *
 * Inline dictionaries (e.g., BorderStyle, TransitionDict) implement this
 * directly — they serialize inside their parent and never get an object
 * number. Top-level objects extend {@see PdfObject} instead, which adds
 * indirect-object wrapping and registration with {@see File\ObjectRegistry}.
 */
interface Serializable
{
    public function toPdf(): string;
}
