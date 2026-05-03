<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\ClassMap;
use Phpdftk\Pdf\Core\Document\RoleMap;
use Phpdftk\Pdf\Core\Document\StructAttribute;
use Phpdftk\Pdf\Core\Document\StructTreeRoot;
use Phpdftk\Pdf\Core\PdfName;
use PHPUnit\Framework\TestCase;

class AccessibilityHelpersTest extends TestCase
{
    public function testRoleMap(): void
    {
        $map = new RoleMap();
        $map->map('Note', 'P')->map('Heading1', 'H1');
        $pdf = $map->toPdf();
        self::assertStringContainsString('/Note /P', $pdf);
        self::assertStringContainsString('/Heading1 /H1', $pdf);
    }

    public function testClassMapWithStructAttribute(): void
    {
        $layout = new StructAttribute('Layout');
        $layout->entries['Placement'] = new PdfName('Block');

        $cm = new ClassMap();
        $cm->set('MyClass', $layout);
        $pdf = $cm->toPdf();
        self::assertStringContainsString('/MyClass', $pdf);
        self::assertStringContainsString('/O /Layout', $pdf);
        self::assertStringContainsString('/Placement /Block', $pdf);
    }

    public function testStructAttribute(): void
    {
        $attr = new StructAttribute('Table');
        $attr->entries['RowSpan'] = 2;
        $attr->entries['ColSpan'] = 3;
        $pdf = $attr->toPdf();
        self::assertStringContainsString('/O /Table', $pdf);
        self::assertStringContainsString('/RowSpan 2', $pdf);
        self::assertStringContainsString('/ColSpan 3', $pdf);
    }

    public function testStructTreeRootAcceptsTypedMaps(): void
    {
        $root = new StructTreeRoot();
        $root->objectNumber = 1;
        $root->roleMap = (new RoleMap())->map('Note', 'P');
        $root->classMap = (new ClassMap())->set('L', new StructAttribute('Layout'));
        $pdf = $root->toPdf();
        self::assertStringContainsString('/RoleMap', $pdf);
        self::assertStringContainsString('/ClassMap', $pdf);
        self::assertStringContainsString('/Note /P', $pdf);
    }
}
