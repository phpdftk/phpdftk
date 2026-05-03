<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Validator;

use Phpdftk\Pdf\Conformance\Constraint\ActionConstraint;
use Phpdftk\Pdf\Conformance\Constraint\AnnotationConstraint;
use Phpdftk\Pdf\Conformance\Constraint\DPartRootConstraint;
use Phpdftk\Pdf\Conformance\Constraint\ColorSpaceConstraint;
use Phpdftk\Pdf\Conformance\Constraint\ConformanceConstraint;
use Phpdftk\Pdf\Conformance\Constraint\DisplayDocTitleConstraint;
use Phpdftk\Pdf\Conformance\Constraint\EmbeddedFileConstraint;
use Phpdftk\Pdf\Conformance\Constraint\EncryptionConstraint;
use Phpdftk\Pdf\Conformance\Constraint\FilterConstraint;
use Phpdftk\Pdf\Conformance\Constraint\FontEmbeddingConstraint;
use Phpdftk\Pdf\Conformance\Constraint\FormConstraint;
use Phpdftk\Pdf\Conformance\Constraint\MetadataConstraint;
use Phpdftk\Pdf\Conformance\Constraint\MultimediaConstraint;
use Phpdftk\Pdf\Conformance\Constraint\OutputIntentConstraint;
use Phpdftk\Pdf\Conformance\Constraint\PdfEActionConstraint;
use Phpdftk\Pdf\Conformance\Constraint\PdfEColorSpaceConstraint;
use Phpdftk\Pdf\Conformance\Constraint\PdfRActionConstraint;
use Phpdftk\Pdf\Conformance\Constraint\PdfRFontConstraint;
use Phpdftk\Pdf\Conformance\Constraint\RasterContentConstraint;
use Phpdftk\Pdf\Conformance\Constraint\ReferenceXObjectConstraint;
use Phpdftk\Pdf\Conformance\Constraint\ZugferdInvoiceConstraint;
use Phpdftk\Pdf\Conformance\Constraint\ZugferdXmpConstraint;
use Phpdftk\Pdf\Conformance\Constraint\TabOrderConstraint;
use Phpdftk\Pdf\Conformance\Constraint\TaggedStructureConstraint;
use Phpdftk\Pdf\Conformance\Constraint\ThreeDContentConstraint;
use Phpdftk\Pdf\Conformance\Constraint\TrappedConstraint;
use Phpdftk\Pdf\Conformance\Constraint\TransparencyConstraint;
use Phpdftk\Pdf\Conformance\Constraint\TrimBoxConstraint;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfVtProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfMailProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfXProfile;
use Phpdftk\Pdf\Conformance\Profile\ZugferdProfile;

/**
 * Maps each conformance profile to its set of applicable constraints.
 */
final class ProfileConstraintRegistry
{
    /**
     * Get the constraints applicable to the given profile.
     *
     * @return list<ConformanceConstraint>
     */
    public function getConstraints(ConformanceProfile $profile): array
    {
        if ($profile instanceof PdfAProfile) {
            return $this->getPdfAConstraints($profile);
        }

        if ($profile instanceof PdfUaProfile) {
            return $this->getPdfUaConstraints($profile);
        }

        if ($profile instanceof PdfXProfile) {
            return $this->getPdfXConstraints($profile);
        }

        if ($profile instanceof PdfVtProfile) {
            return $this->getPdfVtConstraints($profile);
        }

        if ($profile instanceof PdfEProfile) {
            return $this->getPdfEConstraints();
        }

        if ($profile instanceof PdfRProfile) {
            return $this->getPdfRConstraints();
        }

        if ($profile instanceof ZugferdProfile) {
            return $this->getZugferdConstraints($profile);
        }

        if ($profile instanceof PdfMailProfile) {
            return $this->getPdfMailConstraints();
        }

        return [];
    }

