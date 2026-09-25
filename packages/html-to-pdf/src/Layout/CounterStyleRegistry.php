<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * CSS Counter Styles 3 — the counter-style catalogue and the §2 "generate
 * a counter representation" algorithm.
 *
 * One registry holds the predefined styles of §6 / §7 plus any author
 * `@counter-style` rules layered on top. Two entry points, matching the two
 * ways a representation is consumed:
 *
 *  - {@see representation()} — what `counter()` / `counters()` substitute:
 *    the symbols plus the negative sign, and nothing else;
 *  - {@see marker()} — what a `::marker` shows: `prefix`, the
 *    representation, then `suffix`.
 *
 * Getting that split wrong is visible: every CSS 2.1 list reftest writes its
 * expected marker as literal `1. ` text, so the suffix is part of the
 * rendered geometry, while `content: counter(n)` must not carry it.
 */
final class CounterStyleRegistry
{
    /** Guards `fallback` loops — `a` falling back to `b` falling back to `a`. */
    private const int MAX_FALLBACK_DEPTH = 16;

    /** @var array<string, CounterStyleDefinition> */
    private array $styles;

    /** @var array<string, CounterStyleDefinition>|null */
    private static ?array $predefinedCache = null;

    /**
     * @param array<string, CounterStyleDefinition> $authorStyles author
     *        `@counter-style` rules, keyed by lower-cased name. They
     *        override same-named predefined styles (CSS Counter Styles 3
     *        §4 — an author rule may redefine a predefined style).
     */
    public function __construct(array $authorStyles = [])
    {
        $this->styles = $authorStyles + self::predefined();
    }

    public function has(string $name): bool
    {
        return isset($this->styles[strtolower($name)]);
    }

    public function definition(string $name): ?CounterStyleDefinition
    {
        return $this->styles[strtolower($name)] ?? null;
    }

    /**
     * The counter representation for `$value` — symbols plus negative sign,
     * without `prefix` / `suffix`. This is what `counter()` produces.
     */
    public function representation(int $value, string $name): string
    {
        return $this->build($value, strtolower($name), 0) ?? (string) $value;
    }

    /**
     * The full marker string: `prefix` + representation + `suffix`.
     *
     * The affixes come from the style that was ASKED for, even when the
     * representation itself came from a fallback — CSS Counter Styles 3 §2
     * only defers the symbol generation.
     */
    public function marker(int $value, string $name): string
    {
        $key = strtolower($name);
        $def = $this->styles[$key] ?? null;
        if ($def === null) {
            return (string) $value . '. ';
        }
        return $def->prefix . $this->representation($value, $key) . $def->suffix;
    }

    /**
     * CSS Counter Styles 3 §2 — generate a counter representation, or null
     * when neither this style nor its fallback chain can represent `$value`.
     */
    private function build(int $value, string $name, int $depth): ?string
    {
        if ($depth > self::MAX_FALLBACK_DEPTH) {
            return null;
        }
        $def = $this->styles[$name] ?? null;
        if ($def === null) {
            return null;
        }
        [$min, $max] = $def->effectiveRange();
        if ($value < $min || $value > $max) {
            return $this->buildFallback($value, $def, $depth);
        }
        $negative = $value < 0 && $def->usesNegativeSign();
        $core = $this->generate($def, $negative ? -$value : $value);
        if ($core === null) {
            return $this->buildFallback($value, $def, $depth);
        }
        // §4.6 `pad` — the length compared against is the length of the
        // representation INCLUDING the negative sign, and the pad symbols
        // go between the sign and the symbols (`-*I`, never `*-I`).
        $length = mb_strlen($core, 'UTF-8');
        if ($negative) {
            $length += mb_strlen($def->negativePrefix, 'UTF-8')
                + mb_strlen($def->negativeSuffix, 'UTF-8');
        }
        if ($def->padSymbol !== '') {
            $padWidth = mb_strlen($def->padSymbol, 'UTF-8');
            while ($length < $def->padLength && $padWidth > 0) {
                $core = $def->padSymbol . $core;
                $length += $padWidth;
            }
        }
        return $negative
            ? $def->negativePrefix . $core . $def->negativeSuffix
            : $core;
    }

