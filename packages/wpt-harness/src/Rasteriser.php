<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Renders a PDF to an RGBA pixel buffer for downstream perceptual
 * diffing. Phase 4A.2.
 *
 * v1 implementation: shell out to Ghostscript with `-sDEVICE=png16m`
 * at the WPT reference DPI (default 96, override per-test from the
 * WPT metadata).
 *
 * v2 implementation: swap to `phpdftk/raster` once 4C lands so the
 * harness has zero external dependencies.
 *
 * Phase 4A.2 implements both code paths behind a feature flag in
 * `HarnessRunner`.
 */
final class Rasteriser
{
    public function __construct(
        private readonly int $dpi = 96,
        private readonly string $ghostscriptBinary = 'gs',
    ) {}

    /**
     * Rasterise `$pdfPath` to a temp PNG and return the path.
     * Caller is responsible for unlinking the result.
     *
     * Phase 4A.2 implements this.
     */
    public function rasterise(string $pdfPath, int $pageIndex = 0): string
    {
        unset($pdfPath, $pageIndex);
        throw new \RuntimeException('4A.2 not yet implemented');
    }

    public function dpi(): int
    {
        return $this->dpi;
    }

    public function ghostscriptBinary(): string
    {
        return $this->ghostscriptBinary;
    }
}
