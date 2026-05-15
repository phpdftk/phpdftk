<?php

declare(strict_types=1);

namespace Phpdftk\Crypt\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Crypt\SaslPrep;

class SaslPrepTest extends TestCase
{
    public function testAsciiPasswordPassesThroughUnchanged(): void
    {
        $this->assertSame('password123', SaslPrep::prepare('password123'));
    }

    public function testNonAsciiSpaceMappedToRegularSpace(): void
    {
        // U+00A0 (NO-BREAK SPACE) → U+0020
        $input = "hello\xC2\xA0world";
        $this->assertSame('hello world', SaslPrep::prepare($input));
    }

    public function testNfkcNormalization(): void
    {
        if (!class_exists(\Normalizer::class)) {
            $this->markTestSkipped('intl extension not available');
        }

        // n (U+006E) + combining tilde (U+0303) → ñ (U+00F1)
        $decomposed = "n\xCC\x83";
        $composed = "\xC3\xB1";
        $this->assertSame($composed, SaslPrep::prepare($decomposed));
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', SaslPrep::prepare(''));
    }

    public function testProhibitedAsciiControlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prohibited character');
        // U+0007 BEL
        SaslPrep::prepare("pass\x07word");
    }

    public function testMappedToNothingCharsRemoved(): void
    {
        // U+00AD SOFT HYPHEN should be removed
        $input = "pass\xC2\xADword";
        $this->assertSame('password', SaslPrep::prepare($input));
    }

    public function testAsciiSpecialCharsPreserved(): void
    {
        $this->assertSame('p@ss!w0rd', SaslPrep::prepare('p@ss!w0rd'));
    }

    public function testMultipleNonAsciiSpacesNormalized(): void
    {
        // U+2000 EN QUAD + U+2001 EM QUAD
        $input = "a\xE2\x80\x80b\xE2\x80\x81c";
        $this->assertSame('a b c', SaslPrep::prepare($input));
    }

    public function testZeroWidthNoBreakSpaceRemoved(): void
    {
        // U+FEFF BOM / ZWNBSP is mapped to nothing
        $input = "\xEF\xBB\xBFpassword";
        $this->assertSame('password', SaslPrep::prepare($input));
    }

    public function testProhibitedNonAsciiControlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // U+0080 (non-ASCII control)
        SaslPrep::prepare("test\xC2\x80");
    }

    public function testProhibitedNonCharacterThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // U+FFFE (non-character)
        SaslPrep::prepare("test\xEF\xBF\xBE");
    }

    public function testVariationSelectorsRemoved(): void
    {
        // U+FE0F VARIATION SELECTOR-16
        $input = "test\xEF\xB8\x8Fword";
        $this->assertSame('testword', SaslPrep::prepare($input));
    }

    public function testIdeographicSpaceMapped(): void
    {
        // U+3000 IDEOGRAPHIC SPACE → U+0020
        $input = "hello\xE3\x80\x80world";
        $this->assertSame('hello world', SaslPrep::prepare($input));
    }

    public function testProhibitedAdditionalNonAsciiControl(): void
    {
        // U+200C ZERO WIDTH NON-JOINER
        $this->expectException(\InvalidArgumentException::class);
        SaslPrep::prepare("test\xE2\x80\x8Cword");
    }

    public function testProhibitedPrivateUseArea(): void
    {
        // U+E000 (Private Use Area)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('private use');
        SaslPrep::prepare("test\xEE\x80\x80");
    }

    public function testProhibitedSurrogateBlocked(): void
    {
        // Invalid: U+D800 in UTF-8 (technically illegal in UTF-8 but the parser still extracts the codepoint)
        $bytes = "\xED\xA0\x80";  // surrogate U+D800
        $this->expectException(\InvalidArgumentException::class);
        SaslPrep::prepare("ok" . $bytes);
    }

    public function testProhibitedInappropriateForPlainText(): void
    {
        // U+FFF9 INTERLINEAR ANNOTATION ANCHOR
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('plain text');
        SaslPrep::prepare("test\xEF\xBF\xB9");
    }

    public function testProhibitedChangeDisplayProperties(): void
    {
        // U+200E LEFT-TO-RIGHT MARK
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('change display');
        SaslPrep::prepare("test\xE2\x80\x8E");
    }

    public function testProhibitedTaggingCharacter(): void
    {
        // U+E0020 (tag SP)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tagging');
        // U+E0020 in UTF-8 = F3 A0 80 A0
        SaslPrep::prepare("test\xF3\xA0\x80\xA0");
    }

    public function testProhibitedDelete(): void
    {
        // U+007F DELETE
        $this->expectException(\InvalidArgumentException::class);
        SaslPrep::prepare("test\x7F");
    }

    public function testRtlOnlyStringPasses(): void
    {
        // RandALCat-only Hebrew word — should pass bidi rules
        $input = "\xD7\xA9\xD7\x9C\xD7\x95\xD7\x9D"; // שלום
        $result = SaslPrep::prepare($input);
        $this->assertNotEmpty($result);
    }

    public function testRtlMixedWithLatinThrows(): void
    {
        // Mixing Hebrew (RandALCat) with Latin (LCat) violates RFC 3454 §6.
        $input = "abc\xD7\xA9";
        $this->expectException(\InvalidArgumentException::class);
        SaslPrep::prepare($input);
    }

    public function testRtlFirstLastMustBeRandALCatThrows(): void
    {
        // A digit (not RandALCat) sandwiched as first/last char with Hebrew in middle
        // would trip the first/last RandALCat check, but mixing rule fires first if LCat
        // is present. Instead test RandALCat surrounded by a non-RandALCat non-LCat (e.g., space).
        // Per the source, "space" is mapped to U+0020 which is not RandALCat — should throw "first and last"
        $input = " \xD7\xA9 ";
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('first and last');
        SaslPrep::prepare($input);
    }
}