    private function buildFallback(int $value, CounterStyleDefinition $def, int $depth): ?string
    {
        if ($def->fallback === null) {
            return null;
        }
        return $this->build($value, strtolower($def->fallback), $depth + 1);
    }

    /**
     * CSS Counter Styles 3 §3 — the five counter systems, applied to a
     * value already made non-negative where the system requires it.
     * Returns null when the system cannot represent the value at all,
     * which sends the caller to the fallback.
     */
    private function generate(CounterStyleDefinition $def, int $value): ?string
    {
        $symbols = $def->symbols;
        $count = count($symbols);
        switch ($def->system) {
            case CounterStyleDefinition::CYCLIC:
                if ($count === 0) {
                    return null;
                }
                // Wrap in both directions: `(-1 - 1) % 3` is -2 in PHP.
                return $symbols[((($value - 1) % $count) + $count) % $count];

            case CounterStyleDefinition::FIXED:
                $index = $value - $def->fixedFirst;
                return ($index >= 0 && $index < $count) ? $symbols[$index] : null;

            case CounterStyleDefinition::SYMBOLIC:
                if ($count === 0 || $value < 1) {
                    return null;
                }
                return str_repeat(
                    $symbols[($value - 1) % $count],
                    (int) ceil($value / $count),
                );

            case CounterStyleDefinition::ALPHABETIC:
                if ($count < 2 || $value < 1) {
                    return null;
                }
                $out = '';
                while ($value > 0) {
                    $value--;
                    $out = $symbols[$value % $count] . $out;
                    $value = intdiv($value, $count);
                }
                return $out;

            case CounterStyleDefinition::NUMERIC:
                if ($count < 2) {
                    return null;
                }
                if ($value === 0) {
                    return $symbols[0];
                }
                $out = '';
                while ($value > 0) {
                    $out = $symbols[$value % $count] . $out;
                    $value = intdiv($value, $count);
                }
                return $out;

            case CounterStyleDefinition::ADDITIVE:
                return self::additive($def->additiveSymbols, $value);

            case CounterStyleDefinition::CJK_IDEOGRAPHIC:
                return self::cjkIdeographic($def, $value);
        }
        return null;
    }

    /**
     * CSS Counter Styles 3 §3.6 — the additive system. Greedily consume the
     * weight-descending tuple list; a non-zero remainder means the value is
     * not representable and the style must fall back.
     *
     * @param list<array{0: int, 1: string}> $tuples
     */
    private static function additive(array $tuples, int $value): ?string
    {
        if ($tuples === [] || $value < 0) {
            return null;
        }
        if ($value === 0) {
            foreach ($tuples as [$weight, $symbol]) {
                if ($weight === 0) {
                    return $symbol;
                }
            }
            return null;
        }
        $out = '';
        foreach ($tuples as [$weight, $symbol]) {
            if ($weight <= 0 || $weight > $value) {
                continue;
            }
            $repeat = intdiv($value, $weight);
            $out .= str_repeat($symbol, $repeat);
            $value -= $repeat * $weight;
            if ($value === 0) {
                return $out;
            }
        }
        return null;
    }

    /**
     * CSS Counter Styles 3 §7.1 — the East Asian "longhand" numerals, for
     * values 0-9999 (the range every one of these styles declares).
     *
     * Walks the four decimal places from thousands down, emitting
     * `digit + place marker`, with two per-family adjustments:
     *
     *  - a leading `1` before a place marker is dropped by the informal
     *    styles (十 rather than 壹拾), never by the formal ones;
     *  - Chinese inserts the zero digit wherever an interior place was
     *    skipped (一千零五), which is the reason this cannot be the
     *    `additive` system the spec presents it as.
     *
     * Trailing zero places emit nothing at all: 1800 is 一千八百.
     */
    private static function cjkIdeographic(CounterStyleDefinition $def, int $value): ?string
    {
        $digits = $def->symbols;
        $multipliers = $def->cjkMultipliers;
        if (count($digits) < 10 || count($multipliers) < 3 || $value < 0 || $value > 9999) {
            return null;
        }
        if ($value === 0) {
            return $digits[0];
        }
        $out = '';
        $emitted = false;
        $skipped = false;
        for ($place = 3; $place >= 0; $place--) {
            $digit = intdiv($value, 10 ** $place) % 10;
            if ($digit === 0) {
                // Only an INTERIOR zero can call for a filler; a run of
                // leading zeros is not part of the number at all, and a
                // trailing one is simply absent.
                $skipped = $emitted;
                continue;
            }
            if ($skipped && $def->cjkZeroFiller) {
                $out .= $digits[0];
            }
            $skipped = false;
            if ($place === 0 || $digit !== 1 || !self::elidesLeadingOne($def, $place, $emitted)) {
                $out .= $digits[$digit];
            }
            if ($place > 0) {
                $out .= $multipliers[$place - 1];
            }
            $emitted = true;
        }
        return $out;
    }

