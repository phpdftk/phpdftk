<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Perceptual visual diff between a rendered PDF page and a WPT
 * reference image. Returns a normalised score in `[0.0, 1.0]` where
 * `0.0` is byte-identical and `1.0` is "completely different".
 *
 * The harness pass threshold is configurable; the default `0.01`
 * matches the WPT reftest convention (small anti-aliasing differences
 * between rendering engines are expected and tolerated).
 *
 * Phase 4A.3 — implement either via:
 *
 *   (a) `phpdftk/raster` once 4C lands (pure-PHP perceptual diff),
 *   (b) shelling out to Ghostscript + ImageMagick `compare`, or
 *   (c) calling a perceptual-diff binary (pdiff / nodejs odiff /
 *       butteraugli) over IPC.
 *
 * Recommendation: (b) for the harness v1, swap to (a) once 4C is
 * ready so the diff doesn't depend on external binaries.
 */
final class Scorer
{
    public function __construct(
        private readonly float $passThreshold = 0.01,
    ) {}

    /**
     * Compute the perceptual diff between two image files. Both must
     * exist; if either is missing the scorer returns `1.0` (max diff)
     * with a non-zero `$reason`.
     *
     * Phase 4A.3 implements this.
     *
     * @return array{score: float, passed: bool, reason: string|null}
     */
    public function diff(string $renderedPath, string $referencePath): array
    {
        unset($renderedPath, $referencePath);
        return [
            'score' => 1.0,
            'passed' => false,
            'reason' => '4A.3 not yet implemented',
        ];
    }

    public function passThreshold(): float
    {
        return $this->passThreshold;
    }
}
