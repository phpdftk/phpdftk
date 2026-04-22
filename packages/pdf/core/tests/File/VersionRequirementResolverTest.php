<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\File;

use ApprLabs\Pdf\Core\DeprecatedPdfFeature;
use ApprLabs\Pdf\Core\File\VersionRequirementResolver;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;
use ApprLabs\Pdf\Core\Annotation\RedactAnnotation;
use ApprLabs\Pdf\Core\Document\CrossReferenceStream;
use ApprLabs\Pdf\Core\Document\DPartRoot;
use ApprLabs\Pdf\Core\Document\MarkInfo;
use ApprLabs\Pdf\Core\Document\ViewerPreferences;
use ApprLabs\Pdf\Core\Graphics\ExtGState;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType2;
use ApprLabs\Pdf\Core\Multimedia\Movie;
use ApprLabs\Pdf\Core\Graphics\XObject\PostScriptXObject;
use ApprLabs\Pdf\Core\Document\StandardStructureType;
use ApprLabs\Pdf\Core\Document\StructElem;
use PHPUnit\Framework\TestCase;

class VersionRequirementResolverTest extends TestCase
{
    protected function setUp(): void
    {
        VersionRequirementResolver::clearCache();
    }

    public function testClassLevelRequirement(): void
    {
        $this->assertSame(PdfVersion::V1_5, VersionRequirementResolver::getClassRequirement(RedactAnnotation::class));
        $this->assertSame(PdfVersion::V2_0, VersionRequirementResolver::getClassRequirement(DPartRoot::class));
        $this->assertNull(VersionRequirementResolver::getClassRequirement(ViewerPreferences::class));
    }

    public function testClassLevelRequirementFromInstance(): void
    {
        $obj = new DPartRoot(new \ApprLabs\Pdf\Core\PdfReference(1));
        $this->assertSame(PdfVersion::V2_0, VersionRequirementResolver::getClassRequirement($obj));
    }

    public function testEffectiveRequirementClassOnly(): void
    {
        // Use class-string for classes with complex constructors
        $this->assertSame(PdfVersion::V1_3, VersionRequirementResolver::getClassRequirement(ShadingType2::class));
    }

    public function testEffectiveRequirementPropertyNull(): void
    {
        // ViewerPreferences has no class-level requirement, and $enforce is null
        $vp = new ViewerPreferences();
        $this->assertSame(PdfVersion::V1_0, VersionRequirementResolver::getEffectiveRequirement($vp));
    }

    public function testEffectiveRequirementPropertySet(): void
    {
        $vp = new ViewerPreferences();
        $vp->enforce = new \ApprLabs\Pdf\Core\PdfArray([]);
        $this->assertSame(PdfVersion::V2_0, VersionRequirementResolver::getEffectiveRequirement($vp));
    }

    public function testEffectiveRequirementExtGStateTransparency(): void
    {
        $gs = new ExtGState();
        // No transparency properties set — should be V1_0
        $this->assertSame(PdfVersion::V1_0, VersionRequirementResolver::getEffectiveRequirement($gs));

        // Set stroke alpha → bumps to 1.4
        $gs->ca = 0.5;
        $this->assertSame(PdfVersion::V1_4, VersionRequirementResolver::getEffectiveRequirement($gs));
    }

    public function testMarkInfoPropertyLevel(): void
    {
        $mi = new MarkInfo();
        $mi->marked = true;
        $this->assertSame(PdfVersion::V1_0, VersionRequirementResolver::getEffectiveRequirement($mi));

        $mi->userProperties = true;
        $this->assertSame(PdfVersion::V1_6, VersionRequirementResolver::getEffectiveRequirement($mi));
    }

    public function testDeprecation(): void
    {
        $dep = VersionRequirementResolver::getDeprecation(Movie::class);
        $this->assertNotNull($dep);
        $this->assertSame('2.0', $dep->since);
        $this->assertSame('RichMediaAnnotation', $dep->replacement);
    }

    public function testDeprecationPostScriptXObject(): void
    {
        $dep = VersionRequirementResolver::getDeprecation(PostScriptXObject::class);
        $this->assertNotNull($dep);
        $this->assertSame('1.7.1', $dep->since);
        $this->assertNull($dep->replacement);
    }

    public function testNoDeprecation(): void
    {
        $this->assertNull(VersionRequirementResolver::getDeprecation(ViewerPreferences::class));
    }

    public function testCaching(): void
    {
        // Call twice — should use cache second time
        VersionRequirementResolver::getClassRequirement(DPartRoot::class);
        $this->assertSame(PdfVersion::V2_0, VersionRequirementResolver::getClassRequirement(DPartRoot::class));
    }

    public function testStructElemPdf20Type(): void
    {
        $elem = new StructElem(StandardStructureType::ASIDE);
        $this->assertSame(PdfVersion::V2_0, VersionRequirementResolver::getEffectiveRequirement($elem));
    }

    public function testStructElemPdf20DocumentFragment(): void
    {
        $elem = new StructElem(StandardStructureType::DOCUMENT_FRAGMENT);
        $this->assertSame(PdfVersion::V2_0, VersionRequirementResolver::getEffectiveRequirement($elem));
    }

    public function testStructElemPdf20TableTypes(): void
    {
        foreach (['THead', 'TBody', 'TFoot'] as $type) {
            $elem = new StructElem($type);
            $this->assertSame(PdfVersion::V2_0, VersionRequirementResolver::getEffectiveRequirement($elem));
        }
    }

    public function testStructElemPrePdf20Type(): void
    {
        $elem = new StructElem(StandardStructureType::P);
        $this->assertSame(PdfVersion::V1_0, VersionRequirementResolver::getEffectiveRequirement($elem));
    }

    public function testStructElemCustomType(): void
    {
        // Custom / non-standard types return no version constraint
        $elem = new StructElem('MyCustomType');
        $this->assertSame(PdfVersion::V1_0, VersionRequirementResolver::getEffectiveRequirement($elem));
    }

    public function testStandardStructureTypeMinimumVersion(): void
    {
        $this->assertSame(PdfVersion::V2_0, StandardStructureType::minimumVersion('Aside'));
        $this->assertSame(PdfVersion::V2_0, StandardStructureType::minimumVersion('FENote'));
        $this->assertSame(PdfVersion::V2_0, StandardStructureType::minimumVersion('Artifact'));
        $this->assertNull(StandardStructureType::minimumVersion('P'));
        $this->assertNull(StandardStructureType::minimumVersion('Table'));
        $this->assertNull(StandardStructureType::minimumVersion('UnknownType'));
    }
}
