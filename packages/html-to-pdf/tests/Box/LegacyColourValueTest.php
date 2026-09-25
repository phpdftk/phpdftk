<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Color;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * HTML Standard §2.4.6, "rules for parsing a legacy colour value" — the
 * algorithm behind `<font color>`, `bgcolor`, `<body text>` and friends.
 *
 * Its tail is deliberately total: after the named-colour and `#rgb`
 * escape hatches, EVERY remaining string is coerced to some colour by
 * blanking non-hex characters and slicing the result into three runs. The
 * slicing steps are where it gets counter-intuitive, and where a
 * plausible-looking per-component implementation goes wrong:
 *
 *   13. If each component is longer than 8, keep the LAST 8.
 *   14. While EVERY component is longer than two AND EVERY component
 *       starts with "0", drop that leading "0" from each.
 *   15. If each component is still longer than two, keep the FIRST two.
 *
 * Step 14's condition ranges over all three components at once. Trimming
 * each component on its own instead turns `tranſparent` — which must be
 * #0000E0 — into #A0A0E0, because the two runs that do start with "0" get
 * shortened while the one starting with "e" does not.
 */
final class LegacyColourValueTest extends TestCase
{
    private BoxGenerator $generator;
    private CssParser $css;
    private HtmlParser $html;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $this->generator = new BoxGenerator(new Cascade(PropertyRegistry::default()));
    }

    /** Resolve the used `color` of `<font color="$attr">` as `#rrggbb`. */
    private function fontColour(string $attr): string
    {
        $ua = $this->css->parseStylesheet(
            (new RendererOptions())->effectiveUserAgentStylesheet(),
            Origin::UserAgent,
        );
        $doc = $this->html->parseDocument(
            '<!doctype html><html><body><font id=t color="' . $attr . '">X</font></body></html>',
        );
        $root = $this->generator->generate($doc, [$ua]);
        self::assertNotNull($root);
        $box = $this->findById($root, 't');
        self::assertNotNull($box, 'no box for the <font> element');
        $colour = $box->style->get('color');
        self::assertInstanceOf(Color::class, $colour);
        return sprintf(
            '#%02x%02x%02x',
            (int) round($colour->r * 255),
            (int) round($colour->g * 255),
            (int) round($colour->b * 255),
        );
    }

    private function findById(Box $root, string $id): ?Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element?->getAttribute('id') === $id
                && !$node instanceof \Phpdftk\HtmlToPdf\Box\TextBox
            ) {
                return $node;
            }
            foreach ($node->children as $child) {
                $stack[] = $child;
            }
        }
        return null;
    }

    public function testLongSDefeatsTheTransparentKeywordAndFallsIntoTheHexTail(): void
    {
        // U+017F LATIN SMALL LETTER LONG S is not an ASCII case-insensitive
        // match for "s", so `tranſparent` is not the `transparent` error
        // case. The tail then runs:
        //   blank non-hex -> 00a000a0e00  (11)
        //   pad to x3     -> 00a000a0e000 (12)
        //   split         -> 00a0 | 00a0 | e000
        //   step 14       -> no-op, because "e000" does not start with "0"
        //   step 15       -> 00 | 00 | e0
        self::assertSame('#0000e0', $this->fontColour("tran\u{017F}parent"));
    }

    public function testStepFourteenOnlyTrimsWhenEveryComponentHasALeadingZero(): void
    {
        // All three runs start with "0", so the shared trim DOES fire, once,
        // and then step 15 keeps the first two of what is left.
        //   "0a10b20c3" -> 0a1 | 0b2 | 0c3 -> (len 3 > 2, all lead "0")
        //               -> a1 | b2 | c3
        self::assertSame('#a1b2c3', $this->fontColour('0a10b20c3'));
    }

    public function testStepFourteenIsBlockedByASingleNonZeroLeader(): void
    {
        // Same shape, but the middle run leads with "b": nothing is
        // trimmed, and step 15 keeps the FIRST two of each.
        self::assertSame('#0ab20c', $this->fontColour('0a1b200c3'));
    }

    public function testTransparentIsAnErrorCaseAndLeavesTheColourAlone(): void
    {
        self::assertSame('#000000', $this->fontColour('transparent'));
    }

    public function testTransparentIsMatchedAsciiCaseInsensitively(): void
    {
        self::assertSame('#000000', $this->fontColour('TrAnSpArEnT'));
    }

    public function testNamedColoursAndHashRgbStillShortCircuit(): void
    {
        self::assertSame('#ff0000', $this->fontColour('ReD'));
        self::assertSame('#00ff00', $this->fontColour('#0f0'));
    }

    public function testAnUnparseableValueIsCoercedToBlackNotIgnored(): void
    {
        // "x" -> "0" -> padded "000" -> 0 | 0 | 0
        self::assertSame('#000000', $this->fontColour('x'));
    }

    public function testComponentsLongerThanEightKeepTheirLastEightCharacters(): void
    {
        // 27 hex digits -> three 9-character runs. Step 13 drops the
        // LEADING character of each (not the trailing one), which is what
        // discards the three "1"s:
        //   100000000 | 100000000 | 1000000ff
        //   -> 00000000 | 00000000 | 000000ff   (step 13, keep last 8)
        //   -> 00       | 00       | ff         (step 14, six shared trims)
        // Keeping the FIRST 8 instead would leave "10000000" runs and
        // block step 14 outright, giving #101010.
        self::assertSame('#0000ff', $this->fontColour('100000000' . '100000000' . '1000000ff'));
    }

    public function testStepThirteenKeepsTheLastEightNotTheFirst(): void
    {
        // A sharper probe of the same step: the informative digits sit at
        // the END of each over-long run, so truncating from the front is
        // the only way to reach them.
        //   00000000a | 00000000b | 00000000c
        //   -> 0000000a | 0000000b | 0000000c   (step 13)
        //   -> 0a       | 0b       | 0c         (step 14, then 15)
        self::assertSame('#0a0b0c', $this->fontColour('00000000a' . '00000000b' . '00000000c'));
    }
}
