<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

/**
 * Simplified UAX #9 reordering pass for mixed-direction MathML
 * token content.
 *
 * Splits a UTF-8 string into runs of consistent strong direction
 * (LTR vs RTL, with neutrals attached to whichever direction
 * surrounds them) and then re-emits the runs in visual order:
 *
 *   - LTR paragraph: source-order runs, each LTR run kept as-is,
 *     each RTL run reversed in place.
 *   - RTL paragraph: reverse the run order, then reverse RTL runs
 *     in place; LTR runs stay internally LTR.
 *
 * This handles the most common embedded-RTL-in-LTR-paragraph case
 * (a Hebrew word inside an English sentence and vice versa)
 * without taking on the full UAX #9 weak-type / neutral
 * resolution machinery. Numeric runs (digits) stay attached to
 * the surrounding direction since BidiAnalyzer reports them
 * neutral.
 *
 * For pure-LTR or pure-RTL content the cheap path in
 * {@see Translator::emitText()} already handles it; this helper
 * fires only for `'mixed'` runs.
 */
final class BidiReorder
{
    /**
     * Reorder a mixed-direction UTF-8 string into visual order for
     * a paragraph rendered in `$paragraphDir` direction (LTR or
     * RTL). Returns the input unchanged when the run isn't mixed.
     */
    public static function reorder(
        string $utf8,
        string $paragraphDir = BidiAnalyzer::DIRECTION_LTR,
    ): string {
        if (BidiAnalyzer::runDirection($utf8) !== 'mixed') {
            return $utf8;
        }
        $runs = self::splitIntoRuns($utf8);

        if ($paragraphDir === BidiAnalyzer::DIRECTION_RTL) {
            // For RTL paragraphs, the visual order is right-to-left
            // at the run level, so the run sequence reverses.
            $runs = array_reverse($runs);
        }

        $output = '';
        foreach ($runs as $run) {
            if ($run['dir'] === BidiAnalyzer::DIRECTION_RTL) {
                // RTL runs always reverse internally.
                $output .= self::reverseUtf8($run['text']);
            } else {
                $output .= $run['text'];
            }
        }
        return $output;
    }

    /**
     * Split into directional runs. Strong-direction codepoints
     * form runs of their own direction; neutrals (whitespace,
     * punctuation, marks) collect into their own neutral runs so
     * they keep their position when surrounding strong runs
     * reverse. This mirrors the UAX #9 outcome for the simple case
     * where neutrals between runs of different direction can split
     * (rule N1).
     *
     * @return list<array{dir: string, text: string}>
     */
    private static function splitIntoRuns(string $utf8): array
    {
        $runs = [];
        // Use empty string as a sentinel "no current run" marker.
        $currentDir = '';
        $currentText = '';
        $flush = function () use (&$runs, &$currentDir, &$currentText): void {
            if ($currentText !== '') {
                $runs[] = ['dir' => $currentDir, 'text' => $currentText];
                $currentText = '';
            }
        };
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                if ($currentDir !== BidiAnalyzer::DIRECTION_NEUTRAL) {
                    $flush();
                    $currentDir = BidiAnalyzer::DIRECTION_NEUTRAL;
                }
                $currentText .= $char;
                continue;
            }
            $strong = BidiAnalyzer::directionOf($cp);
            $charDir = $strong ?? BidiAnalyzer::DIRECTION_NEUTRAL;
            if ($charDir !== $currentDir) {
                $flush();
                $currentDir = $charDir;
            }
            $currentText .= $char;
        }
        $flush();
        return $runs;
    }

    /**
     * Reverse a UTF-8 string by codepoint. PHP's strrev() works on
     * bytes and would corrupt multi-byte glyphs.
     */
    private static function reverseUtf8(string $utf8): string
    {
        return implode('', array_reverse(mb_str_split($utf8, 1, 'UTF-8')));
    }
}
