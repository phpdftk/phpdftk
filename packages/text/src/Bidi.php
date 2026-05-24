<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * UAX #9 bidi analyser — Phase-1 implementation.
 *
 * The algorithm here is the "common path" subset sufficient for the MVP's
 * Latin / CJK / Cyrillic / Greek scope. It implements:
 *
 *  - **P2 / P3**: base direction detection (first strong char wins,
 *    LTR fallback) and base level assignment.
 *  - **L → 0 / R, AL → 1** strong-type level assignment.
 *  - **N1 (partial)**: neutrals between same-direction chars take that
 *    direction; neutrals between LTR and RTL adopt the base direction.
 *  - **Run consolidation**: contiguous same-level characters fuse into
 *    one `BidiRun`.
 *
 * Notably **NOT** implemented yet (Phase 2):
 *
 *  - Explicit embedding controls (RLE / LRE / RLO / LRO / PDF) and the
 *    isolate controls (RLI / LRI / FSI / PDI). They are treated as
 *    neutrals here.
 *  - Weak types W1–W7 (Arabic shaping interactions, European number
 *    separators, common number separators around digits).
 *  - I1 / I2 implicit level resolution (separate odd/even-level rules
 *    for AN/EN/L).
 *  - Bracket pair handling (BD16 / N0).
 *  - L1 separator reset.
 *
 * For pure-LTR documents (the MVP target), all of these are no-ops, so
 * the simplified algorithm produces spec-correct output. RTL documents
 * with embedded numbers / parentheses / quotes will produce
 * approximations that improve over time as the Phase-2 work lands.
 *
 * Byte offsets follow UTF-8 semantics throughout; we walk codepoints
 * using PHP's `mb_*` family and use the byte position for run boundaries.
 */
final class Bidi
{
    public function analyze(string $text, BidiBase $base = BidiBase::Auto): BidiResult
    {
        if ($text === '') {
            return new BidiResult(
                $base === BidiBase::Auto ? BidiBase::Ltr : $base,
                [],
            );
        }

        // Per-codepoint direction with byte offsets.
        $chars = self::decodeUtf8($text);
        $directions = [];
        foreach ($chars as $i => $entry) {
            $directions[$i] = $this->classify($entry['codepoint']);
        }

        // P2: find first strong character for auto-base.
        $resolvedBase = $base;
        if ($resolvedBase === BidiBase::Auto) {
            $resolvedBase = BidiBase::Ltr;
            foreach ($directions as $d) {
                if ($d === 'L') {
                    $resolvedBase = BidiBase::Ltr;
                    break;
                }
                if ($d === 'R') {
                    $resolvedBase = BidiBase::Rtl;
                    break;
                }
            }
        }
        $baseLevel = $resolvedBase === BidiBase::Rtl ? 1 : 0;

        // Assign per-char levels: strong types take their natural level,
        // neutrals borrow direction from surrounding strong characters
        // (N1 partial), falling back to the base level.
        $levels = [];
        $count = count($chars);
        for ($i = 0; $i < $count; $i++) {
            $d = $directions[$i];
            $levels[$i] = match ($d) {
                'L' => 0,
                'R' => 1,
                default => self::neutralLevel($directions, $i, $baseLevel),
            };
        }

        // Consolidate contiguous same-level runs.
        $runs = [];
        $startIdx = 0;
        for ($i = 1; $i <= $count; $i++) {
            if ($i === $count || $levels[$i] !== $levels[$startIdx]) {
                $startByte = $chars[$startIdx]['byteOffset'];
                $endByte = $i === $count
                    ? strlen($text)
                    : $chars[$i]['byteOffset'];
                $runs[] = new BidiRun(
                    offset: $startByte,
                    length: $endByte - $startByte,
                    level: $levels[$startIdx],
                );
                $startIdx = $i;
            }
        }

        return new BidiResult($resolvedBase, $runs);
    }

    /**
     * Classify a codepoint into UAX #9 strong / neutral categories. Returns
     * one of: `'L'`, `'R'`, `'AL'`, `'EN'`, `'AN'`, `'WS'`, `'ON'`, `'BN'`.
     * AL is merged into R for level assignment (both yield level 1).
     */
    private function classify(int $cp): string
    {
        $direction = \IntlChar::charDirection($cp);
        return match ($direction) {
            \IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT => 'L',
            \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT,
            \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC => 'R',
            \IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER => 'EN',
            \IntlChar::CHAR_DIRECTION_ARABIC_NUMBER => 'AN',
            \IntlChar::CHAR_DIRECTION_WHITE_SPACE_NEUTRAL => 'WS',
            default => 'ON',
        };
    }

    /**
     * Decide the level for a neutral character. N1 partial: look back to
     * the most recent strong, look forward to the next strong; if they
     * agree, adopt that direction. Otherwise fall back to the base level.
     *
     * @param array<int, string> $directions
     */
    private static function neutralLevel(array $directions, int $i, int $baseLevel): int
    {
        $prev = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if ($directions[$j] === 'L' || $directions[$j] === 'R') {
                $prev = $directions[$j];
                break;
            }
        }
        $next = null;
        $count = count($directions);
        for ($j = $i + 1; $j < $count; $j++) {
            if ($directions[$j] === 'L' || $directions[$j] === 'R') {
                $next = $directions[$j];
                break;
            }
        }
        if ($prev !== null && $prev === $next) {
            return $prev === 'R' ? 1 : 0;
        }
        return $baseLevel;
    }

    /**
     * Decode a UTF-8 string into per-codepoint metadata: the codepoint
     * value plus its starting byte offset.
     *
     * @return list<array{codepoint: int, byteOffset: int}>
     */
    private static function decodeUtf8(string $text): array
    {
        $out = [];
        $bytes = strlen($text);
        $i = 0;
        while ($i < $bytes) {
            $byte = ord($text[$i]);
            if ($byte < 0x80) {
                $out[] = ['codepoint' => $byte, 'byteOffset' => $i];
                $i++;
            } elseif ($byte < 0xC0) {
                // Invalid continuation; skip with replacement.
                $out[] = ['codepoint' => 0xFFFD, 'byteOffset' => $i];
                $i++;
            } elseif ($byte < 0xE0) {
                $cp = (($byte & 0x1F) << 6) | (ord($text[$i + 1] ?? "\x00") & 0x3F);
                $out[] = ['codepoint' => $cp, 'byteOffset' => $i];
                $i += 2;
            } elseif ($byte < 0xF0) {
                $cp = (($byte & 0x0F) << 12)
                    | ((ord($text[$i + 1] ?? "\x00") & 0x3F) << 6)
                    | (ord($text[$i + 2] ?? "\x00") & 0x3F);
                $out[] = ['codepoint' => $cp, 'byteOffset' => $i];
                $i += 3;
            } else {
                $cp = (($byte & 0x07) << 18)
                    | ((ord($text[$i + 1] ?? "\x00") & 0x3F) << 12)
                    | ((ord($text[$i + 2] ?? "\x00") & 0x3F) << 6)
                    | (ord($text[$i + 3] ?? "\x00") & 0x3F);
                $out[] = ['codepoint' => $cp, 'byteOffset' => $i];
                $i += 4;
            }
        }
        return $out;
    }
}
