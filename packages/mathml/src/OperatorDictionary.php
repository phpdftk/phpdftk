<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * The MathML Core operator dictionary
 * (https://w3c.github.io/mathml-core/#operator-dictionary).
 *
 * Maps an `<mo>`'s content plus its form (prefix / infix / postfix /
 * suffix) to the default values Core §3.2.4 gives that operator:
 *
 *  - `lspace` / `rspace` in em — the inline padding the painter puts
 *    either side of the glyph;
 *  - `stretchy`, and with it `horizontal` (which AXIS it stretches
 *    along — an arrow or an overbar grows inline, a fence grows in the
 *    block direction) and `symmetric` (whether the stretched glyph
 *    centres on the math axis);
 *  - `largeop` and `movablelimits`, which decide whether ∑ renders at
 *    display proportions and whether its limits sit above/below or
 *    become scripts.
 *
 * The data lives in the generated {@see OperatorDictionaryTable} — all
 * 1180 (characters, form) pairs of the spec table, not a curated
 * slice. That distinction matters because a missing entry is invisible:
 * it resolves to {@see DEFAULT_ENTRY}, which is a plausible-looking
 * 5/18 em on both sides, so an operator the table forgot renders
 * *nearly* right and nothing points at the dictionary.
 *
 * The painter calls {@see lookup} with the operator text and the form
 * it computed from sibling position. When the table has no matching
 * `(text, form)` pair, the lookup returns the default entry, so paint()
 * always has a well-defined answer.
 */
final class OperatorDictionary
{
    /**
     * Bit values packed into the third element of a
     * {@see OperatorDictionaryTable} entry. The generator writes these
     * same values — keep the two in sync.
     */
    public const int FLAG_STRETCHY = 1;

    public const int FLAG_SYMMETRIC = 2;

    public const int FLAG_HORIZONTAL = 4;

    public const int FLAG_LARGEOP = 8;

    public const int FLAG_MOVABLELIMITS = 16;

    /**
     * Per Core §3.2.4, an operator not present in the dictionary uses
     * lspace = rspace = 5/18 em ("thickmuskip"), NOT zero, and none of
     * the boolean properties.
     *
     * @var array{
     *     lspace: float,
     *     rspace: float,
     *     stretchy: bool,
     *     symmetric: bool,
     *     horizontal: bool,
     *     largeop: bool,
     *     movablelimits: bool,
     * }
     */
    public const array DEFAULT_ENTRY = [
        'lspace' => 5.0 / 18.0,
        'rspace' => 5.0 / 18.0,
        'stretchy' => false,
        'symmetric' => false,
        'horizontal' => false,
        'largeop' => false,
        'movablelimits' => false,
    ];

    /**
     * Look an operator up by its UTF-8 text content + form.
     * Returns {@see DEFAULT_ENTRY} when no match is found so the
     * caller never sees null.
     *
     * `$formWasInferred` says the form came from the operator's
     * position among its siblings rather than from a `form`
     * attribute; only then does a miss consult the character's other
     * tabulated forms (Core §3.2.4.2).
     *
     * @return array{
     *     lspace: float,
     *     rspace: float,
     *     stretchy: bool,
     *     symmetric: bool,
     *     horizontal: bool,
     *     largeop: bool,
     *     movablelimits: bool,
     * }
     */
    public static function lookup(
        string $text,
        string $form,
        bool $formWasInferred = false,
    ): array {
        $packed = OperatorDictionaryTable::TABLE[$text][$form] ?? null;
        // Core §3.2.4.2 — when the dictionary lists the character but
        // not under the form we are looking up, the character's OTHER
        // forms are consulted before the default entry, in the order
        // infix, postfix, prefix.
        //
        // This applies ONLY to an INFERRED form. An author-supplied
        // `form` attribute is an assertion about which entry to use,
        // and a miss against it falls straight through to the default
        // — `mo-form`'s reference pins all nine combinations of
        // {∇ (prefix-only), ⋉ (infix-only), ” (postfix-only)} ×
        // {infix, prefix, postfix} and every mismatched pair there
        // resolves to the default 5/18 em rather than to the
        // character's one tabulated form.
        //
        // With an inferred form the fallback is what makes
        // `operator-dictionary-arabic-001` correct: U+1EEF0 is
        // tabulated postfix-only, and as the middle child of an
        // `<mrow>` (inferred infix) it must still pick up that
        // entry's zero spacing rather than a 5/18 em gap per side.
        if ($packed === null
            && $formWasInferred
            && isset(OperatorDictionaryTable::TABLE[$text])
        ) {
            foreach (self::FORM_FALLBACK_ORDER as $candidate) {
                $packed = OperatorDictionaryTable::TABLE[$text][$candidate] ?? null;
                if ($packed !== null) {
                    break;
                }
            }
        }
        if ($packed === null) {
            return self::DEFAULT_ENTRY;
        }
        [$lspace, $rspace, $flags] = $packed;

        return [
            // Spacing is tabulated in eighteenths of an em.
            'lspace' => $lspace / 18.0,
            'rspace' => $rspace / 18.0,
            'stretchy' => ($flags & self::FLAG_STRETCHY) !== 0,
            'symmetric' => ($flags & self::FLAG_SYMMETRIC) !== 0,
            'horizontal' => ($flags & self::FLAG_HORIZONTAL) !== 0,
            'largeop' => ($flags & self::FLAG_LARGEOP) !== 0,
            'movablelimits' => ($flags & self::FLAG_MOVABLELIMITS) !== 0,
        ];
    }

    /**
     * Order the other forms of a listed character are tried in when
     * the computed form has no entry (Core §3.2.4.3).
     *
     * @var list<string>
     */
    private const array FORM_FALLBACK_ORDER = ['infix', 'postfix', 'prefix'];

    /**
     * Whether the dictionary lists `$text` under ANY form.
     *
     * Core §3.2.4.3 distinguishes "the dictionary has no entry for this
     * character at all" from "it has one, but not for the form we
     * computed" — the latter falls back to the character's other forms
     * before it falls back to the default entry.
     */
    public static function hasOperator(string $text): bool
    {
        return isset(OperatorDictionaryTable::TABLE[$text]);
    }

    /**
     * Total number of (characters, form) pairs in the table. Exposed so
     * a test can assert the full spec dictionary is present rather than
     * a subset that would silently resolve to {@see DEFAULT_ENTRY}.
     */
    public static function entryCount(): int
    {
        $count = 0;
        foreach (OperatorDictionaryTable::TABLE as $forms) {
            $count += count($forms);
        }

        return $count;
    }
}
