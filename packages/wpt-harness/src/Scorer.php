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
 * Two comparison regimes, and which one applies is decided by the
 * fixture, not by us:
 *
 *  - **Fixture-declared** — the test carries a WPT
 *    `<meta name="fuzzy">`. WPT then abandons exact matching entirely
 *    and scores against the two declared tolerances; the harness does
 *    the same via {@see FuzzyTolerance::permits()}. No extra slack is
 *    added on top: the fixture author already said how much
 *    difference is acceptable, and inventing more would overrule
 *    them.
 *  - **Harness default** — no annotation. WPT requires a byte-exact
 *    screenshot match here. We cannot: the test and its reference are
 *    two different documents rasterised independently, so sub-pixel
 *    layout differences produce anti-aliasing noise on edges that a
 *    browser's single-screenshot comparison never sees. The default
 *    pass threshold `0.01` (1% of pixels, each within a 1% colour
 *    fuzz) is the harness's stand-in. It is a deliberate divergence
 *    from WPT, not an implementation of it.
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
     * `$fuzzy` carries the fixture's own `<meta name="fuzzy">`
     * tolerance when it declared one. It replaces the harness default
     * threshold outright — see the class docblock.
     *
     * @return array{score: float, passed: bool, reason: string|null,
     *               diffImage: string|null}
     */
    public function diff(string $renderedPath, string $referencePath, ?FuzzyTolerance $fuzzy = null): array
    {
        if (!is_file($renderedPath)) {
            return self::failure("rendered image not found: $renderedPath");
        }
        if (!is_file($referencePath)) {
            return self::failure("reference image not found: $referencePath");
        }

        $dim = self::dimensions($renderedPath);
        $totalPixels = $dim['w'] * $dim['h'];
        if ($totalPixels <= 0) {
            return self::failure('rendered image has zero area');
        }

        $diffImage = tempnam(sys_get_temp_dir(), 'wpt_diff_') . '.png';

        if ($fuzzy !== null && !$fuzzy->isTrivial()) {
            return $this->diffAgainstDeclaredTolerance(
                $renderedPath,
                $referencePath,
                $diffImage,
                $fuzzy,
                $totalPixels,
            );
        }

        // Harness default: count pixels outside a 1% colour fuzz and
        // require them to be under `passThreshold` of the frame.
        $metric = $this->runCompare('AE', $renderedPath, $referencePath, $diffImage, 1.0);
        if ($metric['error'] !== null) {
            return self::failure($metric['error']);
        }
        $errorPixels = (int) round($metric['raw']);
        $score = min(1.0, $errorPixels / $totalPixels);

        return [
            'score' => $score,
            'passed' => $score <= $this->passThreshold,
            'reason' => null,
            'diffImage' => is_file($diffImage) ? $diffImage : null,
        ];
    }

    /**
     * WPT's fuzzy comparison, which needs two measurements that no
     * single ImageMagick metric provides:
     *
     *  - `pixels_different` — pixels differing *at all*. That is
     *    `-metric AE` with **zero** fuzz. Running it at the harness's
     *    1% default, as this class used to, silently forgives every
     *    difference below ~2.5/255 before the fixture's own tolerance
     *    is even consulted.
     *  - `max_per_channel` — the largest single-channel absolute
     *    difference, i.e. `-metric PAE`, denormalised to 0-255.
     *
     * (`get_differences` in wptrunner computes the pair with PIL:
     * `ImageChops.difference` then `ImageStat.Stat(...).extrema` /
     * `.count`.)
     *
     * @return array{score: float, passed: bool, reason: string|null,
     *               diffImage: string|null}
     */
    private function diffAgainstDeclaredTolerance(
        string $renderedPath,
        string $referencePath,
        string $diffImage,
        FuzzyTolerance $fuzzy,
        int $totalPixels,
    ): array {
        $absolute = $this->runCompare('AE', $renderedPath, $referencePath, $diffImage, 0.0);
        if ($absolute['error'] !== null) {
            return self::failure($absolute['error']);
        }
        $peak = $this->runCompare('PAE', $renderedPath, $referencePath, null, 0.0);
        if ($peak['error'] !== null) {
            return self::failure($peak['error']);
        }

        $pixelsDifferent = (int) round($absolute['raw']);
        // PAE's parenthesised figure is the quantum-independent
        // normalised peak; WPT's per-channel extrema are 8-bit.
        $maxPerChannel = (int) round($peak['normalised'] * 255.0);
        $passed = $fuzzy->permits($maxPerChannel, $pixelsDifferent);

        return [
            'score' => min(1.0, $pixelsDifferent / $totalPixels),
            'passed' => $passed,
            'reason' => $passed ? null : sprintf(
                'outside declared fuzzy tolerance (%s): maxDifference=%d, totalPixels=%d',
                $fuzzy->describe(),
                $maxPerChannel,
                $pixelsDifferent,
            ),
            'diffImage' => is_file($diffImage) ? $diffImage : null,
        ];
    }

    /**
     * Run ImageMagick `compare` for one metric.
     *
     * `compare` writes `<raw> (<normalised>)` to stderr and exits 0
     * when the images match within fuzz, 1 when they differ, 2 on a
     * hard error.
     *
     * @return array{raw: float, normalised: float, error: string|null}
     */
    private function runCompare(
        string $metric,
        string $renderedPath,
        string $referencePath,
        ?string $diffImage,
        float $fuzzPercent,
    ): array {
        $cmd = sprintf(
            '%s -metric %s -fuzz %s%% %s %s %s 2>&1',
            escapeshellcmd($this->compareBinary),
            escapeshellarg($metric),
            escapeshellarg(rtrim(rtrim(sprintf('%.4F', $fuzzPercent), '0'), '.') ?: '0'),
            escapeshellarg($renderedPath),
            escapeshellarg($referencePath),
            escapeshellarg($diffImage ?? 'null:'),
        );
        exec($cmd, $output, $status);
        $text = trim(implode("\n", $output));
        if ($status === 2) {
            return ['raw' => 0.0, 'normalised' => 0.0, 'error' => "compare error: $text"];
        }
        // The metric is the last line; ImageMagick may print warnings
        // ahead of it, and those contain digits too.
        $number = '-?[0-9.]+(?:e[+-]?\\d+)?';
        foreach (array_reverse($output) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match("~^($number)(?:\\s*\\(($number)\\))?$~i", $line, $m) !== 1) {
                continue;
            }
            return [
                'raw' => (float) $m[1],
                'normalised' => isset($m[2]) ? (float) $m[2] : 0.0,
                'error' => null,
            ];
        }
        return ['raw' => 0.0, 'normalised' => 0.0, 'error' => "unparsable compare output: $text"];
    }

    /**
     * @return array{score: float, passed: bool, reason: string,
     *               diffImage: null}
     */
    private static function failure(string $reason): array
    {
        return [
            'score' => 1.0,
            'passed' => false,
            'reason' => $reason,
            'diffImage' => null,
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
