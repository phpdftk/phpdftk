<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Inspection;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\Graphics\XObject\ImageXObject;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;

/**
 * Abstract inspection interface for conformance validation.
 *
 * Provides read-only access to the document structure for constraint
 * checking, decoupled from whether the document was built via PdfWriter
 * or parsed via PdfReader.
 */
interface DocumentInspector
{
    public function getCatalog(): Catalog;

    public function getInfo(): ?Info;

    /** @return iterable<Page> */
    public function getPages(): iterable;

    /** @return iterable<PdfObject> All fonts registered (Font or Type0Font instances). */
    public function getFonts(): iterable;

    /** Whether the document has encryption configured. */
    public function hasEncryption(): bool;

    /** Whether the catalog has an XMP metadata stream. */
    public function hasXmpMetadata(): bool;

    /** Raw XMP XML bytes, or null if no metadata stream. */
    public function getXmpBytes(): ?string;

    /** Whether the catalog has OutputIntents. */
    public function hasOutputIntents(): bool;

    /** Whether any OutputIntent has an embedded ICC profile (/DestOutputProfile). */
    public function hasOutputIntentWithIccProfile(): bool;

    /** Whether any page uses a transparency group. */
    public function hasTransparency(): bool;

    /** Whether the document contains JavaScript actions. */
    public function hasJavaScript(): bool;

    /** Whether the document contains embedded files. */
    public function hasEmbeddedFiles(): bool;

    /**
     * All registered PdfObject instances.
     *
     * @return iterable<PdfObject>
     */
    public function getRegisteredObjects(): iterable;

    /** Whether the document contains 3D annotations. */
    public function hasThreeDAnnotations(): bool;

    /** @return iterable<ThreeDStream> All 3D streams in the document. */
    public function getThreeDStreams(): iterable;

    /** Whether all page content is raster-only (no text operators, no vector paths). */
    public function hasRasterOnlyContent(): bool;

    /** @return iterable<ImageXObject> All image XObjects in the document. */
    public function getImageXObjects(): iterable;

    /** Whether the document contains interactive forms (AcroForm). */
    public function hasInteractiveForms(): bool;

    /** Whether the document contains multimedia content (movies, sounds, renditions). */
    public function hasMultimediaContent(): bool;

    /** @return iterable<FormXObject> All FormXObjects with a /Ref (reference XObject) entry. */
    public function getReferenceXObjects(): iterable;
}
