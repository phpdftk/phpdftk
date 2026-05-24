<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * UAX #14 line breaking via ICU's `IntlBreakIterator::createLineInstance`.
 *
 * Returns positions where a soft wrap may occur (in addition to mandatory
 * line terminators). Locale-sensitive: CJK locales apply kinsoku rules
 * forbidding certain leading / trailing punctuation, while Latin locales
 * mainly key off whitespace and hyphenation points.
 *
 * The line-break engine is structural — it doesn't measure text width or
 * decide which opportunities to actually break on. The layout consumer
 * iterates opportunities and chooses based on the box's available width.
 */
final class LineBreaker
{
    /**
     * Enumerate line-break opportunities for `$text`. The `$locale` is a
     * BCP 47 language tag (e.g. `"en"`, `"ja"`, `"zh-Hans"`). Default is
     * `"en"`. Unknown locales fall back to the ICU root locale, which
     * applies generic UAX #14 with no language-specific tailoring.
     */
    public function breakOpportunities(string $text, string $locale = 'en'): LineBreakIterator
    {
        return new LineBreakIterator($text, $locale);
    }
}
