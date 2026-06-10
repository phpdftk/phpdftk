<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\ArabicShaper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Arabic contextual shaping via Presentation
 * Forms-B substitution.
 *
 * Each test verifies that a logical-order Arabic input produces
 * the expected Presentation Forms-B codepoint sequence. The
 * shaping output is still in logical order; visual reordering
 * (right-to-left) is handled by the bidi layer downstream.
 */
final class ArabicShaperTest extends TestCase
{
    public function testNonArabicPassesThrough(): void
    {
        self::assertSame('hello', ArabicShaper::shape('hello'));
        self::assertSame('1234', ArabicShaper::shape('1234'));
        self::assertSame('', ArabicShaper::shape(''));
    }

    public function testIsolatedSingleLetter(): void
    {
        // U+0628 BEH alone -> isolated form U+FE8F.
        self::assertSame(
            "\u{FE8F}",
            ArabicShaper::shape("\u{0628}"),
        );
    }

    public function testDualLetterPairProducesInitialPlusFinal(): void
    {
        // BEH + TEH (both dual-joining): BEH takes initial form
        // (U+FE91), TEH takes final form (U+FE96).
        $input = "\u{0628}\u{062A}";
        $expected = "\u{FE91}\u{FE96}";
        self::assertSame($expected, ArabicShaper::shape($input));
    }

    public function testThreeLetterDualSequenceProducesInitialMedialFinal(): void
    {
        // BEH + TEH + THEH (all dual): initial U+FE91, medial
        // U+FE98, final U+FE9A.
        $input = "\u{0628}\u{062A}\u{062B}";
        $expected = "\u{FE91}\u{FE98}\u{FE9A}";
        self::assertSame($expected, ArabicShaper::shape($input));
    }

    public function testRightJoiningLetterBreaksMedialChain(): void
    {
        // BEH + ALEF + BEH: ALEF is right-joining only, so it
        // accepts the join from the preceding BEH but does NOT
        // propagate forward. The second BEH starts a fresh sequence.
        // Forms:
        //   BEH(1): initial U+FE91 (joins right of ALEF)
        //   ALEF:    final U+FE8E (joined on right by BEH)
        //   BEH(2): isolated U+FE8F (no left neighbour that joins,
        //            no right neighbour at all)
        $input = "\u{0628}\u{0627}\u{0628}";
        $expected = "\u{FE91}\u{FE8E}\u{FE8F}";
        self::assertSame($expected, ArabicShaper::shape($input));
    }

    public function testWordSurroundedBySpacesShapesCorrectly(): void
    {
        // ' بت ' - leading + trailing space breaks joining at
        // the boundaries. BEH initial, TEH final.
        $input = " \u{0628}\u{062A} ";
        $expected = " \u{FE91}\u{FE96} ";
        self::assertSame($expected, ArabicShaper::shape($input));
    }

    public function testTransparentDiacriticDoesNotBreakJoin(): void
    {
        // BEH + FATHA (U+064E, transparent) + TEH: the FATHA must
        // not interrupt the join. BEH still gets initial, TEH still
        // gets final, FATHA passes through unchanged in the output.
        $input = "\u{0628}\u{064E}\u{062A}";
        $expected = "\u{FE91}\u{064E}\u{FE96}";
        self::assertSame($expected, ArabicShaper::shape($input));
    }

    public function testJoiningTypeLookups(): void
    {
        self::assertSame(ArabicShaper::JOIN_DUAL, ArabicShaper::joiningTypeOf(0x0628)); // BEH
        self::assertSame(ArabicShaper::JOIN_RIGHT, ArabicShaper::joiningTypeOf(0x0627)); // ALEF
        self::assertSame(ArabicShaper::JOIN_NONE, ArabicShaper::joiningTypeOf(0x0621)); // HAMZA
        self::assertSame(ArabicShaper::JOIN_TRANSPARENT, ArabicShaper::joiningTypeOf(0x064E)); // FATHA
        self::assertSame(ArabicShaper::JOIN_CAUSING, ArabicShaper::joiningTypeOf(0x0640)); // TATWEEL
        self::assertSame(ArabicShaper::JOIN_NONE, ArabicShaper::joiningTypeOf(0x0041)); // 'A'
    }

    public function testIsShapeableFilter(): void
    {
        self::assertTrue(ArabicShaper::isShapeable(0x0628));   // BEH
        self::assertTrue(ArabicShaper::isShapeable(0x0627));   // ALEF
        self::assertFalse(ArabicShaper::isShapeable(0x0041));  // 'A'
        self::assertFalse(ArabicShaper::isShapeable(0x064E));  // FATHA (transparent)
        self::assertFalse(ArabicShaper::isShapeable(0x0640));  // TATWEEL (causing)
    }

    public function testHamzaIsolatedOnly(): void
    {
        // HAMZA (U+0621) is non-joining; it has only an isolated
        // form (U+FE80). Even adjacent letters don't change it.
        $input = "\u{0628}\u{0621}\u{062A}";
        // BEH(0628): final form (right-joins with non-joining HAMZA
        //            on left means no left-join). Wait - HAMZA on
        //            the RIGHT of BEH in source order; in
        //            logical-order parsing BEH is index 0 and HAMZA
        //            is index 1 to the LEFT visually but in the
        //            source string array the HAMZA comes AFTER.
        // Actually source order: BEH then HAMZA then TEH.
        // BEH joinsLeft? HAMZA is non-joining so no.
        // BEH joinsRight? Nothing before -> no.
        // -> BEH isolated U+FE8F.
        // HAMZA always isolated U+FE80.
        // TEH joinsRight? HAMZA non-joining -> no.
        // TEH joinsLeft? nothing after -> no.
        // -> TEH isolated U+FE95.
        $expected = "\u{FE8F}\u{FE80}\u{FE95}";
        self::assertSame($expected, ArabicShaper::shape($input));
    }

    public function testTwoWordSeparatedByTatweel(): void
    {
        // Tatweel (U+0640) is join-causing: it forces a join on
        // both sides. So BEH + TATWEEL + TEH should be all in
        // joining forms even though TATWEEL itself isn't shaped.
        $input = "\u{0628}\u{0640}\u{062A}";
        // BEH joinsLeft? next non-transparent is TATWEEL (causing) -> yes.
        // BEH joinsRight? nothing before -> no.
        // -> BEH initial U+FE91.
        // TATWEEL passes through unchanged.
        // TEH joinsRight? previous non-transparent is TATWEEL (causing) -> yes.
        // TEH joinsLeft? nothing after -> no.
        // -> TEH final U+FE96.
        $expected = "\u{FE91}\u{0640}\u{FE96}";
        self::assertSame($expected, ArabicShaper::shape($input));
    }
}
