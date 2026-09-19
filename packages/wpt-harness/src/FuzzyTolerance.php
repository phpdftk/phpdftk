<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * A WPT `<meta name="fuzzy">` tolerance declaration.
 *
 * WPT reftests are exact-match by default. A fixture that knows it
 * cannot be pixel-exact across engines relaxes the comparison with a
 * fuzzy annotation carrying **two** independent tolerances:
 *
 *     <meta name=fuzzy content="maxDifference=0-5;totalPixels=0-100">
 *
 *  - `maxDifference` bounds the largest per-channel absolute colour
 *    difference (0-255) found anywhere in the image.
 *  - `totalPixels` bounds how many pixels differ at all.
 *
 * Both are *ranges*, both bounds inclusive, and both are enforced —
 * see {@see self::permits()}. Either may be written as a bare number
 * (`maxDifference=1`), which WPT reads as the degenerate range `1-1`.
 * The names may be omitted entirely, in which case the two ranges are
 * positional in the order `maxDifference;totalPixels`:
 *
 *     <meta name=fuzzy content="0-5;0-100">
 *
 * Grammar and semantics are pinned to WPT's own implementation:
 * parsing is `tools/manifest/sourcefile.py::fuzzy`, comparison is
 * `tools/wptrunner/wptrunner/executors/base.py::_screenshot_list`.
 *
 * Dropping `maxDifference` — as this harness did until now — scores
 * fixtures wrong in *both* directions: a fixture declaring a tight
 * colour tolerance is let off with whatever the harness default fuzz
 * happened to be, and a fixture declaring a loose one is held to a
 * default it never asked for.
 */
final class FuzzyTolerance
{
    /**
     * @param array{int, int} $maxDifference inclusive [min, max] per-channel colour difference (0-255)
     * @param array{int, int} $totalPixels   inclusive [min, max] count of differing pixels
     */
    private function __construct(
        public readonly array $maxDifference,
        public readonly array $totalPixels,
    ) {}

    /**
     * Parse a `content` attribute value. Returns `null` when the value
     * is not a well-formed fuzzy declaration — templated placeholders
     * (`{{ fuzzy }}`) and other malformed values fall back to the
     * harness default rather than failing the run.
     *
     * Any `<ref-url>:` prefix must already have been stripped by the
     * caller (see `parse_ref_keyed_meta` in WPT): only the tolerance
     * expression is parsed here.
     */
    public static function parse(string $content): ?self
    {
        $ranges = explode(';', trim($content));
        if (count($ranges) !== 2) {
            // WPT raises "Malformed fuzzy value" here; the harness is
            // lenient and falls back to its default threshold.
            return null;
        }

        /** @var array<string, array{int, int}> $named */
        $named = [];
        /** @var list<array{int, int}> $positional */
        $positional = [];
        foreach ($ranges as $range) {
            $name = null;
            if (str_contains($range, '=')) {
                [$name, $range] = array_map(trim(...), explode('=', $range, 2));
                if ($name !== 'maxDifference' && $name !== 'totalPixels') {
                    return null;
                }
                if (isset($named[$name])) {
                    return null;
                }
            }
            $parsed = self::parseRange($range);
            if ($parsed === null) {
                return null;
            }
            if ($name === null) {
                $positional[] = $parsed;
            } else {
                $named[$name] = $parsed;
            }
        }

        // Named arguments bind first; the positional ones fill the
        // remaining slots in declaration order (maxDifference, then
        // totalPixels) — exactly WPT's `positional_args.popleft()`.
        $resolved = [];
        foreach (['maxDifference', 'totalPixels'] as $argument) {
            if (isset($named[$argument])) {
                $resolved[$argument] = $named[$argument];
                continue;
            }
            if ($positional === []) {
                return null;
            }
            $resolved[$argument] = array_shift($positional);
        }
        if ($positional !== []) {
            return null;
        }

        return new self($resolved['maxDifference'], $resolved['totalPixels']);
    }

    /**
     * `<lo>-<hi>`, or a bare `<n>` meaning `<n>-<n>`.
     *
     * @return array{int, int}|null
     */
    private static function parseRange(string $range): ?array
    {
        $range = trim($range);
        if (str_contains($range, '-')) {
            [$min, $max] = explode('-', $range, 2);
        } else {
            $min = $max = $range;
        }
        $min = trim($min);
        $max = trim($max);
        if (!self::isInteger($min) || !self::isInteger($max)) {
            return null;
        }
        return [(int) $min, (int) $max];
    }

    private static function isInteger(string $value): bool
    {
        return preg_match('~^\d+$~', $value) === 1;
    }

    /**
     * Does an observed diff fall within the declared tolerance?
     *
     * Verbatim port of wptrunner's decision
     * (`executors/base.py`):
     *
     *     equal = ((pixels_different == 0 and allowed_different[0] == 0) or
     *              (max_per_channel == 0 and allowed_per_channel[0] == 0) or
     *              (allowed_per_channel[0] <= max_per_channel <= allowed_per_channel[1] and
     *               allowed_different[0] <= pixels_different <= allowed_different[1]))
     *
     * Note both LOWER bounds are live. A fixture declaring
     * `totalPixels=7600-8700` is asserting that the engines *must*
     * disagree over roughly that many pixels; a render that differs
     * over 12 of them is not "better than required", it is a
     * different rendering and WPT fails it. The two leading clauses
     * are the escape hatch for a perfect match against a tolerance
     * whose floor is zero.
     *
     * @param int $maxPerChannel   largest per-channel absolute difference, 0-255
     * @param int $pixelsDifferent count of pixels differing at all
     */
    public function permits(int $maxPerChannel, int $pixelsDifferent): bool
    {
        if ($pixelsDifferent === 0 && $this->totalPixels[0] === 0) {
            return true;
        }
        if ($maxPerChannel === 0 && $this->maxDifference[0] === 0) {
            return true;
        }
        return $maxPerChannel >= $this->maxDifference[0]
            && $maxPerChannel <= $this->maxDifference[1]
            && $pixelsDifferent >= $this->totalPixels[0]
            && $pixelsDifferent <= $this->totalPixels[1];
    }

    /**
     * A `0-0;0-0` declaration is WPT's "no tolerance at all" sentinel
     * and is treated as exact matching rather than as a fuzzy range.
     */
    public function isTrivial(): bool
    {
        return $this->maxDifference === [0, 0] && $this->totalPixels === [0, 0];
    }

    public function describe(): string
    {
        return sprintf(
            'maxDifference=%d-%d;totalPixels=%d-%d',
            $this->maxDifference[0],
            $this->maxDifference[1],
            $this->totalPixels[0],
            $this->totalPixels[1],
        );
    }
}
