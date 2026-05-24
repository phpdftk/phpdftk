<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Aztec "high-level" encoder — text input → optimal bit stream using the five
 * sub-alphabets (Upper, Lower, Mixed, Punct, Digit) plus Binary Shift fallback.
 *
 * Implements a Dijkstra-style state search per AIM ISO/IEC 24778: for each
 * input byte, every candidate state expands into multiple successor states
 * (stay in mode, latch to another mode, shift to another mode, enter / extend
 * binary shift). After each step we prune states that are strictly dominated
 * by a cheaper alternative; at the end of the input the minimum-bit-count
 * terminal state wins and is materialised into a bit array.
 *
 * Algorithm and constants follow ZXing.NET's `HighLevelEncoder` / `State`
 * reference encoder, which is the de-facto reference for spec-compliant Aztec.
 *
 * @internal
 */
final class AztecHighLevelEncoder
{
    public const MODE_UPPER = 0;
    public const MODE_LOWER = 1;
    public const MODE_DIGIT = 2;
    public const MODE_MIXED = 3;
    public const MODE_PUNCT = 4;

    /**
     * `LATCH_TABLE[fromMode][toMode]` encodes the cheapest mode transition.
     * Low 16 bits = the actual bits to emit; high 16 bits = the bit width of
     * that emission. 0 means "already in `toMode`, no transition needed".
     *
     * @var list<list<int>>
     */
    public const LATCH_TABLE = [
        // From UPPER
        [
            0,
            (5 << 16) + 28,                                  // → LOWER
            (5 << 16) + 30,                                  // → DIGIT
            (5 << 16) + 29,                                  // → MIXED
            (10 << 16) + (29 << 5) + 30,                     // → MIXED → PUNCT
        ],
        // From LOWER
        [
            (9 << 16) + (30 << 4) + 14,                      // → DIGIT → UPPER (shift)
            0,
            (5 << 16) + 30,                                  // → DIGIT
            (5 << 16) + 29,                                  // → MIXED
            (10 << 16) + (29 << 5) + 30,                     // → MIXED → PUNCT
        ],
        // From DIGIT
        [
            (4 << 16) + 14,                                  // → UPPER (4 bits in DIGIT)
            (9 << 16) + (14 << 5) + 28,                      // → UPPER → LOWER
            0,
            (9 << 16) + (14 << 5) + 29,                      // → UPPER → MIXED
            (14 << 16) + (14 << 10) + (29 << 5) + 30,        // → UPPER → MIXED → PUNCT
        ],
        // From MIXED
        [
            (5 << 16) + 29,                                  // → UPPER
            (5 << 16) + 28,                                  // → LOWER
            (10 << 16) + (29 << 5) + 30,                     // → UPPER → DIGIT
            0,
            (5 << 16) + 30,                                  // → PUNCT
        ],
        // From PUNCT
        [
            (5 << 16) + 31,                                  // → UPPER
            (10 << 16) + (31 << 5) + 28,                     // → UPPER → LOWER
            (10 << 16) + (31 << 5) + 30,                     // → UPPER → DIGIT
            (10 << 16) + (31 << 5) + 29,                     // → UPPER → MIXED
            0,
        ],
    ];

    /**
     * Lazy-built character maps: CHAR_MAP[mode][byte] = encoded value (1-based;
     * 0 means "not in this table"). Bit width is 4 for DIGIT, 5 for all others.
     *
     * @var array<int, array<int, int>>
     */
    private static array $charMap = [];

    /**
     * SHIFT_TABLE[fromMode][toMode] = shift code emitted in the FROM mode's
     * bit width, after which one character is emitted in the TO mode's 5-bit
     * width. -1 means no shift exists between those modes.
     *
     * @var array<int, array<int, int>>
     */
    private static array $shiftTable = [];

    private static bool $initialised = false;

    /**
     * Encode `$text` as the optimal Aztec bit stream.
     *
     * @return list<int> Bit stream, MSB first within each field.
     */
    public static function encode(string $text): array
    {
        self::initTables();

        $states = [AztecState::initial()];
        $n = strlen($text);
        for ($i = 0; $i < $n; $i++) {
            $pairCode = self::detectPunctPair($text, $i, $n);
            if ($pairCode > 0) {
                $states = self::updateStatesForPair($states, $i, $pairCode);
                $i++; // pair consumes 2 input bytes
            } else {
                $states = self::updateStatesForChar($states, $text, $i);
            }
        }

        // Pick the lowest-bit-count terminal state, then materialise its
        // token list into a bit stream.
        $best = null;
        foreach ($states as $s) {
            if ($best === null || $s->bitCount < $best->bitCount) {
                $best = $s;
            }
        }
        if ($best === null) {
            throw new \RuntimeException('Aztec high-level encoder produced no candidate states.');
        }
        return $best->toBits($text);
    }

