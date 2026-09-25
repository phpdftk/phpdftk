<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\OperatorDictionary;
use PHPUnit\Framework\TestCase;

/**
 * Coverage + shape of the MathML Core operator dictionary
 * (Core §3.2.4 / the generated table in Appendix B).
 *
 * {@see OperatorDictionaryTest} covers the lookup contract on a
 * handful of representative operators. This file covers the cases a
 * hand-curated subset gets WRONG rather than merely missing — an
 * operator absent from the table silently resolves to the 5/18 em
 * default, which is a plausible-looking value and so hides the gap.
 * Each case below is an operator the painter meets in ordinary
 * markup whose real entry differs from that default, plus the two
 * stretch-axis properties (`symmetric`, `horizontal`) the dictionary
 * carries and a stretchy painter needs.
 */
final class OperatorDictionaryCoverageTest extends TestCase
{
    // -----------------------------------------------------------------
    // Operators whose real entry is NOT the 5/18 fallback.
    // -----------------------------------------------------------------

    /**
     * @return iterable<string, array{0: string, 1: string, 2: float, 3: float}>
     */
    public static function spacingCases(): iterable
    {
        // Invisible operators carry no spacing at all — rendering them
        // with the 5/18 default opens a visible gap around something
        // that is supposed to be invisible.
        yield 'U+2061 function application' => ["\u{2061}", 'infix', 0.0, 0.0];
        yield 'U+2062 invisible times' => ["\u{2062}", 'infix', 0.0, 0.0];
        yield 'U+2063 invisible separator' => ["\u{2063}", 'infix', 0.0, 0.0];
        yield 'U+2064 invisible plus' => ["\u{2064}", 'infix', 0.0, 0.0];
        // Multiplicative operators take 3/18, not the additive 4/18
        // and not the relational 5/18.
        yield 'multiplication sign' => ["\u{00D7}", 'infix', 3.0 / 18.0, 3.0 / 18.0];
        yield 'middle dot' => ["\u{00B7}", 'infix', 3.0 / 18.0, 3.0 / 18.0];
        yield 'asterisk' => ['*', 'infix', 3.0 / 18.0, 3.0 / 18.0];
        // Unary forms of the additive operators take no space.
        yield 'unary plus' => ['+', 'prefix', 0.0, 0.0];
        yield 'unary minus' => ['-', 'prefix', 0.0, 0.0];
        // Separators: no lspace, thin rspace.
        yield 'semicolon' => [';', 'infix', 0.0, 3.0 / 18.0];
        yield 'colon' => [':', 'infix', 0.0, 3.0 / 18.0];
        // Prefix negation hugs its operand.
        yield 'not sign' => ["\u{00AC}", 'prefix', 0.0, 0.0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('spacingCases')]
    public function testDictionarySpacingMatchesTheSpecTable(
        string $text,
        string $form,
        float $lspace,
        float $rspace,
    ): void {
        $entry = OperatorDictionary::lookup($text, $form);
        self::assertEqualsWithDelta($lspace, $entry['lspace'], 0.0001, 'lspace');
        self::assertEqualsWithDelta($rspace, $entry['rspace'], 0.0001, 'rspace');
    }

    // -----------------------------------------------------------------
    // Stretch axis: a stretchy operator is useless without it.
    // -----------------------------------------------------------------

    public function testHorizontalArrowsAreStretchyAlongTheInlineAxis(): void
    {
        $entry = OperatorDictionary::lookup("\u{2190}", 'infix');
        self::assertTrue($entry['stretchy'], 'leftwards arrow stretches');
        self::assertTrue($entry['horizontal'], 'and it stretches horizontally');
        self::assertEqualsWithDelta(5.0 / 18.0, $entry['lspace'], 0.0001);
    }

    public function testOverbarIsAHorizontalPostfixStretchyOperator(): void
    {
        // The accent used by the embellished-operator reftests: it has
        // to stretch to the width of the base it sits over.
        $entry = OperatorDictionary::lookup("\u{00AF}", 'postfix');
        self::assertTrue($entry['stretchy']);
        self::assertTrue($entry['horizontal']);
        self::assertSame(0.0, $entry['lspace']);
        self::assertSame(0.0, $entry['rspace']);
    }

    public function testFencesAreStretchyAndSymmetricButNotHorizontal(): void
    {
        $open = OperatorDictionary::lookup('(', 'prefix');
        self::assertTrue($open['stretchy']);
        self::assertTrue($open['symmetric'], 'fences centre on the math axis');
        self::assertFalse($open['horizontal'], 'fences stretch along the block axis');
    }

    public function testLargeOperatorsCarryTheirLimitsAndSymmetryFlags(): void
    {
        $entry = OperatorDictionary::lookup("\u{2211}", 'prefix');
        self::assertTrue($entry['largeop']);
        self::assertTrue($entry['movablelimits']);
        self::assertTrue($entry['symmetric']);
        self::assertEqualsWithDelta(3.0 / 18.0, $entry['lspace'], 0.0001);
    }

    // -----------------------------------------------------------------
    // Shape invariants over the whole table.
    // -----------------------------------------------------------------

    public function testUnknownOperatorStillFallsBackToTheSpecDefault(): void
    {
        $entry = OperatorDictionary::lookup('snorgle', 'infix');
        self::assertEqualsWithDelta(5.0 / 18.0, $entry['lspace'], 0.0001);
        self::assertEqualsWithDelta(5.0 / 18.0, $entry['rspace'], 0.0001);
        self::assertFalse($entry['stretchy']);
        self::assertFalse($entry['horizontal']);
        self::assertFalse($entry['symmetric']);
    }

    public function testEveryLookupReturnsTheFullEntryShape(): void
    {
        foreach ([['(', 'prefix'], ['+', 'infix'], ['snorgle', 'infix'], ["\u{2211}", 'prefix']] as [$text, $form]) {
            $entry = OperatorDictionary::lookup($text, $form);
            self::assertSame(
                ['lspace', 'rspace', 'stretchy', 'symmetric', 'horizontal', 'largeop', 'movablelimits'],
                array_keys($entry),
                "entry shape for '$text' $form",
            );
        }
    }

    public function testTableCoversTheSpecDictionaryNotJustACuratedSlice(): void
    {
        // The dictionary is ~1180 (character, form) pairs. A curated
        // subset resolves the rest to the 5/18 default, which is the
        // failure this whole file exists to catch, so assert the table
        // is actually the full one.
        self::assertGreaterThan(1000, OperatorDictionary::entryCount());
    }

    public function testCheckedInTableStillMatchesItsGeneratorSource(): void
    {
        // The table is generated (scripts/generate-operator-dictionary.php)
        // from the spec dictionary that ships with the WPT corpus. A
        // generated file nobody re-derives rots into a hand-written one,
        // so compare the checked-in table against the source entry for
        // entry. Skipped, not failed, when the corpus is absent — the
        // table is committed precisely so it works without it.
        $source = __DIR__ . '/../../../vendor-data/wpt/mathml/support/operator-dictionary.json';
        if (!is_file($source)) {
            self::markTestSkipped(
                "Spec dictionary not available at $source. "
                . 'Run `git submodule update --init vendor-data/wpt`.',
            );
        }
        $raw = file_get_contents($source);
        self::assertIsString($raw);
        /** @var array{dictionary: array<string, array<string, int|bool>>} $decoded */
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $drift = [];
        foreach ($decoded['dictionary'] as $key => $specEntry) {
            $split = strrpos($key, ' ');
            self::assertNotFalse($split, "malformed spec key: $key");
            $characters = substr($key, 0, $split);
            $form = substr($key, $split + 1);

            $ours = OperatorDictionary::lookup($characters, $form);
            $expected = [
                'lspace' => ($specEntry['lspace'] ?? 5) / 18.0,
                'rspace' => ($specEntry['rspace'] ?? 5) / 18.0,
                'stretchy' => ($specEntry['stretchy'] ?? false) === true,
                'symmetric' => ($specEntry['symmetric'] ?? false) === true,
                'horizontal' => ($specEntry['horizontal'] ?? false) === true,
                'largeop' => ($specEntry['largeop'] ?? false) === true,
                'movablelimits' => ($specEntry['movablelimits'] ?? false) === true,
            ];
            if ($ours !== $expected) {
                $drift[$key] = ['ours' => $ours, 'spec' => $expected];
            }
        }
        self::assertSame([], $drift, 'checked-in table drifted from the spec dictionary');
        self::assertSame(
            count($decoded['dictionary']),
            OperatorDictionary::entryCount(),
            'checked-in table has a different number of entries than the spec dictionary',
        );
    }
}
