<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Value;

use Phpdftk\Color\RgbColor;
use Phpdftk\Svg\Value\Paint;
use Phpdftk\Svg\Value\Paint\CurrentColor;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\Svg\Value\Paint\SolidColor;
use Phpdftk\Svg\Value\Paint\Url;
use PHPUnit\Framework\TestCase;

final class PaintTest extends TestCase
{
    public function testEmptyInputReturnsNull(): void
    {
        self::assertNull(Paint::parse(''));
        self::assertNull(Paint::parse('   '));
    }

    public function testParsesNoneKeyword(): void
    {
        self::assertInstanceOf(None_::class, Paint::parse('none'));
    }

    public function testNoneKeywordIsCaseInsensitive(): void
    {
        // CSS keywords are case-insensitive; SVG follows.
        self::assertInstanceOf(None_::class, Paint::parse('None'));
    }

    public function testParsesCurrentColorKeyword(): void
    {
        self::assertInstanceOf(CurrentColor::class, Paint::parse('currentColor'));
        self::assertInstanceOf(CurrentColor::class, Paint::parse('CURRENTCOLOR'));
    }

    public function testParsesHexAsSolidColor(): void
    {
        $p = Paint::parse('#ff0000');
        self::assertInstanceOf(SolidColor::class, $p);
        self::assertInstanceOf(RgbColor::class, $p->color);
    }

    public function testParsesNamedColorAsSolidColor(): void
    {
        $p = Paint::parse('red');
        self::assertInstanceOf(SolidColor::class, $p);
    }

    public function testParsesUrlReference(): void
    {
        $p = Paint::parse('url(#gradient1)');
        self::assertInstanceOf(Url::class, $p);
        self::assertSame('gradient1', $p->id);
        self::assertNull($p->fallback);
    }

    public function testParsesUrlReferenceWithColorFallback(): void
    {
        $p = Paint::parse('url(#missing) red');
        self::assertInstanceOf(Url::class, $p);
        self::assertSame('missing', $p->id);
        self::assertInstanceOf(SolidColor::class, $p->fallback);
    }

    public function testParsesUrlReferenceWithNoneFallback(): void
    {
        $p = Paint::parse('url(#missing) none');
        self::assertInstanceOf(Url::class, $p);
        self::assertInstanceOf(None_::class, $p->fallback);
    }

    public function testUrlFallbackOfAnotherUrlIsStrippedPerSpec(): void
    {
        // SVG 2 §13.2 grammar: fallback is `none | <color>`, not another
        // url. Real-world strict parsers reject the whole thing; we
        // preserve the leading url and drop the chained fallback.
        $p = Paint::parse('url(#a) url(#b)');
        self::assertInstanceOf(Url::class, $p);
        self::assertSame('a', $p->id);
        self::assertNull($p->fallback);
    }

    public function testMalformedReturnsNull(): void
    {
        self::assertNull(Paint::parse('definitely-not-a-paint'));
    }

    /**
     * The CSS serialiser writes `url("#id")` with quotes, so a paint
     * reaching an SVG element through the cascade arrives quoted. Both
     * forms must resolve to the same reference.
     */
    public function testQuotedUrlReferenceParses(): void
    {
        foreach (['url(#g)', 'url("#g")', "url('#g')", 'url( "#g" )'] as $raw) {
            $paint = Paint::parse($raw);
            self::assertInstanceOf(Url::class, $paint, $raw);
            self::assertSame('g', $paint->id, $raw);
        }
    }

    /**
     * SVG 2 §16.2 / CSS Values 4 §4.5.1 — leading and trailing
     * whitespace is stripped from a URL, including inside the quotes
     * of a quoted `url()`. WPT's
     * `svg/linking/reftests/url-processing-whitespace-001` writes all
     * four placements.
     */
    public function testWhitespaceInsideAQuotedUrlIsStripped(): void
    {
        foreach (["url(' #green') red", "url('#green ') red", "url(' #green ') red"] as $raw) {
            $paint = Paint::parse($raw);
            self::assertInstanceOf(Url::class, $paint, $raw);
            self::assertSame('green', $paint->id, $raw);
        }
    }

    public function testWhitespaceAroundAnUnquotedUrlIsStripped(): void
    {
        $paint = Paint::parse('url(  #green  ) red');
        self::assertInstanceOf(Url::class, $paint);
        self::assertSame('green', $paint->id);
    }

    public function testInternalWhitespaceIsNotStrippedSoTheReferenceStaysUnresolvable(): void
    {
        // `url(' # red ')` trims to `# red`, whose fragment still
        // contains a space. That names no element, so the paint falls
        // back — it must NOT be "helpfully" read as `#red`.
        $paint = Paint::parse("url(' # red ') green");
        self::assertInstanceOf(Url::class, $paint);
        self::assertNotSame('red', $paint->id);
        self::assertInstanceOf(SolidColor::class, $paint->fallback);
    }

    public function testDoubleQuotedUrlParses(): void
    {
        $paint = Paint::parse('url("#grad")');
        self::assertInstanceOf(Url::class, $paint);
        self::assertSame('grad', $paint->id);
    }

    public function testMismatchedQuotesAreNotStripped(): void
    {
        // Guard: only a MATCHED pair of quotes is a CSS string. A lone
        // quote is a parse error, and swallowing it would turn
        // malformed author input into a silently different reference.
        self::assertNull(Paint::parse('url(\'#grad")'));
    }

    public function testNonFragmentUrlIsNotTreatedAsALocalReference(): void
    {
        // Guard: an external reference is not a local `#id`. Reading
        // one as a local id would resolve it against the wrong
        // document.
        self::assertNull(Paint::parse('url(http://example.test/a.svg#g) red'));
    }
}
