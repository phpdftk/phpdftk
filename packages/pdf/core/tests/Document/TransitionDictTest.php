<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Document\TransitionDict;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;

class TransitionDictTest extends TestCase
{
    public function testTransitionDictType(): void
    {
        $t = new TransitionDict();
        self::assertStringContainsString('/Type /Trans', $t->toPdf());
    }

    public function testTransitionDictDissolve(): void
    {
        $t = new TransitionDict();
        $t->s = new PdfName('Dissolve');
        self::assertStringContainsString('/S /Dissolve', $t->toPdf());
    }

    public function testTransitionDictDuration(): void
    {
        $t = new TransitionDict();
        $t->s = new PdfName('Wipe');
        $t->d = new PdfNumber(2.0);
        self::assertStringContainsString('/D 2', $t->toPdf());
    }

    public function testTransitionDictSplitHorizontal(): void
    {
        $t = new TransitionDict();
        $t->s  = new PdfName('Split');
        $t->dm = new PdfName('H');
        $t->m  = new PdfName('I');
        $pdf = $t->toPdf();
        self::assertStringContainsString('/Dm /H', $pdf);
        self::assertStringContainsString('/M /I', $pdf);
    }

    public function testTransitionDictDirection(): void
    {
        $t = new TransitionDict();
        $t->s  = new PdfName('Glitter');
        $t->di = new PdfNumber(315);
        self::assertStringContainsString('/Di 315', $t->toPdf());
    }

    public function testTransitionDictFlyScale(): void
    {
        $t = new TransitionDict();
        $t->s  = new PdfName('Fly');
        $t->ss = new PdfNumber(1.0);
        $t->b  = true;
        $pdf = $t->toPdf();
        self::assertStringContainsString('/SS', $pdf);
        self::assertStringContainsString('/B true', $pdf);
    }

    public function testTransitionDictFlyOpaqueOff(): void
    {
        $t = new TransitionDict();
        $t->s = new PdfName('Fly');
        $t->b = false;
        self::assertStringContainsString('/B false', $t->toPdf());
    }

    public function testTransitionDictAllStyles(): void
    {
        $styles = ['Split', 'Blinds', 'Box', 'Wipe', 'Dissolve', 'Glitter', 'R', 'Fly', 'Push', 'Cover', 'Uncover', 'Fade'];
        foreach ($styles as $style) {
            $t = new TransitionDict();
            $t->s = new PdfName($style);
            self::assertStringContainsString('/S /' . $style, $t->toPdf());
        }
    }

    public function testTransitionDictOnPage(): void
    {
        $t = new TransitionDict();
        $t->s = new PdfName('Dissolve');
        $t->d = new PdfNumber(1.0);

        $page = new \Phpdftk\Pdf\Core\Document\Page();
        $page->objectNumber = 1;
        $page->transition = $t;
        $pdf = $page->toPdf();
        self::assertStringContainsString('/Trans', $pdf);
        self::assertStringContainsString('/Dissolve', $pdf);
    }
}
