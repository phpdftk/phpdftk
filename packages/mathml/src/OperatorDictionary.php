<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * Subset of the MathML Core operator dictionary
 * (https://w3c.github.io/mathml-core/#operator-dictionary).
 *
 * Maps an `<mo>` content + form (prefix / infix / postfix) to its
 * default attribute values: `lspace` and `rspace` in em (the
 * surrounding horizontal padding the painter must apply), plus
 * `stretchy` so a future stretch-aware painter can pick it up.
 *
 * The full Core dictionary has ~1000 entries covering every operator
 * the W3C cared to standardise. v1 ships the operators that show up
 * in the WPT presentation-markup tests and the everyday subset of
 * basic algebra / calculus / set notation - roughly 60 entries. Any
 * operator not in this table falls through to {@see DEFAULT_ENTRY}
 * (zero padding, non-stretchy), which is a reasonable inert default.
 *
 * The painter calls {@see lookup} with the operator text and the
 * form it computed from sibling position. When the table has no
 * matching `(text, form)` pair, the lookup returns the default
 * entry so paint() always has a well-defined spacing.
 */
final class OperatorDictionary
{
    /**
     * Fallback when an operator is not in the table. Zero spacing
     * on both sides and non-stretchy.
     *
     * @var array{lspace: float, rspace: float, stretchy: bool}
     */
    public const array DEFAULT_ENTRY = [
        'lspace' => 0.0,
        'rspace' => 0.0,
        'stretchy' => false,
    ];

    /**
     * Look an operator up by its UTF-8 text content + form.
     * Returns {@see DEFAULT_ENTRY} when no match is found so the
     * caller never sees null.
     *
     * @return array{lspace: float, rspace: float, stretchy: bool}
     */
    public static function lookup(string $text, string $form): array
    {
        $entries = self::entries();
        return $entries[$text][$form] ?? self::DEFAULT_ENTRY;
    }

    /**
     * Indexed by operator text -> form -> entry. Kept lazy via a
     * static method (instead of a const) so the values can include
     * computed constants without losing the data-table shape.
     *
     * @return array<string, array<string, array{lspace: float, rspace: float, stretchy: bool}>>
     */
    private static function entries(): array
    {
        // em values follow MathML Core Appendix B. The most common
        // pattern is lspace=rspace=5/18 (~ 0.278) for relational
        // operators and 4/18 (~ 0.222) for additive operators.
        $thinmuskip   = 3.0 / 18.0;     // ~ 0.167 em
        $medmuskip    = 4.0 / 18.0;     // ~ 0.222 em
        $thickmuskip  = 5.0 / 18.0;     // ~ 0.278 em

        return [
            // Additive operators (infix, mediummath).
            '+' => self::infix($medmuskip, $medmuskip),
            '-' => self::infix($medmuskip, $medmuskip),
            "\u{2212}" => self::infix($medmuskip, $medmuskip),  // MINUS SIGN
            "\u{00B1}" => self::infix($medmuskip, $medmuskip),  // PLUS-MINUS
            "\u{2213}" => self::infix($medmuskip, $medmuskip),  // MINUS-PLUS

            // Multiplicative operators (infix, mediummath).
            '*' => self::infix($medmuskip, $medmuskip),
            "\u{00D7}" => self::infix($medmuskip, $medmuskip),  // TIMES
            "\u{00F7}" => self::infix($medmuskip, $medmuskip),  // DIVISION
            "\u{22C5}" => self::infix($medmuskip, $medmuskip),  // DOT OPERATOR
            "\u{00B7}" => self::infix($medmuskip, $medmuskip),  // MIDDLE DOT

            // Relational operators (infix, thickmath).
            '=' => self::infix($thickmuskip, $thickmuskip),
            '<' => self::infix($thickmuskip, $thickmuskip),
            '>' => self::infix($thickmuskip, $thickmuskip),
            "\u{2260}" => self::infix($thickmuskip, $thickmuskip),  // NOT EQUAL
            "\u{2264}" => self::infix($thickmuskip, $thickmuskip),  // LESS-EQUAL
            "\u{2265}" => self::infix($thickmuskip, $thickmuskip),  // GREATER-EQUAL
            "\u{2248}" => self::infix($thickmuskip, $thickmuskip),  // ALMOST EQUAL
            "\u{2261}" => self::infix($thickmuskip, $thickmuskip),  // IDENTICAL TO
            "\u{2243}" => self::infix($thickmuskip, $thickmuskip),  // ASYMP EQUAL
            "\u{2245}" => self::infix($thickmuskip, $thickmuskip),  // APPROX EQUAL
            "\u{221D}" => self::infix($thickmuskip, $thickmuskip),  // PROPORTIONAL

            // Set membership (infix, thickmath).
            "\u{2208}" => self::infix($thickmuskip, $thickmuskip),  // ELEMENT OF
            "\u{220B}" => self::infix($thickmuskip, $thickmuskip),  // CONTAINS
            "\u{2282}" => self::infix($thickmuskip, $thickmuskip),  // SUBSET
            "\u{2283}" => self::infix($thickmuskip, $thickmuskip),  // SUPERSET
            "\u{2286}" => self::infix($thickmuskip, $thickmuskip),  // SUBSET-EQ
            "\u{2287}" => self::infix($thickmuskip, $thickmuskip),  // SUPERSET-EQ

            // Arrows (infix, thickmath).
            "\u{2192}" => self::infix($thickmuskip, $thickmuskip),  // RIGHTWARDS
            "\u{2190}" => self::infix($thickmuskip, $thickmuskip),  // LEFTWARDS
            "\u{21D2}" => self::infix($thickmuskip, $thickmuskip),  // DOUBLE RIGHT
            "\u{21D4}" => self::infix($thickmuskip, $thickmuskip),  // DOUBLE LEFT-RIGHT
            "\u{2194}" => self::infix($thickmuskip, $thickmuskip),  // LEFT-RIGHT

            // Punctuation - asymmetric spacing.
            ',' => self::infix(0.0, $thinmuskip),
            ';' => self::infix(0.0, $medmuskip),
            ':' => self::infix($medmuskip, $medmuskip),
            '.' => self::infix(0.0, 0.0),
            '/' => self::infix(0.0, 0.0),

            // Factorial-style postfix.
            '!' => [
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => false],
            ],

            // Brackets - prefix opens, postfix closes. Stretchy in
            // production but the v1 painter doesn't stretch them.
            '(' => [
                'prefix'  => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            ')' => [
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            '[' => [
                'prefix'  => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            ']' => [
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            '{' => [
                'prefix'  => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            '}' => [
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            '|' => [
                // Used as both prefix and postfix (absolute value).
                'prefix'  => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
                'infix'   => self::infix($thickmuskip, $thickmuskip)['infix'],
            ],

            // Large operators - prefix with thin spacing.
            "\u{2211}" => self::prefix($thinmuskip, $thinmuskip),  // SUMMATION
            "\u{220F}" => self::prefix($thinmuskip, $thinmuskip),  // PRODUCT
            "\u{2210}" => self::prefix($thinmuskip, $thinmuskip),  // COPRODUCT
            "\u{222B}" => self::prefix($thinmuskip, $thinmuskip),  // INTEGRAL
            "\u{222C}" => self::prefix($thinmuskip, $thinmuskip),  // DOUBLE INTEGRAL
            "\u{222E}" => self::prefix($thinmuskip, $thinmuskip),  // CONTOUR INTEGRAL
            "\u{2A00}" => self::prefix($thinmuskip, $thinmuskip),  // CIRCLED DOT
            "\u{2A01}" => self::prefix($thinmuskip, $thinmuskip),  // CIRCLED PLUS
            "\u{2A02}" => self::prefix($thinmuskip, $thinmuskip),  // CIRCLED TIMES

            // Logical (infix, thickmath).
            "\u{2227}" => self::infix($thickmuskip, $thickmuskip),  // AND
            "\u{2228}" => self::infix($thickmuskip, $thickmuskip),  // OR
            "\u{00AC}" => self::prefix(0.0, $thinmuskip),           // NOT

            // Extended relational - common in proofs / analysis.
            "\u{226A}" => self::infix($thickmuskip, $thickmuskip),  // MUCH LESS-THAN
            "\u{226B}" => self::infix($thickmuskip, $thickmuskip),  // MUCH GREATER-THAN
            "\u{2266}" => self::infix($thickmuskip, $thickmuskip),  // LESS-EQUAL OVER GT
            "\u{2267}" => self::infix($thickmuskip, $thickmuskip),  // GREATER-EQUAL OVER LT
            "\u{2272}" => self::infix($thickmuskip, $thickmuskip),  // LESS-EQUAL-SIM
            "\u{2273}" => self::infix($thickmuskip, $thickmuskip),  // GREATER-EQUAL-SIM
            "\u{223C}" => self::infix($thickmuskip, $thickmuskip),  // TILDE OPERATOR
            "\u{2249}" => self::infix($thickmuskip, $thickmuskip),  // NOT ALMOST EQUAL
            "\u{2262}" => self::infix($thickmuskip, $thickmuskip),  // NOT IDENTICAL
            "\u{2225}" => self::infix($thickmuskip, $thickmuskip),  // PARALLEL TO
            "\u{2226}" => self::infix($thickmuskip, $thickmuskip),  // NOT PARALLEL
            "\u{22A5}" => self::infix($thickmuskip, $thickmuskip),  // UP TACK / perpendicular
            "\u{22A4}" => self::infix($thickmuskip, $thickmuskip),  // DOWN TACK / top

            // Extended set theory.
            "\u{2209}" => self::infix($thickmuskip, $thickmuskip),  // NOT ELEMENT OF
            "\u{220C}" => self::infix($thickmuskip, $thickmuskip),  // DOES NOT CONTAIN
            "\u{228A}" => self::infix($thickmuskip, $thickmuskip),  // SUBSET WITH NEQ
            "\u{228B}" => self::infix($thickmuskip, $thickmuskip),  // SUPERSET WITH NEQ
            "\u{2284}" => self::infix($thickmuskip, $thickmuskip),  // NOT SUBSET
            "\u{2285}" => self::infix($thickmuskip, $thickmuskip),  // NOT SUPERSET
            "\u{2229}" => self::infix($medmuskip, $medmuskip),      // INTERSECTION
            "\u{222A}" => self::infix($medmuskip, $medmuskip),      // UNION
            "\u{22C2}" => self::prefix($thinmuskip, $thinmuskip),   // N-ARY INTERSECTION
            "\u{22C3}" => self::prefix($thinmuskip, $thinmuskip),   // N-ARY UNION
            "\u{2205}" => self::infix(0.0, 0.0),                    // EMPTY SET
            "\u{2216}" => self::infix($medmuskip, $medmuskip),      // SET MINUS

            // Calculus + analysis.
            "\u{2202}" => self::infix(0.0, 0.0),                    // PARTIAL
            "\u{2207}" => self::infix(0.0, 0.0),                    // NABLA
            "\u{221E}" => self::infix(0.0, 0.0),                    // INFINITY
            "\u{2218}" => self::infix($thinmuskip, $thinmuskip),    // RING OPERATOR
            "\u{2217}" => self::infix($medmuskip, $medmuskip),      // ASTERISK OPERATOR
            "\u{2295}" => self::infix($medmuskip, $medmuskip),      // CIRCLED PLUS
            "\u{2296}" => self::infix($medmuskip, $medmuskip),      // CIRCLED MINUS
            "\u{2297}" => self::infix($medmuskip, $medmuskip),      // CIRCLED TIMES
            "\u{2299}" => self::infix($medmuskip, $medmuskip),      // CIRCLED DOT

            // More arrows.
            "\u{21A6}" => self::infix($thickmuskip, $thickmuskip),  // MAPS TO
            "\u{21D0}" => self::infix($thickmuskip, $thickmuskip),  // DOUBLE LEFTWARDS
            "\u{21CC}" => self::infix($thickmuskip, $thickmuskip),  // RIGHT-LEFT HARPOONS
            "\u{21C4}" => self::infix($thickmuskip, $thickmuskip),  // RIGHT-LEFT ARROWS
            "\u{27F6}" => self::infix($thickmuskip, $thickmuskip),  // LONG RIGHTWARDS
            "\u{27F5}" => self::infix($thickmuskip, $thickmuskip),  // LONG LEFTWARDS
            "\u{27F9}" => self::infix($thickmuskip, $thickmuskip),  // LONG DOUBLE RIGHT
            "\u{27FA}" => self::infix($thickmuskip, $thickmuskip),  // LONG DOUBLE LEFT-RIGHT
            "\u{2191}" => self::infix($thickmuskip, $thickmuskip),  // UPWARDS ARROW
            "\u{2193}" => self::infix($thickmuskip, $thickmuskip),  // DOWNWARDS ARROW

            // Quantifiers.
            "\u{2200}" => self::prefix(0.0, $thinmuskip),           // FOR ALL
            "\u{2203}" => self::prefix(0.0, $thinmuskip),           // THERE EXISTS
            "\u{2204}" => self::prefix(0.0, $thinmuskip),           // THERE DOES NOT EXIST

            // Logic / proof.
            "\u{22A2}" => self::infix($thickmuskip, $thickmuskip),  // RIGHT TACK / proves
            "\u{22A8}" => self::infix($thickmuskip, $thickmuskip),  // TRUE / models
            "\u{2234}" => self::infix($thickmuskip, $thickmuskip),  // THEREFORE
            "\u{2235}" => self::infix($thickmuskip, $thickmuskip),  // BECAUSE

            // Specialised brackets / fences.
            "\u{27E8}" => [   // MATH LEFT ANGLE BRACKET
                'prefix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            "\u{27E9}" => [   // MATH RIGHT ANGLE BRACKET
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            "\u{230A}" => [   // LEFT FLOOR
                'prefix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            "\u{230B}" => [   // RIGHT FLOOR
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            "\u{2308}" => [   // LEFT CEILING
                'prefix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            "\u{2309}" => [   // RIGHT CEILING
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],
            "\u{2016}" => [   // DOUBLE VERTICAL LINE (norm)
                'prefix'  => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => true],
            ],

            // Dots / ellipsis.
            "\u{2026}" => self::infix(0.0, 0.0),                    // HORIZONTAL ELLIPSIS
            "\u{22EE}" => self::infix(0.0, 0.0),                    // VERTICAL ELLIPSIS
            "\u{22EF}" => self::infix(0.0, 0.0),                    // MIDLINE HORIZONTAL ELLIPSIS
            "\u{22F1}" => self::infix(0.0, 0.0),                    // DOWN RIGHT DIAGONAL ELLIPSIS

            // Additional postfix - double factorial, primes.
            "\u{2032}" => [   // PRIME
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => false],
            ],
            "\u{2033}" => [   // DOUBLE PRIME
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => false],
            ],
            "\u{2034}" => [   // TRIPLE PRIME
                'postfix' => ['lspace' => 0.0, 'rspace' => 0.0, 'stretchy' => false],
            ],
        ];
    }

    /**
     * @return array<string, array{lspace: float, rspace: float, stretchy: bool}>
     */
    private static function infix(float $lspace, float $rspace): array
    {
        return [
            'infix' => [
                'lspace' => $lspace,
                'rspace' => $rspace,
                'stretchy' => false,
            ],
        ];
    }

    /**
     * @return array<string, array{lspace: float, rspace: float, stretchy: bool}>
     */
    private static function prefix(float $lspace, float $rspace): array
    {
        return [
            'prefix' => [
                'lspace' => $lspace,
                'rspace' => $rspace,
                'stretchy' => false,
            ],
        ];
    }
}