    public static function charMap(int $mode, int $byte): int
    {
        self::initTables();
        return self::$charMap[$mode][$byte] ?? 0;
    }

    public static function shift(int $fromMode, int $toMode): int
    {
        self::initTables();
        return self::$shiftTable[$fromMode][$toMode] ?? -1;
    }

    public static function modeBitCount(int $mode): int
    {
        return $mode === self::MODE_DIGIT ? 4 : 5;
    }

    /**
     * Detect the 2-character punctuation pairs that Aztec compresses into a
     * single PUNCT codeword (values 2–5):
     *   "\r\n" → 2, ". " → 3, ", " → 4, ": " → 5.
     */
    private static function detectPunctPair(string $text, int $i, int $n): int
    {
        if ($i + 1 >= $n) {
            return 0;
        }
        $a = $text[$i];
        $b = $text[$i + 1];
        return match (true) {
            $a === "\r" && $b === "\n" => 2,
            $a === '.' && $b === ' ' => 3,
            $a === ',' && $b === ' ' => 4,
            $a === ':' && $b === ' ' => 5,
            default => 0,
        };
    }

    /**
     * @param list<AztecState> $states
     * @return list<AztecState>
     */
    private static function updateStatesForChar(array $states, string $text, int $index): array
    {
        $byte = ord($text[$index]);
        $result = [];
        foreach ($states as $state) {
            self::expandStateForChar($state, $byte, $index, $result);
        }
        return self::simplify($result);
    }

    /**
     * @param list<AztecState> &$result
     */
    private static function expandStateForChar(AztecState $state, int $byte, int $index, array &$result): void
    {
        $inCurrent = (self::$charMap[$state->mode][$byte] ?? 0) > 0;
        $endedBinary = null;
        for ($mode = 0; $mode <= self::MODE_PUNCT; $mode++) {
            $value = self::$charMap[$mode][$byte] ?? 0;
            if ($value === 0) {
                continue;
            }
            if ($endedBinary === null) {
                $endedBinary = $state->endBinaryShift($index);
            }
            // Latch: only worthwhile if the char isn't already in our table, or
            // we're staying in mode, or we're moving to DIGIT (4-bit codewords).
            if (!$inCurrent || $mode === $state->mode || $mode === self::MODE_DIGIT) {
                $result[] = $endedBinary->latchAndAppend($mode, $value);
            }
            // Shift: only worthwhile if the char isn't already in our table.
            if (!$inCurrent && self::$shiftTable[$state->mode][$mode] >= 0) {
                $result[] = $endedBinary->shiftAndAppend($mode, $value);
            }
        }
        // Binary shift: only worthwhile if we're already in B/S OR the char has
        // no representation in the current mode's table.
        if ($state->binaryShiftByteCount > 0 || (self::$charMap[$state->mode][$byte] ?? 0) === 0) {
            $result[] = $state->addBinaryShiftChar($index);
        }
    }

    /**
     * @param list<AztecState> $states
     * @return list<AztecState>
     */
    private static function updateStatesForPair(array $states, int $index, int $pairCode): array
    {
        $result = [];
        foreach ($states as $state) {
            $endedBinary = $state->endBinaryShift($index);
            // Possibility 1: latch to PUNCT and emit pairCode.
            $result[] = $endedBinary->latchAndAppend(self::MODE_PUNCT, $pairCode);
            // Possibility 2: shift to PUNCT (if not already there).
            if ($state->mode !== self::MODE_PUNCT) {
                $result[] = $endedBinary->shiftAndAppend(self::MODE_PUNCT, $pairCode);
            }
            // Possibility 3: for ". " or ", " we can stay in DIGIT mode.
            if ($pairCode === 3 || $pairCode === 4) {
                $digit = $endedBinary
                    ->latchAndAppend(self::MODE_DIGIT, 16 - $pairCode) // . = 13, , = 12
                    ->latchAndAppend(self::MODE_DIGIT, 1);              // space in DIGIT
                $result[] = $digit;
            }
            // Possibility 4: extend an active binary shift by 2 bytes.
            if ($state->binaryShiftByteCount > 0) {
                $result[] = $state->addBinaryShiftChar($index)->addBinaryShiftChar($index + 1);
            }
        }
        return self::simplify($result);
    }

