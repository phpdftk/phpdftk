<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\ValueList;
use Phpdftk\FontParser\OpenTypeData;

/**
 * Resolves a cascaded `font-family` (+ optional `font-weight` / `font-style`)
 * to a concrete `OpenTypeData` by walking the family list left-to-right and
 * picking the closest matching face per CSS Fonts 4 §6 font-matching.
 *
 * Two layers:
 *  - `$faceMap` — multi-face per family, used when callers want real
 *    bold / italic alternates. Per CSS Fonts 4 §6.4 weight matching and
 *    §6.3 style matching, the resolver picks the face whose weight is
 *    nearest the requested value (with the spec's directional tie-break)
 *    and whose style matches (italic > oblique > normal preference depends
 *    on the requested style).
 *  - `$fontMap` — single-face per family, used as a fallback when `faceMap`
 *    has no entry. Treated as a 400-normal face.
 *
 * Both maps are keyed by lower-case family name; lookups are
 * case-insensitive. Returns `defaultFont` when no family matches.
 */
final readonly class FontResolver
{
    /**
     * @param array<string, OpenTypeData> $fontMap legacy single-face map
     * @param array<string, list<FontFace>> $faceMap weight/style-tagged faces
     */
    public function __construct(
        private array $fontMap,
        private ?OpenTypeData $defaultFont,
        private array $faceMap = [],
    ) {}

    /**
     * Pick a font for the given cascaded `font-family`. When `$weight` /
     * `$style` are supplied, the resolver prefers a real face from
     * `$faceMap` matching them per CSS Fonts 4 §6; otherwise it falls back
     * to the legacy single-face `$fontMap`, or to `$defaultFont`.
     */
    public function resolve(
        ?Value $fontFamily,
        int $weight = 400,
        string $style = 'normal',
    ): ?OpenTypeData {
        $match = $this->resolveMatch($fontFamily, $weight, $style);
        return $match?->face->data ?? $this->defaultFont;
    }

    /**
     * Like {@see resolve()} but returns the matched {@see FontMatch}
     * carrying the chosen face *and* whether it actually satisfies the
     * requested weight/style. Layout uses this so the painter can suppress
     * synthetic fake-bold / fake-italic when a real face matched.
     */
    public function resolveMatch(
        ?Value $fontFamily,
        int $weight = 400,
        string $style = 'normal',
    ): ?FontMatch {
        if ($fontFamily === null) {
            return null;
        }
        $lcStyle = strtolower($style);
        foreach ($this->iterateFamilies($fontFamily) as $name) {
            $key = strtolower($name);
            if (isset($this->faceMap[$key]) && $this->faceMap[$key] !== []) {
                $best = $this->pickFace($this->faceMap[$key], $weight, $lcStyle);
                return new FontMatch(
                    face: $best,
                    matchesWeight: $this->weightSatisfies($best->weight, $weight),
                    matchesStyle: $this->styleSatisfies($best->style, $lcStyle),
                );
            }
            if (isset($this->fontMap[$key])) {
                // Single-face fallback — treat as 400-normal.
                $synthetic = new FontFace($this->fontMap[$key], 400, 'normal');
                return new FontMatch(
                    face: $synthetic,
                    matchesWeight: $this->weightSatisfies(400, $weight),
                    matchesStyle: $this->styleSatisfies('normal', $lcStyle),
                );
            }
        }
        return null;
    }

    /**
     * CSS Fonts 4 §6 font-matching over the in-family face list. Picks
     * first by style preference (an exact-style match always beats a
     * style-mismatched alternative), then within the same-style bucket
     * picks the closest weight using the spec's directional tie-break
     * algorithm.
     *
     * @param list<FontFace> $faces
     */
    private function pickFace(array $faces, int $weight, string $style): FontFace
    {
        // Style buckets: prefer exact match. For 'italic' request, fall
        // back order is italic > oblique > normal; for 'oblique', oblique
        // > italic > normal; for 'normal', normal > oblique > italic.
        $preference = match ($style) {
            'italic' => ['italic', 'oblique', 'normal'],
            'oblique' => ['oblique', 'italic', 'normal'],
            default => ['normal', 'oblique', 'italic'],
        };
        foreach ($preference as $candidateStyle) {
            $bucket = array_values(array_filter(
                $faces,
                static fn(FontFace $f): bool => $f->style === $candidateStyle,
            ));
            if ($bucket !== []) {
                return $this->pickWeight($bucket, $weight);
            }
        }
        // Defensive: faces is non-empty (checked by caller) but no style
        // bucket matched (impossible since FontFace::style is normalised
        // to one of three values). Return the first.
        return $faces[0];
    }

    /**
     * Per CSS Fonts 4 §6.4: weight selection inside a style bucket. The
     * directional rule (treat 400-500 differently from <400 and >500)
     * captures the practical "if you want normal-ish, prefer 500 over
     * 600" behaviour browsers ship.
     *
     * @param list<FontFace> $faces
     */
    private function pickWeight(array $faces, int $weight): FontFace
    {
        // Group by weight; the spec picks closest, with directional
        // tie-break: if 400 <= weight <= 500, scan 400..500 first; below
        // 400, scan downward then upward; above 500, scan upward then
        // downward.
        usort($faces, static fn(FontFace $a, FontFace $b): int => $a->weight <=> $b->weight);
        $exact = null;
        foreach ($faces as $f) {
            if ($f->weight === $weight) {
                return $f;
            }
        }
        if ($weight >= 400 && $weight <= 500) {
            // Look in [weight..500] then [<weight] then [>500].
            foreach ($faces as $f) {
                if ($f->weight > $weight && $f->weight <= 500) {
                    return $f;
                }
            }
            for ($i = count($faces) - 1; $i >= 0; $i--) {
                if ($faces[$i]->weight < $weight) {
                    return $faces[$i];
                }
            }
            // Else: only weights >500 remain — return the lightest.
            foreach ($faces as $f) {
                if ($f->weight > 500) {
                    return $f;
                }
            }
        } elseif ($weight < 400) {
            // Scan downward (closer to 0) first.
            for ($i = count($faces) - 1; $i >= 0; $i--) {
                if ($faces[$i]->weight < $weight) {
                    return $faces[$i];
                }
            }
            foreach ($faces as $f) {
                if ($f->weight > $weight) {
                    return $f;
                }
            }
        } else {
            // weight > 500: scan upward first.
            foreach ($faces as $f) {
                if ($f->weight > $weight) {
                    return $f;
                }
            }
            for ($i = count($faces) - 1; $i >= 0; $i--) {
                if ($faces[$i]->weight < $weight) {
                    return $faces[$i];
                }
            }
        }
        // Single-element bucket: just return it.
        return $faces[0];
    }

    /**
     * A face's weight "satisfies" the request when the face is at least
     * as heavy as the requested 600+ (bold-ish) cutoff, or the face is at
     * most 500 (normal-ish) when the request is normal-ish. Matches the
     * coarse-grained "do we still need fake-bold?" decision the painter
     * cares about, not the fine-grained weight-difference signal.
     */
    private function weightSatisfies(int $faceWeight, int $requestedWeight): bool
    {
        $faceIsBold = $faceWeight >= 600;
        $requestIsBold = $requestedWeight >= 600;
        return $faceIsBold === $requestIsBold;
    }

    private function styleSatisfies(string $faceStyle, string $requestedStyle): bool
    {
        if ($requestedStyle === 'normal') {
            return $faceStyle === 'normal';
        }
        // italic or oblique request — either italic or oblique face
        // satisfies (browsers treat them interchangeably for fallback).
        return in_array($faceStyle, ['italic', 'oblique'], true);
    }

    /**
     * Yields each family name in the comma-separated `font-family` list,
     * unquoted and trimmed. Generic keywords (`serif` / `sans-serif` /
     * `monospace` / `cursive` / `fantasy` / `system-ui`) come through
     * verbatim so callers can register fonts under those names.
     *
     * @return iterable<string>
     */
    private function iterateFamilies(Value $value): iterable
    {
        if ($value instanceof ValueList) {
            foreach ($value->values as $item) {
                $name = $this->familyToString($item);
                if ($name !== '') {
                    yield $name;
                }
            }
            return;
        }
        $name = $this->familyToString($value);
        if ($name !== '') {
            yield $name;
        }
    }

    private function familyToString(Value $value): string
    {
        if ($value instanceof StringValue) {
            return $value->value;
        }
        if ($value instanceof Keyword) {
            return $value->name;
        }
        if ($value instanceof ValueList) {
            $parts = [];
            foreach ($value->values as $v) {
                $piece = $this->familyToString($v);
                if ($piece !== '') {
                    $parts[] = $piece;
                }
            }
            return implode(' ', $parts);
        }
        if ($value instanceof CssFunction) {
            return '';
        }
        return '';
    }
}
