<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Validator;

use ApprLabs\Pdf\Conformance\Constraint\ActionConstraint;
use ApprLabs\Pdf\Conformance\Constraint\AnnotationConstraint;
use ApprLabs\Pdf\Conformance\Constraint\DPartRootConstraint;
use ApprLabs\Pdf\Conformance\Constraint\ColorSpaceConstraint;
use ApprLabs\Pdf\Conformance\Constraint\ConformanceConstraint;
use ApprLabs\Pdf\Conformance\Constraint\DisplayDocTitleConstraint;
use ApprLabs\Pdf\Conformance\Constraint\EmbeddedFileConstraint;
use ApprLabs\Pdf\Conformance\Constraint\EncryptionConstraint;
use ApprLabs\Pdf\Conformance\Constraint\FilterConstraint;
use ApprLabs\Pdf\Conformance\Constraint\FontEmbeddingConstraint;
use ApprLabs\Pdf\Conformance\Constraint\FormConstraint;
use ApprLabs\Pdf\Conformance\Constraint\MetadataConstraint;
use ApprLabs\Pdf\Conformance\Constraint\MultimediaConstraint;
use ApprLabs\Pdf\Conformance\Constraint\OutputIntentConstraint;
use ApprLabs\Pdf\Conformance\Constraint\PdfEActionConstraint;
use ApprLabs\Pdf\Conformance\Constraint\PdfEColorSpaceConstraint;
use ApprLabs\Pdf\Conformance\Constraint\PdfRActionConstraint;
use ApprLabs\Pdf\Conformance\Constraint\PdfRFontConstraint;
use ApprLabs\Pdf\Conformance\Constraint\RasterContentConstraint;
use ApprLabs\Pdf\Conformance\Constraint\ReferenceXObjectConstraint;
use ApprLabs\Pdf\Conformance\Constraint\ZugferdInvoiceConstraint;
use ApprLabs\Pdf\Conformance\Constraint\ZugferdXmpConstraint;
use ApprLabs\Pdf\Conformance\Constraint\TabOrderConstraint;
use ApprLabs\Pdf\Conformance\Constraint\TaggedStructureConstraint;
use ApprLabs\Pdf\Conformance\Constraint\ThreeDContentConstraint;
use ApprLabs\Pdf\Conformance\Constraint\TrappedConstraint;
use ApprLabs\Pdf\Conformance\Constraint\TransparencyConstraint;
use ApprLabs\Pdf\Conformance\Constraint\TrimBoxConstraint;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfEProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfRProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfUaProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfVtProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfMailProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfXProfile;
use ApprLabs\Pdf\Conformance\Profile\ZugferdProfile;

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