    /**
     * Remove strictly dominated states from a candidate list.
     *
     * @param list<AztecState> $states
     * @return list<AztecState>
     */
    private static function simplify(array $states): array
    {
        $keep = [];
        foreach ($states as $newState) {
            $add = true;
            foreach ($keep as $j => $oldState) {
                if ($oldState->isBetterThanOrEqualTo($newState)) {
                    $add = false;
                    break;
                }
                if ($newState->isBetterThanOrEqualTo($oldState)) {
                    unset($keep[$j]);
                }
            }
            if ($add) {
                $keep[] = $newState;
            }
        }
        return array_values($keep);
    }

    /**
     * Populate the static CHAR_MAP and SHIFT_TABLE on first use.
     */
    private static function initTables(): void
    {
        if (self::$initialised) {
            return;
        }
        for ($m = 0; $m <= 4; $m++) {
            self::$charMap[$m] = array_fill(0, 256, 0);
        }

        // Upper: ' ' = 1, A..Z = 2..27
        self::$charMap[self::MODE_UPPER][ord(' ')] = 1;
        for ($c = ord('A'); $c <= ord('Z'); $c++) {
            self::$charMap[self::MODE_UPPER][$c] = $c - ord('A') + 2;
        }
        // Lower: ' ' = 1, a..z = 2..27
        self::$charMap[self::MODE_LOWER][ord(' ')] = 1;
        for ($c = ord('a'); $c <= ord('z'); $c++) {
            self::$charMap[self::MODE_LOWER][$c] = $c - ord('a') + 2;
        }
        // Digit: ' ' = 1, 0..9 = 2..11, ',' = 12, '.' = 13
        self::$charMap[self::MODE_DIGIT][ord(' ')] = 1;
        for ($c = ord('0'); $c <= ord('9'); $c++) {
            self::$charMap[self::MODE_DIGIT][$c] = $c - ord('0') + 2;
        }
        self::$charMap[self::MODE_DIGIT][ord(',')] = 12;
        self::$charMap[self::MODE_DIGIT][ord('.')] = 13;

        // Mixed: control chars and shifted symbols.
        $mixed = [
            0x00, 0x20,    1,    2,    3,    4,    5,    6,    7, 0x08,
            0x09, 0x0A,   11, 0x0C, 0x0D,   27,   28,   29,   30,   31,
            ord('@'), ord('\\'), ord('^'), ord('_'), ord('`'), ord('|'), ord('~'), 0x7F,
        ];
        foreach ($mixed as $i => $value) {
            self::$charMap[self::MODE_MIXED][$value] = $i;
        }

        // Punct: position 0 = FLG(n), 2..5 are pair codes (unused for chars),
        // 31 = latch to UPPER. Values are per AIM ISO/IEC 24778; we deviate
        // from ZXing.NET's source which has a typo (`'\''` at index 7 instead
        // of `"`) — the spec table has `"` at 7 and `'` at 12.
        $punct = [
            0x00, ord("\r"), 0x00, 0x00, 0x00, 0x00,
            ord('!'), ord('"'), ord('#'), ord('$'), ord('%'), ord('&'),
            ord("'"), ord('('), ord(')'), ord('*'), ord('+'), ord(','),
            ord('-'), ord('.'), ord('/'), ord(':'), ord(';'), ord('<'),
            ord('='), ord('>'), ord('?'), ord('['), ord(']'), ord('{'), ord('}'),
        ];
        foreach ($punct as $i => $value) {
            if ($value > 0) {
                self::$charMap[self::MODE_PUNCT][$value] = $i;
            }
        }

        // Shift table: only specific transitions have a single-char shift code.
        for ($m = 0; $m <= 4; $m++) {
            self::$shiftTable[$m] = array_fill(0, 5, -1);
        }
        self::$shiftTable[self::MODE_UPPER][self::MODE_PUNCT] = 0;
        self::$shiftTable[self::MODE_LOWER][self::MODE_PUNCT] = 0;
        self::$shiftTable[self::MODE_LOWER][self::MODE_UPPER] = 28;
        self::$shiftTable[self::MODE_MIXED][self::MODE_PUNCT] = 0;
        self::$shiftTable[self::MODE_DIGIT][self::MODE_PUNCT] = 0;
        self::$shiftTable[self::MODE_DIGIT][self::MODE_UPPER] = 15;

        self::$initialised = true;
    }
}
