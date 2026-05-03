<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Simple text shaper for Latin ligature substitution.
 *
 * Given a GID sequence, applies GSUB ligature rules to produce
 * the shaped GID sequence. This is NOT a full shaping engine —
 * it handles standard Latin ligatures (fi, fl, ffi, ffl, etc.)
 * but does not support Arabic joining, Indic reordering, mark
 * positioning, or other complex script features.
 */
final class TextShaper
{
    /**
     * Apply ligature substitutions to a GID sequence.
     *
     * @param int[] $gids Input glyph IDs
     * @param array<int, list<array{components: int[], ligature: int}>> $ligatures
     *     Ligature rules from GsubParser, keyed by first GID
     * @return int[] Shaped glyph IDs (may be shorter if ligatures were applied)
     */
    public static function applyLigatures(array $gids, array $ligatures): array
    {
        if ($gids === [] || $ligatures === []) {
            return $gids;
        }

        $result = [];
        $i = 0;
        $len = count($gids);

        while ($i < $len) {
            $gid = $gids[$i];

            if (isset($ligatures[$gid])) {
                $matched = false;

                // Try each ligature rule (sorted longest-first by GsubParser)
                foreach ($ligatures[$gid] as $rule) {
                    $components = $rule['components'];
                    $compLen = count($components);

                    // Check if enough glyphs remain
                    if ($i + $compLen >= $len) {
                        continue;
                    }

                    // Check if component GIDs match
                    $match = true;
                    for ($j = 0; $j < $compLen; $j++) {
                        if ($gids[$i + 1 + $j] !== $components[$j]) {
                            $match = false;
                            break;
                        }
                    }

                    if ($match) {
                        $result[] = $rule['ligature'];
                        $i += 1 + $compLen; // skip first glyph + all components
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $result[] = $gid;
                    $i++;
                }
            } else {
                $result[] = $gid;
                $i++;
            }
        }

        return $result;
    }
}
