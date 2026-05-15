<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Toolkit\TextBlock;
use PHPUnit\Framework\TestCase;

class TextBlockTest extends TestCase
{
    public function testTextOnly(): void
    {
        $b = new TextBlock('Hello');
        $this->assertSame('Hello', $b->text);
        $this->assertNull($b->fontName);
        $this->assertNull($b->fontSize);
    }

    public function testWithFontInfo(): void
    {
        $b = new TextBlock('World', 'Helvetica', 12.5);
        $this->assertSame('World', $b->text);
        $this->assertSame('Helvetica', $b->fontName);
        $this->assertSame(12.5, $b->fontSize);
    }
}