    private static function elidesLeadingOne(
        CounterStyleDefinition $def,
        int $place,
        bool $emitted,
    ): bool {
        return match ($def->cjkElideOne) {
            CounterStyleDefinition::ELIDE_ALL => true,
            CounterStyleDefinition::ELIDE_LEADING_TENS => $place === 1 && !$emitted,
            default => false,
        };
    }

    // -----------------------------------------------------------------
    // Predefined styles — CSS Counter Styles 3 §6 and §7.
    // -----------------------------------------------------------------

    /** @return array<string, CounterStyleDefinition> */
    public static function predefined(): array
    {
        if (self::$predefinedCache !== null) {
            return self::$predefinedCache;
        }
        $s = [];

        // §6.1 Symbolic. The bullet families cycle a single symbol and use a
        // bare space suffix rather than the default `". "`.
        foreach ([
            'disc' => "\u{2022}",
            'circle' => "\u{25E6}",
            'square' => "\u{25AA}",
            'disclosure-open' => "\u{25BC}",
            'disclosure-closed' => "\u{25B6}",
        ] as $name => $glyph) {
            $s[$name] = new CounterStyleDefinition(
                system: CounterStyleDefinition::CYCLIC,
                symbols: [$glyph],
                suffix: ' ',
            );
        }

        // §6.2 Numeric — positional systems over a script's decimal digits.
        $s['decimal'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::NUMERIC,
            symbols: self::digits(0x0030),
        );
        $s['decimal-leading-zero'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::NUMERIC,
            symbols: self::digits(0x0030),
            padLength: 2,
            padSymbol: '0',
        );
        foreach ([
            'arabic-indic' => 0x0660,
            'bengali' => 0x09E6,
            'devanagari' => 0x0966,
            'gujarati' => 0x0AE6,
            'gurmukhi' => 0x0A66,
            'kannada' => 0x0CE6,
            'khmer' => 0x17E0,
            'lao' => 0x0ED0,
            'malayalam' => 0x0D66,
            'mongolian' => 0x1810,
            'myanmar' => 0x1040,
            'oriya' => 0x0B66,
            'persian' => 0x06F0,
            'tamil' => 0x0BE6,
            'telugu' => 0x0C66,
            'thai' => 0x0E50,
            'tibetan' => 0x0F20,
        ] as $name => $zero) {
            $s[$name] = new CounterStyleDefinition(
                system: CounterStyleDefinition::NUMERIC,
                symbols: self::digits($zero),
            );
        }
        // `cambodian` is defined as `system: extends khmer`.
        $s['cambodian'] = $s['khmer'];
        $s['cjk-decimal'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::NUMERIC,
            symbols: [
                "\u{3007}", "\u{4E00}", "\u{4E8C}", "\u{4E09}", "\u{56DB}",
                "\u{4E94}", "\u{516D}", "\u{4E03}", "\u{516B}", "\u{4E5D}",
            ],
            suffix: "\u{3001}",
            rangeMin: 0,
        );

        // §6.2 Numeric, additive spellings.
        $roman = [
            [1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'],
            [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'],
            [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I'],
        ];
        $s['upper-roman'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ADDITIVE,
            additiveSymbols: $roman,
            rangeMin: 1,
            rangeMax: 3999,
        );
        $s['lower-roman'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ADDITIVE,
            additiveSymbols: array_map(
                static fn(array $t): array => [$t[0], strtolower($t[1])],
                $roman,
            ),
            rangeMin: 1,
            rangeMax: 3999,
        );
        $armenian = self::armenianTuples();
        $s['upper-armenian'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ADDITIVE,
            additiveSymbols: $armenian,
            rangeMin: 1,
            rangeMax: 9999,
        );
        $s['armenian'] = $s['upper-armenian'];
        $s['lower-armenian'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ADDITIVE,
            additiveSymbols: array_map(
                static fn(array $t): array => [$t[0], mb_strtolower($t[1], 'UTF-8')],
                $armenian,
            ),
            rangeMin: 1,
            rangeMax: 9999,
        );
        $s['georgian'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ADDITIVE,
            additiveSymbols: self::georgianTuples(),
            rangeMin: 1,
            rangeMax: 19999,
        );
        $s['hebrew'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ADDITIVE,
            additiveSymbols: self::hebrewTuples(),
            rangeMin: 1,
            rangeMax: 10999,
        );

        // §6.3 Alphabetic — bijective numbering over a symbol list.
        $s['lower-alpha'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ALPHABETIC,
            symbols: range('a', 'z'),
        );
        $s['lower-latin'] = $s['lower-alpha'];
        $s['upper-alpha'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ALPHABETIC,
            symbols: range('A', 'Z'),
        );
        $s['upper-latin'] = $s['upper-alpha'];
        $s['lower-greek'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::ALPHABETIC,
            symbols: [
                'α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ',
                'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω',
            ],
        );
        foreach ([
            'hiragana' => [
                'あ','い','う','え','お','か','き','く','け','こ',
                'さ','し','す','せ','そ','た','ち','つ','て','と',
                'な','に','ぬ','ね','の','は','ひ','ふ','へ','ほ',
                'ま','み','む','め','も','や','ゆ','よ','ら','り',
                'る','れ','ろ','わ','ゐ','ゑ','を','ん',
            ],
            'hiragana-iroha' => [
                'い','ろ','は','に','ほ','へ','と','ち','り','ぬ',
                'る','を','わ','か','よ','た','れ','そ','つ','ね',
                'な','ら','む','う','ゐ','の','お','く','や','ま',
                'け','ふ','こ','え','て','あ','さ','き','ゆ','め',
                'み','し','ゑ','ひ','も','せ','す',
            ],
            'katakana' => [
                'ア','イ','ウ','エ','オ','カ','キ','ク','ケ','コ',
                'サ','シ','ス','セ','ソ','タ','チ','ツ','テ','ト',
                'ナ','ニ','ヌ','ネ','ノ','ハ','ヒ','フ','ヘ','ホ',
                'マ','ミ','ム','メ','モ','ヤ','ユ','ヨ','ラ','リ',
                'ル','レ','ロ','ワ','ヰ','ヱ','ヲ','ン',
            ],
            'katakana-iroha' => [
                'イ','ロ','ハ','ニ','ホ','ヘ','ト','チ','リ','ヌ',
                'ル','ヲ','ワ','カ','ヨ','タ','レ','ソ','ツ','ネ',
                'ナ','ラ','ム','ウ','ヰ','ノ','オ','ク','ヤ','マ',
                'ケ','フ','コ','エ','テ','ア','サ','キ','ユ','メ',
                'ミ','シ','ヱ','ヒ','モ','セ','ス',
            ],
        ] as $name => $kana) {
            $s[$name] = new CounterStyleDefinition(
                system: CounterStyleDefinition::ALPHABETIC,
                symbols: $kana,
                suffix: "\u{3001}",
            );
        }

        // §6.5 Fixed — a finite sequence that falls back once exhausted.
        $s['cjk-earthly-branch'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::FIXED,
            symbols: [
                "\u{5B50}", "\u{4E11}", "\u{5BC5}", "\u{536F}",
                "\u{8FB0}", "\u{5DF3}", "\u{5348}", "\u{672A}",
                "\u{7533}", "\u{9149}", "\u{620C}", "\u{4EA5}",
            ],
            suffix: "\u{3001}",
        );
        $s['cjk-heavenly-stem'] = new CounterStyleDefinition(
            system: CounterStyleDefinition::FIXED,
            symbols: [
                "\u{7532}", "\u{4E59}", "\u{4E19}", "\u{4E01}", "\u{620A}",
                "\u{5DF1}", "\u{5E9A}", "\u{8F9B}", "\u{58EC}", "\u{7678}",
            ],
            suffix: "\u{3001}",
        );

        // §7.1 East Asian. Every one declares `range: -9999 9999` and
        // `fallback: cjk-decimal`, so 10000 renders as 一〇〇〇〇 rather
        // than reaching for the 万 the optional extended range would use.
        $ja = "\u{30DE}\u{30A4}\u{30CA}\u{30B9}";        // マイナス
        $ko = "\u{B9C8}\u{C774}\u{B108}\u{C2A4}\u{0020}"; // 마이너스␣
        $ideographicComma = "\u{3001}";
        foreach ([
            'japanese-informal' => [
                "\u{3007}\u{4E00}\u{4E8C}\u{4E09}\u{56DB}\u{4E94}\u{516D}\u{4E03}\u{516B}\u{4E5D}",
                "\u{5341}\u{767E}\u{5343}",
                CounterStyleDefinition::ELIDE_ALL, false, $ideographicComma, $ja,
            ],
            'japanese-formal' => [
                "\u{96F6}\u{58F1}\u{5F10}\u{53C2}\u{56DB}\u{4F0D}\u{516D}\u{4E03}\u{516B}\u{4E5D}",
                "\u{62FE}\u{767E}\u{9621}",
                CounterStyleDefinition::ELIDE_NONE, false, $ideographicComma, $ja,
            ],
            'simp-chinese-informal' => [
                "\u{96F6}\u{4E00}\u{4E8C}\u{4E09}\u{56DB}\u{4E94}\u{516D}\u{4E03}\u{516B}\u{4E5D}",
                "\u{5341}\u{767E}\u{5343}",
                CounterStyleDefinition::ELIDE_LEADING_TENS, true, $ideographicComma, "\u{8D1F}",
            ],
            'simp-chinese-formal' => [
                "\u{96F6}\u{58F9}\u{8D30}\u{53C1}\u{8086}\u{4F0D}\u{9646}\u{67D2}\u{634C}\u{7396}",
                "\u{62FE}\u{4F70}\u{4EDF}",
                CounterStyleDefinition::ELIDE_NONE, true, $ideographicComma, "\u{8D1F}",
            ],
            'trad-chinese-informal' => [
                "\u{96F6}\u{4E00}\u{4E8C}\u{4E09}\u{56DB}\u{4E94}\u{516D}\u{4E03}\u{516B}\u{4E5D}",
                "\u{5341}\u{767E}\u{5343}",
                CounterStyleDefinition::ELIDE_LEADING_TENS, true, $ideographicComma, "\u{8CA0}",
            ],
            'trad-chinese-formal' => [
                "\u{96F6}\u{58F9}\u{8CB3}\u{53C3}\u{8086}\u{4F0D}\u{9678}\u{67D2}\u{634C}\u{7396}",
                "\u{62FE}\u{4F70}\u{4EDF}",
                CounterStyleDefinition::ELIDE_NONE, true, $ideographicComma, "\u{8CA0}",
            ],
            'korean-hangul-formal' => [
                "\u{C601}\u{C77C}\u{C774}\u{C0BC}\u{C0AC}\u{C624}\u{C721}\u{CE60}\u{D314}\u{AD6C}",
                "\u{C2ED}\u{BC31}\u{CC9C}",
                CounterStyleDefinition::ELIDE_NONE, false, ', ', $ko,
            ],
            'korean-hanja-informal' => [
                "\u{96F6}\u{4E00}\u{4E8C}\u{4E09}\u{56DB}\u{4E94}\u{516D}\u{4E03}\u{516B}\u{4E5D}",
                "\u{5341}\u{767E}\u{5343}",
                CounterStyleDefinition::ELIDE_ALL, false, ', ', $ko,
            ],
            'korean-hanja-formal' => [
                "\u{96F6}\u{58F9}\u{8CB3}\u{53C3}\u{56DB}\u{4E94}\u{516D}\u{4E03}\u{516B}\u{4E5D}",
                "\u{62FE}\u{767E}\u{4EDF}",
                CounterStyleDefinition::ELIDE_NONE, false, ', ', $ko,
            ],
        ] as $name => [$digitRun, $multiplierRun, $elide, $zeroFiller, $styleSuffix, $negative]) {
            $s[$name] = new CounterStyleDefinition(
                system: CounterStyleDefinition::CJK_IDEOGRAPHIC,
                symbols: mb_str_split($digitRun, 1, 'UTF-8'),
                negativePrefix: $negative,
                suffix: $styleSuffix,
                rangeMin: -9999,
                rangeMax: 9999,
                // The Chinese and Japanese styles fall back to cjk-decimal;
                // the Korean ones declare no fallback and so take the
                // initial `decimal` — 10000 is 一〇〇〇〇 for the former and
                // plain `10000` for the latter.
                fallback: str_starts_with($name, 'korean-') ? 'decimal' : 'cjk-decimal',
                cjkMultipliers: mb_str_split($multiplierRun, 1, 'UTF-8'),
                cjkElideOne: $elide,
                cjkZeroFiller: $zeroFiller,
            );
        }

        self::$predefinedCache = $s;
        return $s;
    }

    /**
     * The ten decimal digits of a script, given the codepoint of its ZERO.
     * Every §6.2 "simple numeric" style is exactly this — spelling the
     * tables out by hand is how transcription typos get shipped.
     *
     * @return list<string>
     */
    private static function digits(int $zero): array
    {
        $out = [];
        for ($i = 0; $i < 10; $i++) {
            $out[] = \IntlChar::chr($zero + $i) ?? '?';
        }
        return $out;
    }

    /** @return list<array{0: int, 1: string}> */
    private static function armenianTuples(): array
    {
        $ones = ['Ա','Բ','Գ','Դ','Ե','Զ','Է','Ը','Թ'];
        $tens = ['Ժ','Ի','Լ','Խ','Ծ','Կ','Հ','Ձ','Ղ'];
        $hundreds = ['Ճ','Մ','Յ','Ն','Շ','Ո','Չ','Պ','Ջ'];
        $thousands = ['Ռ','Ս','Վ','Տ','Ր','Ց','Ւ','Փ','Ք'];
        $out = [];
        foreach ([[1000, $thousands], [100, $hundreds], [10, $tens], [1, $ones]] as [$scale, $list]) {
            for ($i = 9; $i >= 1; $i--) {
                $out[] = [$scale * $i, $list[$i - 1]];
            }
        }
        usort($out, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
        return $out;
    }

    /** @return list<array{0: int, 1: string}> */
    private static function georgianTuples(): array
    {
        $ones = ['ა','ბ','გ','დ','ე','ვ','ზ','ჱ','თ'];
        $tens = ['ი','კ','ლ','მ','ნ','ჲ','ო','პ','ჟ'];
        $hundreds = ['რ','ს','ტ','ჳ','ფ','ქ','ღ','ყ','შ'];
        $thousands = ['ჩ','ც','ძ','წ','ჭ','ხ','ჴ','ჯ','ჰ'];
        $out = [[10000, 'ჵ']];
        foreach ([[1000, $thousands], [100, $hundreds], [10, $tens], [1, $ones]] as [$scale, $list]) {
            for ($i = 9; $i >= 1; $i--) {
                $out[] = [$scale * $i, $list[$i - 1]];
            }
        }
        usort($out, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
        return $out;
    }

    /**
     * CSS Counter Styles 3 §6.2 `hebrew`. 15 and 16 are spelled טו / טז
     * rather than the theophoric יה / יו, which is exactly why the style
     * is additive with explicit 15/16 tuples instead of positional.
     *
     * @return list<array{0: int, 1: string}>
     */
    private static function hebrewTuples(): array
    {
        return [
            [10000, 'י׳'], [9000, 'ט׳'], [8000, 'ח׳'], [7000, 'ז׳'],
            [6000, 'ו׳'], [5000, 'ה׳'], [4000, 'ד׳'], [3000, 'ג׳'],
            [2000, 'ב׳'], [1000, 'א׳'],
            [400, 'ת'], [300, 'ש'], [200, 'ר'], [100, 'ק'],
            [90, 'צ'], [80, 'פ'], [70, 'ע'], [60, 'ס'], [50, 'נ'],
            [40, 'מ'], [30, 'ל'], [20, 'כ'],
            [19, 'יט'], [18, 'יח'], [17, 'יז'], [16, 'טז'], [15, 'טו'],
            [10, 'י'],
            [9, 'ט'], [8, 'ח'], [7, 'ז'], [6, 'ו'], [5, 'ה'],
            [4, 'ד'], [3, 'ג'], [2, 'ב'], [1, 'א'],
        ];
    }
}
