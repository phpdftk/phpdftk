<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Length;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use PHPUnit\Framework\TestCase;

/**
 * HTML §15.3.3 (The page) — the legacy `<body>` margin attributes.
 *
 * The mapping is not one attribute per side, which is the whole reason
 * this needs tests: a single attribute sets BOTH sides of its axis, and
 * `rightmargin` / `bottommargin` map to nothing at all. Writing them
 * per-side instead makes `<body leftmargin=100>` a one-sided margin,
 * which looks plausible and is wrong.
 */
final class BodyMarginAttributesTest extends TestCase
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

    /**
     * @return array{top: ?float, right: ?float, bottom: ?float, left: ?float}
     */
    private function bodyMargins(string $markup, ?Element $frameOwner = null): array
    {
        $sheet = $this->css->parseStylesheet(
            'html, body { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate(
            $this->html->parseDocument($markup),
            [$sheet],
            $frameOwner,
        );
        self::assertNotNull($root);
        $body = $this->findBody($root);
        self::assertNotNull($body, 'no <body> box generated');
        $out = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $value = $body->style->has("margin-$side")
                ? $body->style->get("margin-$side")
                : null;
            $out[$side] = $value instanceof Length ? $value->value : null;
        }
        return $out;
    }

    private function findBody(Box $box): ?Box
    {
        if ($box->element !== null && strtolower($box->element->localName) === 'body') {
            return $box;
        }
        foreach ($box->children as $child) {
            $found = $this->findBody($child);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    private function iframe(string $markup): Element
    {
        $document = $this->html->parseDocument('<!doctype html><body>' . $markup);
        $found = null;
        $walk = static function (object $node) use (&$walk, &$found): void {
            if ($found !== null) {
                return;
            }
            if ($node instanceof Element && strtolower($node->localName) === 'iframe') {
                $found = $node;
                return;
            }
            foreach ($node->childNodes() as $child) {
                $walk($child);
            }
        };
        $walk($document);
        self::assertNotNull($found);
        return $found;
    }

    public function testNoAttributesLeavesMarginsUndeclared(): void
    {
        self::assertSame(
            ['top' => null, 'right' => null, 'bottom' => null, 'left' => null],
            $this->bodyMargins('<!doctype html><body>x'),
        );
    }

    public function testMarginwidthSetsBothInlineSides(): void
    {
        $m = $this->bodyMargins('<!doctype html><body marginwidth=100>x');
        self::assertSame(100.0, $m['left']);
        self::assertSame(100.0, $m['right']);
        self::assertNull($m['top']);
        self::assertNull($m['bottom']);
    }

    public function testLeftmarginSetsBothInlineSidesNotJustTheLeft(): void
    {
        // The name says "left"; the mapping is both sides. Every
        // `body-margin-1c`-style reference is `margin-left + margin-right`.
        $m = $this->bodyMargins('<!doctype html><body leftmargin=100>x');
        self::assertSame(100.0, $m['left']);
        self::assertSame(100.0, $m['right']);
    }

    public function testMarginheightSetsBothBlockSides(): void
    {
        $m = $this->bodyMargins('<!doctype html><body marginheight=100>x');
        self::assertSame(100.0, $m['top']);
        self::assertSame(100.0, $m['bottom']);
        self::assertNull($m['left']);
        self::assertNull($m['right']);
    }

    public function testTopmarginSetsBothBlockSidesNotJustTheTop(): void
    {
        $m = $this->bodyMargins('<!doctype html><body topmargin=100>x');
        self::assertSame(100.0, $m['top']);
        self::assertSame(100.0, $m['bottom']);
    }

    public function testRightmarginAndBottommarginMapToNothing(): void
    {
        // `body-margin-3a` / `3b`: a body carrying only these two keeps
        // its UA margins in both standards and quirks mode.
        self::assertSame(
            ['top' => null, 'right' => null, 'bottom' => null, 'left' => null],
            $this->bodyMargins('<!doctype html><body rightmargin=100 bottommargin=100>x'),
        );
    }

    public function testMarginwidthBeatsLeftmargin(): void
    {
        $m = $this->bodyMargins('<!doctype html><body marginwidth=100 leftmargin=40 rightmargin=50>x');
        self::assertSame(100.0, $m['left']);
        self::assertSame(100.0, $m['right']);
    }

    public function testMarginheightBeatsTopmargin(): void
    {
        $m = $this->bodyMargins('<!doctype html><body marginheight=100 topmargin=40 bottommargin=50>x');
        self::assertSame(100.0, $m['top']);
        self::assertSame(100.0, $m['bottom']);
    }

    public function testAuthorStyleBeatsEveryAttribute(): void
    {
        $m = $this->bodyMargins(
            '<!doctype html><body marginwidth=30 leftmargin=40 rightmargin=50'
            . ' style="margin-left: 100px; margin-right: 100px">x',
            $this->iframe('<iframe marginwidth=20></iframe>'),
        );
        self::assertSame(100.0, $m['left']);
        self::assertSame(100.0, $m['right']);
    }

    public function testFramesMarginwidthAppliesWhenTheBodyDeclaresNone(): void
    {
        $m = $this->bodyMargins(
            '<!doctype html><body>x',
            $this->iframe('<iframe marginwidth=100></iframe>'),
        );
        self::assertSame(100.0, $m['left']);
        self::assertSame(100.0, $m['right']);
        self::assertNull($m['top']);
    }

    public function testFramesMarginheightAppliesWhenTheBodyDeclaresNone(): void
    {
        $m = $this->bodyMargins(
            '<!doctype html><body>x',
            $this->iframe('<iframe marginheight=100></iframe>'),
        );
        self::assertSame(100.0, $m['top']);
        self::assertSame(100.0, $m['bottom']);
        self::assertNull($m['left']);
    }

    public function testBodyAttributeBeatsTheFramesAttribute(): void
    {
        $m = $this->bodyMargins(
            '<!doctype html><body leftmargin=100>x',
            $this->iframe('<iframe marginwidth=20></iframe>'),
        );
        self::assertSame(100.0, $m['left']);
        self::assertSame(100.0, $m['right']);
    }

    public function testFramesFarSideAttributeIsNotConsultedAsAFallback(): void
    {
        // The frame fallback reads `marginwidth` / `marginheight` only.
        // `<iframe leftmargin>` is not a thing.
        self::assertSame(
            ['top' => null, 'right' => null, 'bottom' => null, 'left' => null],
            $this->bodyMargins(
                '<!doctype html><body>x',
                $this->iframe('<iframe leftmargin=100 topmargin=100></iframe>'),
            ),
        );
    }

    public function testUnparseableAttributeMapsToNothingRatherThanZero(): void
    {
        self::assertSame(
            ['top' => null, 'right' => null, 'bottom' => null, 'left' => null],
            $this->bodyMargins('<!doctype html><body marginwidth="auto" marginheight="">x'),
        );
    }

    public function testAttributeValueIsParsedLeniently(): void
    {
        // "Rules for parsing non-negative integers" stop at the first
        // non-digit, so a unit suffix is tolerated.
        $m = $this->bodyMargins('<!doctype html><body marginwidth="25px">x');
        self::assertSame(25.0, $m['left']);
    }

    public function testAttributesOnANonBodyElementAreIgnored(): void
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, div { display: block; }',
            Origin::UserAgent,
        );
        $root = $this->generator->generate(
            $this->html->parseDocument('<!doctype html><body><div marginwidth=100>x</div>'),
            [$sheet],
        );
        self::assertNotNull($root);
        $div = null;
        $walk = static function (Box $b) use (&$walk, &$div): void {
            if ($b->element !== null && strtolower($b->element->localName) === 'div') {
                $div = $b;
                return;
            }
            foreach ($b->children as $c) {
                $walk($c);
            }
        };
        $walk($root);
        self::assertNotNull($div);
        self::assertFalse($div->style->has('margin-left'));
    }
}
