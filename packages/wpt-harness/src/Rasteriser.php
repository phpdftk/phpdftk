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
    /**
     * Sets Ghostscript's fill adjustment to zero in both axes.
     * See {@see self::fillAdjustPrologue()}.
     */
    private const FILL_ADJUST = '0 0 .setfilladjust2';

    /**
     * Memoised {@see self::supportsFillAdjust()} probe, keyed by
     * binary — one `gs` spawn per process rather than per page.
     *
     * @var array<string, bool>
     */
    private static array $fillAdjustSupport = [];

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
                . '-sOutputFile=%s %s%s 2>&1',
            escapeshellcmd($this->ghostscriptBinary),
            $this->dpi,
            $page,
            $page,
            escapeshellarg($outPath),
            $this->fillAdjustPrologue(),
            escapeshellarg($pdfPath),
        );
        exec($cmd, $output, $status);
        if ($status !== 0 || !is_file($outPath)) {
            $err = implode("\n", $output);
            throw new \RuntimeException("Ghostscript rasterise failed (exit $status): $err");
        }
        return $outPath;
    }

    /**
     * `-c '<prologue>' -f` clause that puts path fills on the same
     * rounding rule as every other paint path, or `''` on a
     * Ghostscript build that cannot do it.
     *
     * Ghostscript expands each path fill by half a device pixel
     * before scan-converting it, so `re ... f` paints every pixel the
     * fill touches at all. Shadings, image XObjects and glyphs are
     * resolved at the pixel CENTRE instead. An edge landing in the
     * upper half of a pixel therefore belongs to one device column
     * when a rectangle draws it and the next column right when a
     * gradient, an image or a glyph draws it — the renderer itself
     * rounds nothing, emitting both at full precision, so this is the
     * pipeline's only rounding decision and it was being taken two
     * different ways.
     *
     * A reftest reaches the same picture down two paint paths by
     * construction (a gradient with a hard stop against the solid
     * rectangles of its reference, say), so the disagreement shows up
     * as a whole-colour seam one pixel wide and fails an exact-match
     * comparison. Zeroing the adjustment leaves one rule — pixel
     * centre — for the whole pipeline.
     *
     * The cost is dropout: a fill thinner than a device pixel that
     * clears every pixel centre now paints nothing, where the
     * adjustment would have widened it to a full pixel. Anything one
     * CSS pixel or wider is unaffected at 72 dpi, since one CSS pixel
     * is one device pixel and always covers exactly one centre.
     */
    private function fillAdjustPrologue(): string
    {
        if (!self::supportsFillAdjust($this->ghostscriptBinary)) {
            return '';
        }
        return '-c ' . escapeshellarg(self::FILL_ADJUST) . ' -f ';
    }

    /**
     * Whether `$binary` understands {@see self::FILL_ADJUST}.
     *
     * It is a Ghostscript internal operator, not a documented switch:
     * PostScript's `setfilladjust` was dropped in Ghostscript 10 and
     * `-dFILLADJUST` has never existed (it parses, and does nothing).
     * So probe once per binary per process and degrade to the default
     * behaviour rather than failing every rasterise on a build that
     * lacks it.
     */
    private static function supportsFillAdjust(string $binary): bool
    {
        if (!isset(self::$fillAdjustSupport[$binary])) {
            $cmd = sprintf(
                '%s -dNOPAUSE -dBATCH -dQUIET -dNODISPLAY -c %s >/dev/null 2>&1',
                escapeshellcmd($binary),
                escapeshellarg(self::FILL_ADJUST . ' quit'),
            );
            exec($cmd, $_, $status);
            self::$fillAdjustSupport[$binary] = $status === 0;
        }
        return self::$fillAdjustSupport[$binary];
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
