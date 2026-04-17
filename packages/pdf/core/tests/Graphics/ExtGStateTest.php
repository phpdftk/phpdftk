<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Graphics;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Graphics\ExtGState;
use ApprLabs\Pdf\Core\Graphics\SoftMask;
use ApprLabs\Pdf\Core\Graphics\XObject\PostScriptXObject;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;

class ExtGStateTest extends TestCase
{
    // -----------------------------------------------------------------------
    // ExtGState — new fields
    // -----------------------------------------------------------------------

    public function testExtGStateBg(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->bg = 'Default';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/BG /Default', $pdf);
    }

    public function testExtGStateBg2(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->bg2 = 'Default';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/BG2 /Default', $pdf);
    }

    public function testExtGStateUcr(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->ucr = 'Identity';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/UCR /Identity', $pdf);
    }

    public function testExtGStateUcr2(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->ucr2 = 'Default';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/UCR2 /Default', $pdf);
    }

    public function testExtGStateTr(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->tr = 'Identity';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/TR /Identity', $pdf);
    }

    public function testExtGStateTr2(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->tr2 = 'Default';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/TR2 /Default', $pdf);
    }

    public function testExtGStateHt(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->ht = 'Default';
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/HT /Default', $pdf);
    }

    public function testExtGStateUseBlackPtComp(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->useBlackPtComp = new PdfName('ON');
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/UseBlackPtComp /ON', $pdf);
    }

    public function testExtGStateHto(): void
    {
        $gs = new ExtGState();
        $gs->objectNumber = 1;
        $gs->hto = new PdfArray([new PdfNumber(10), new PdfNumber(20)]);
        $pdf = $gs->toPdf();
        self::assertStringContainsString('/HTO [ 10 20 ]', $pdf);
    }

    // -----------------------------------------------------------------------
    // SoftMask
    // -----------------------------------------------------------------------

    public function testSoftMaskAlpha(): void
    {
        $ref = new PdfReference(5, 0);
        $mask = new SoftMask('Alpha', $ref);
        $pdf = $mask->toPdf();
        self::assertStringContainsString('/Type /Mask', $pdf);
        self::assertStringContainsString('/S /Alpha', $pdf);
        self::assertStringContainsString('/G 5 0 R', $pdf);
    }

    public function testSoftMaskLuminosity(): void
    {
        $ref = new PdfReference(7, 0);
        $mask = new SoftMask('Luminosity', $ref);
        $pdf = $mask->toPdf();
        self::assertStringContainsString('/S /Luminosity', $pdf);
    }

    public function testSoftMaskWithBackdropColor(): void
    {
        $ref = new PdfReference(5, 0);
        $mask = new SoftMask('Alpha', $ref);
        $mask->bc = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(1)]);
        $pdf = $mask->toPdf();
        self::assertStringContainsString('/BC [ 1 1 1 ]', $pdf);
    }

    public function testSoftMaskWithTransferFunction(): void
    {
        $ref = new PdfReference(5, 0);
        $mask = new SoftMask('Alpha', $ref);
        $mask->tr = 'Identity';
        $pdf = $mask->toPdf();
        self::assertStringContainsString('/TR /Identity', $pdf);
    }

    // -----------------------------------------------------------------------
    // PostScriptXObject
    // -----------------------------------------------------------------------

    public function testPostScriptXObjectType(): void
    {
        $ps = new PostScriptXObject('% PostScript code');
        $ps->objectNumber = 1;
        $pdf = $ps->toPdf();
        self::assertStringContainsString('/Type /XObject', $pdf);
        self::assertStringContainsString('/Subtype /PS', $pdf);
        self::assertStringContainsString('stream', $pdf);
        self::assertStringContainsString('% PostScript code', $pdf);
    }

    public function testPostScriptXObjectWithLevel1(): void
    {
        $ps = new PostScriptXObject('% PS Level 2');
        $ps->objectNumber = 1;
        $ps->level1 = new PdfReference(10, 0);
        $pdf = $ps->toPdf();
        self::assertStringContainsString('/Level1 10 0 R', $pdf);
    }
}
