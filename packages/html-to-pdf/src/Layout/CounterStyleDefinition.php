<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * One CSS Counter Styles 3 counter style — the resolved form of an
 * `@counter-style` rule (§4) or of a predefined style (§6, §7).
 *
 * `system: extends …` is resolved away before a definition is built: the
 * extending style copies every descriptor it did not itself specify, so by
 * the time the formatter sees a definition it is always one of the five
 * concrete systems.
 */
final class CounterStyleDefinition
{
    public const string CYCLIC = 'cyclic';
    public const string NUMERIC = 'numeric';
    public const string ALPHABETIC = 'alphabetic';
    public const string SYMBOLIC = 'symbolic';
    public const string ADDITIVE = 'additive';
    public const string FIXED = 'fixed';

    /**
     * Not a CSS `system` value — the internal marker for the East Asian
     * styles of CSS Counter Styles 3 §7.1 (Chinese / Japanese / Korean,
     * informal and formal).
     *
     * The spec presents those styles as `additive`, but a greedy additive
     * pass cannot produce the Chinese zero filler: 101 must be 一百零一,
     * and additive gives 一百一. They are generated place-by-place instead.
     */
    public const string CJK_IDEOGRAPHIC = 'cjk-ideographic';

    /** Never drop a `1` before a place multiplier — 10 is 壹拾. */
    public const string ELIDE_NONE = 'none';
    /** Always drop it — 10 is 十, 100 is 百, 1000 is 千. */
    public const string ELIDE_ALL = 'all';
    /** Drop it only in a leading tens place — 10 is 十 but 100 is 一百. */
    public const string ELIDE_LEADING_TENS = 'leading-tens';

    /**
     * @param list<string>                $symbols         `symbols` descriptor, in order.
     * @param list<array{0: int, 1: string}> $additiveSymbols `additive-symbols`, weight-descending.
     */
    public function __construct(
        public readonly string $system,
        public readonly array $symbols = [],
        public readonly array $additiveSymbols = [],
        public readonly string $negativePrefix = '-',
        public readonly string $negativeSuffix = '',
        public readonly string $prefix = '',
        /**
         * CSS Counter Styles 3 §4.7 — the initial `suffix` is a full stop
         * followed by a space, which is why every list marker in the CSS 2.1
         * reference files reads `1. ` and not `1.`.
         */
        public readonly string $suffix = '. ',
        /** Null means `range: auto`, which each system resolves for itself. */
        public readonly ?int $rangeMin = null,
        public readonly ?int $rangeMax = null,
        public readonly int $padLength = 0,
        public readonly string $padSymbol = '',
        public readonly ?string $fallback = 'decimal',
        /** `system: fixed <first>` — the counter value the first symbol represents. */
        public readonly int $fixedFirst = 1,
        /**
         * {@see CJK_IDEOGRAPHIC} only — the tens / hundreds / thousands place
         * markers (十 百 千, or their formal 拾 佰 仟 variants).
         *
         * @var list<string>
         */
        public readonly array $cjkMultipliers = [],
        /** {@see CJK_IDEOGRAPHIC} only — one of the `ELIDE_*` constants. */
        public readonly string $cjkElideOne = self::ELIDE_NONE,
        /**
         * {@see CJK_IDEOGRAPHIC} only — insert the zero digit where an
         * interior place was skipped. True for Chinese, false for Japanese
         * and Korean: 1005 is 一千零五 but 千五.
         */
        public readonly bool $cjkZeroFiller = false,
    ) {}

    /**
     * CSS Counter Styles 3 §4.4 — the `range: auto` resolution. Each system
     * knows which counter values it can represent at all; outside that the
     * style must defer to its `fallback`.
     *
     * @return array{0: int, 1: int}
     */
    public function effectiveRange(): array
    {
        if ($this->rangeMin !== null || $this->rangeMax !== null) {
            return [$this->rangeMin ?? PHP_INT_MIN, $this->rangeMax ?? PHP_INT_MAX];
        }
        return match ($this->system) {
            self::ALPHABETIC, self::SYMBOLIC => [1, PHP_INT_MAX],
            self::ADDITIVE => [0, PHP_INT_MAX],
            default => [PHP_INT_MIN, PHP_INT_MAX],
        };
    }

    /**
     * Does this system spell negative values with the `negative` descriptor?
     *
     * Cyclic and fixed index their symbol list by the counter value itself,
     * so a negative value is a position in the cycle rather than a signed
     * magnitude; every other system represents magnitudes and needs the sign.
     */
    public function usesNegativeSign(): bool
    {
        return $this->system !== self::CYCLIC && $this->system !== self::FIXED;
    }
}
