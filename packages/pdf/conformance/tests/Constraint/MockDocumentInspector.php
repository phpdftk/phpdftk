<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\Graphics\XObject\ImageXObject;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;

/**
 * Mock document inspector for unit testing constraints.
 */
final class MockDocumentInspector implements DocumentInspector
{
    /** @var list<PdfObject> */
    private array $objects;

    /** @var list<PdfObject> */
    private array $fonts;

    /** @var list<ThreeDStream> */
    private array $threeDStreams;

    /** @var list<ImageXObject> */
    private array $imageXObjects;

    /** @var list<FormXObject> */
    private array $referenceXObjects;

    /**
     * @param list<PdfObject> $registeredObjects
     * @param list<PdfObject> $fonts
     * @param list<ThreeDStream> $threeDStreams
     * @param list<ImageXObject> $imageXObjects
     * @param list<FormXObject> $referenceXObjects
     */
    public function __construct(
        private readonly bool $hasEncryption = false,
        private readonly bool $hasXmpMetadata = false,
        private readonly ?string $xmpBytes = null,
        private readonly bool $hasOutputIntents = false,
        private readonly bool $hasOutputIntentWithIccProfile = false,
        private readonly bool $hasTransparency = false,
        private readonly bool $hasJavaScript = false,
        private readonly bool $hasEmbeddedFiles = false,
        array $registeredObjects = [],
        array $fonts = [],
        private readonly ?Catalog $catalog = null,
        private readonly ?Info $info = null,
        private readonly bool $hasThreeDAnnotations = false,
        array $threeDStreams = [],
        private readonly bool $hasRasterOnlyContent = true,
        array $imageXObjects = [],
        private readonly bool $hasInteractiveForms = false,
        private readonly bool $hasMultimediaContent = false,
        array $referenceXObjects = [],
    ) {
        $this->objects = $registeredObjects;
        $this->fonts = $fonts;
        $this->threeDStreams = $threeDStreams;
        $this->imageXObjects = $imageXObjects;
        $this->referenceXObjects = $referenceXObjects;
    }

    public function getCatalog(): Catalog
    {
        return $this->catalog ?? new Catalog();
    }

    public function getInfo(): ?Info
    {
        return $this->info;
    }

    public function getPages(): iterable
    {
        return [];
    }

    public function getFonts(): iterable
    {
        return $this->fonts;
    }

    public function hasEncryption(): bool
    {
        return $this->hasEncryption;
    }

    public function hasXmpMetadata(): bool
    {
        return $this->hasXmpMetadata;
    }

    public function getXmpBytes(): ?string
    {
        return $this->xmpBytes;
    }

    public function hasOutputIntents(): bool
    {
        return $this->hasOutputIntents;
    }

    public function hasOutputIntentWithIccProfile(): bool
    {
        return $this->hasOutputIntentWithIccProfile;
    }

    public function hasTransparency(): bool
    {
        return $this->hasTransparency;
    }

    public function hasJavaScript(): bool
    {
        return $this->hasJavaScript;
    }

    public function hasEmbeddedFiles(): bool
    {
        return $this->hasEmbeddedFiles;
    }

    public function getRegisteredObjects(): iterable
    {
        return $this->objects;
    }

    public function hasThreeDAnnotations(): bool
    {
        return $this->hasThreeDAnnotations;
    }

    public function getThreeDStreams(): iterable
    {
        return $this->threeDStreams;
    }

    public function hasRasterOnlyContent(): bool
    {
        return $this->hasRasterOnlyContent;
    }

    public function getImageXObjects(): iterable
    {
        return $this->imageXObjects;
    }

    public function hasInteractiveForms(): bool
    {
        return $this->hasInteractiveForms;
    }

    public function hasMultimediaContent(): bool
    {
        return $this->hasMultimediaContent;
    }

    public function getReferenceXObjects(): iterable
    {
        return $this->referenceXObjects;
    }
}
