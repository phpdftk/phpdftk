<?php

declare(strict_types=1);

namespace ApprLabs\Crypt\Tests;

use PHPUnit\Framework\TestCase;
use ApprLabs\Crypt\SaslPrep;

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
}
