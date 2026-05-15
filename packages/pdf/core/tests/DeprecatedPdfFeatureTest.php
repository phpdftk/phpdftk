<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests;

use Phpdftk\Pdf\Core\DeprecatedPdfFeature;
use Phpdftk\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class DeprecatedPdfFeatureTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $d = new DeprecatedPdfFeature(since: '1.5');
        $this->assertSame('1.5', $d->since);
        $this->assertNull($d->replacement);
        $this->assertNull($d->removedIn);
        $this->assertNull($d->removedInVersion);
    }

    public function testWithReplacement(): void
    {
        $d = new DeprecatedPdfFeature(since: '1.6', replacement: 'Use NewClass instead');
        $this->assertSame('Use NewClass instead', $d->replacement);
    }

    public function testRemovedInVersionParsed(): void
    {
        $d = new DeprecatedPdfFeature(since: '1.5', removedIn: '2.0');
        $this->assertSame('2.0', $d->removedIn);
        $this->assertSame(PdfVersion::V2_0, $d->removedInVersion);
    }
}
