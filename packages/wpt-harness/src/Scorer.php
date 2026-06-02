<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Perceptual visual diff between a rendered PDF page and a WPT
 * reference image.
 *
 * Returns a normalised score in `[0.0, 1.0]` where `0.0` is
 * byte-identical and `1.0` is "completely different".
 *
 * v1 implementation: shells out to ImageMagick `compare -metric AE`
 * (absolute-error pixel count), normalises by total pixel count.
 *
 * v2 implementation (Phase 4C / 4A.3 follow-up): switch to
 * `phpdftk/raster` perceptual-diff once it lands.
 *
 * The default pass threshold `0.01` matches the WPT reftest
 * convention — small anti-aliasing differences between rendering
 * engines are expected and tolerated.
 */
final class Scorer
{
    public function __construct(
        private readonly float $passThreshold = 0.01,
        private readonly string $compareBinary = 'compare',
    ) {}

    /**
     * Compute the perceptual diff between two image files. Both must
     * exist; if either is missing the scorer returns `1.0` (max diff)
     * with a non-zero `$reason`.
     *
     * @return array{score: float, passed: bool, reason: string|null,
     *               diffImage: string|null}
     */
    public function diff(string $renderedPath, string $referencePath): array
    {
        if (!is_file($renderedPath)) {
            return [
                'score' => 1.0,
                'passed' => false,
                'reason' => "rendered image not found: $renderedPath",
                'diffImage' => null,
            ];
        }
        if (!is_file($referencePath)) {
            return [
                'score' => 1.0,
                'passed' => false,
                'reason' => "reference image not found: $referencePath",
                'diffImage' => null,
            ];
        }

        $diffImage = tempnam(sys_get_temp_dir(), 'wpt_diff_') . '.png';
        // `compare -metric AE` writes the absolute-error pixel count
        // to stderr. Exit status: 0 = images match (no diff above
        // fuzz), 1 = images differ, 2 = error.
        $cmd = sprintf(
            '%s -metric AE -fuzz 1%% %s %s %s 2>&1',
            escapeshellcmd($this->compareBinary),
            escapeshellarg($renderedPath),
            escapeshellarg($referencePath),
            escapeshellarg($diffImage),
        );
        exec($cmd, $output, $status);
        if ($status === 2) {
            $err = implode("\n", $output);
            return [
                'score' => 1.0,
                'passed' => false,
                'reason' => "compare error: $err",
                'diffImage' => null,
            ];
        }
        $errorPixels = (int) trim(implode("\n", $output));
        $dim = self::dimensions($renderedPath);
        $totalPixels = $dim['w'] * $dim['h'];
        if ($totalPixels <= 0) {
            return [
                'score' => 1.0,
                'passed' => false,
                'reason' => 'rendered image has zero area',
                'diffImage' => null,
            ];
        }
        $score = min(1.0, $errorPixels / $totalPixels);
        return [
            'score' => $score,
            'passed' => $score <= $this->passThreshold,
            'reason' => null,
            'diffImage' => is_file($diffImage) ? $diffImage : null,
        ];
    }

    /**
     * Best-effort dimensions read via GD. Falls back to (0, 0) on
     * error (treated as max-diff by the caller).
     *
     * @return array{w: int, h: int}
     */
    private static function dimensions(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return ['w' => 0, 'h' => 0];
        }
        return ['w' => $info[0], 'h' => $info[1]];
    }

    public function passThreshold(): float
    {
        return $this->passThreshold;
    }

    public function compareBinary(): string
    {
        return $this->compareBinary;
    }

    /**
     * Probe whether the configured `compare` binary is callable.
     */
    public function isAvailable(): bool
    {
        $cmd = sprintf(
            '%s --version 2>/dev/null',
            escapeshellcmd($this->compareBinary),
        );
        exec($cmd, $_, $status);
        return $status === 0;
    }
}