    /** @return list<ConformanceConstraint> */
    private function getPdfAConstraints(PdfAProfile $profile): array
    {
        // Core constraints shared by all PDF/A levels
        $constraints = [
            new FontEmbeddingConstraint(),
            new EncryptionConstraint(),
            new MetadataConstraint(),
            new OutputIntentConstraint(),
            new ColorSpaceConstraint(),
            new ActionConstraint(),
            new EmbeddedFileConstraint(),
        ];

        // PDF/A-1 only: no transparency, no LZWDecode
        if ($profile->getPart() === 1) {
            $constraints[] = new TransparencyConstraint();
            $constraints[] = new FilterConstraint();
        }

        // Level A requires tagged structure
        if ($profile->requiresTaggedStructure()) {
            $constraints[] = new TaggedStructureConstraint();
        }

        return $constraints;
    }

    /** @return list<ConformanceConstraint> */
    private function getPdfUaConstraints(PdfUaProfile $profile): array
    {
        return [
            new TaggedStructureConstraint(),
            new MetadataConstraint(),
            new FontEmbeddingConstraint(),
            new DisplayDocTitleConstraint(),
            new TabOrderConstraint(),
            new AnnotationConstraint(),
        ];
    }

    /** @return list<ConformanceConstraint> */
    private function getPdfXConstraints(PdfXProfile $profile): array
    {
        $constraints = [
            new OutputIntentConstraint(),
            new MetadataConstraint(),
            new EncryptionConstraint(),
            new FontEmbeddingConstraint(),
            new TrimBoxConstraint(),
            new TrappedConstraint(),
        ];

        // PDF/X-1a and X-3 prohibit transparency; X-4+ allows it
        if ($profile->prohibitsTransparency()) {
            $constraints[] = new TransparencyConstraint();
        }

        // PDF/X-5 profiles validate reference XObjects
        if ($profile->supportsReferenceXObjects()) {
            $constraints[] = new ReferenceXObjectConstraint();
        }

        return $constraints;
    }

    /**
     * PDF/VT builds on PDF/X-4 and adds DPartRoot requirement.
     *
     * @return list<ConformanceConstraint>
     */
    private function getPdfVtConstraints(PdfVtProfile $profile): array
    {
        return [
            new OutputIntentConstraint(),
            new MetadataConstraint(),
            new EncryptionConstraint(),
            new FontEmbeddingConstraint(),
            new TrimBoxConstraint(),
            new TrappedConstraint(),
            new DPartRootConstraint(),
        ];
    }

    /**
     * PDF/E-1: embedded fonts, XMP metadata, no encryption,
     * 3D content validation, action restrictions, color space anchoring.
     *
     * @return list<ConformanceConstraint>
     */
    private function getPdfEConstraints(): array
    {
        return [
            new FontEmbeddingConstraint(),
            new MetadataConstraint(),
            new EncryptionConstraint(),
            new ThreeDContentConstraint(),
            new PdfEActionConstraint(),
            new PdfEColorSpaceConstraint(),
        ];
    }

    /**
     * PDF/R-1: XMP metadata, no encryption, raster-only content,
     * action restrictions, font presence warning.
     *
     * @return list<ConformanceConstraint>
     */
    private function getPdfRConstraints(): array
    {
        return [
            new MetadataConstraint(),
            new EncryptionConstraint(),
            new RasterContentConstraint(),
            new PdfRActionConstraint(),
            new PdfRFontConstraint(),
        ];
    }

    /**
     * ZUGFeRD / Factur-X: PDF/A-3b base constraints plus invoice-specific
     * XMP and embedded file validation.
     *
     * @return list<ConformanceConstraint>
     */
    private function getZugferdConstraints(ZugferdProfile $profile): array
    {
        // Start with all PDF/A-3b constraints
        $constraints = $this->getPdfAConstraints($profile->getBaseProfile());

        // Add ZUGFeRD-specific constraints
        $constraints[] = new ZugferdXmpConstraint();
        $constraints[] = new ZugferdInvoiceConstraint();

        return $constraints;
    }

    /**
     * PDF/mail-1: no encryption, no JavaScript, fonts embedded,
     * no interactive forms, no multimedia.
     *
     * @return list<ConformanceConstraint>
     */
    private function getPdfMailConstraints(): array
    {
        return [
            new EncryptionConstraint(),
            new MetadataConstraint(),
            new FontEmbeddingConstraint(),
            new ActionConstraint(),
            new FormConstraint(),
            new MultimediaConstraint(),
        ];
    }
}
