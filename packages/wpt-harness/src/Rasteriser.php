<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Renders a PDF to a PNG file for downstream perceptual diffing.
 *
 * v1 implementation: shells out to Ghostscript with `-sDEVICE=png16m`
 * at the configured DPI (default 96 — the WPT reference). One PNG
 * per page; this method returns the path for `$pageIndex`.
 *
 * v2 implementation (Phase 4C): swap to `phpdftk/raster` so the
 * harness has zero external dependencies. Until then the gs path
 * is the canonical implementation.
 */
final class Rasteriser
{
    public function __construct(
        private readonly int $dpi = 96,
        private readonly string $ghostscriptBinary = 'gs',
    ) {}

    /**
     * Rasterise `$pdfPath` to a temp PNG. Returns the path. Caller
     * is responsible for unlinking the result.
     *
     * `$pageIndex` is 0-based; Ghostscript's `-dFirstPage` /
     * `-dLastPage` are 1-based so we add 1 internally.
     */
    public function rasterise(string $pdfPath, int $pageIndex = 0): string
    {
        if (!is_file($pdfPath)) {
            throw new \RuntimeException("PDF not found: $pdfPath");
        }
        $outPath = tempnam(sys_get_temp_dir(), 'wpt_render_') . '.png';
        $page = $pageIndex + 1;
        $cmd = sprintf(
            '%s -dNOPAUSE -dBATCH -dQUIET -sDEVICE=png16m '
                . '-r%d -dFirstPage=%d -dLastPage=%d '
                . '-sOutputFile=%s %s 2>&1',
            escapeshellcmd($this->ghostscriptBinary),
            $this->dpi,
            $page,
            $page,
            escapeshellarg($outPath),
            escapeshellarg($pdfPath),
        );
        exec($cmd, $output, $status);
        if ($status !== 0 || !is_file($outPath)) {
            $err = implode("\n", $output);
            throw new \RuntimeException("Ghostscript rasterise failed (exit $status): $err");
        }
        return $outPath;
    }

    public function dpi(): int
    {
        return $this->dpi;
    }

    public function ghostscriptBinary(): string
    {
        return $this->ghostscriptBinary;
    }

    /**
     * Probe whether the configured Ghostscript binary is callable.
     * Used by the harness CLI to fail fast with a useful message
     * when the substrate isn't installed.
     */
    public function isAvailable(): bool
    {
        $cmd = sprintf(
            '%s --version 2>/dev/null',
            escapeshellcmd($this->ghostscriptBinary),
        );
        exec($cmd, $_, $status);
        return $status === 0;
    }
}
