<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Inspection;

use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Annotation\MovieAnnotation;
use Phpdftk\Pdf\Core\Annotation\RichMediaAnnotation;
use Phpdftk\Pdf\Core\Annotation\ScreenAnnotation;
use Phpdftk\Pdf\Core\Annotation\SoundAnnotation;
use Phpdftk\Pdf\Core\Annotation\ThreeDAnnotation;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\MetadataStream;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\Font\Font;
use Phpdftk\Pdf\Core\Font\Type0Font;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\Graphics\XObject\ImageXObject;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Multimedia\MediaRendition;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;

/**
 * Inspects a PdfFileWriter's internal state for conformance validation.
 */
final class WriterDocumentInspector implements DocumentInspector
{
    /** @var array<string, Font|Type0Font> */
    private array $fonts;

    /**
     * @param array<string, Font|Type0Font> $fonts Font map from PdfWriter
     */
    public function __construct(
        private readonly Catalog $catalog,
        private readonly PdfFileWriter $fileWriter,
        array $fonts = [],
    ) {
        $this->fonts = $fonts;
    }

    public function getCatalog(): Catalog
    {
        return $this->catalog;
    }

    public function getInfo(): ?Info
    {
        return $this->fileWriter->getInfo();
    }

    public function getPages(): iterable
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof Page) {
                yield $object;
            }
        }
    }

    public function getFonts(): iterable
    {
        foreach ($this->fonts as $font) {
            if ($font instanceof Font) {
                yield $font;
            }
        }
        // Also yield Type0Font instances (they extend PdfObject, not Font)
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof Type0Font) {
                yield $object;
            }
        }
    }

    public function hasEncryption(): bool
    {
        // Check if any registered object is an EncryptDictionary
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof \Phpdftk\Pdf\Core\Security\EncryptDictionary) {
                return true;
            }
        }
        return false;
    }

    public function hasXmpMetadata(): bool
    {
        return $this->catalog->metadata !== null;
    }

    public function getXmpBytes(): ?string
    {
        if ($this->catalog->metadata === null) {
            return null;
        }

        // Find the MetadataStream by object number
        $objNum = $this->catalog->metadata->objectNumber;
        $objects = $this->fileWriter->getRegistry()->getAll();
        if (isset($objects[$objNum]) && $objects[$objNum] instanceof MetadataStream) {
            return $objects[$objNum]->data;
        }
        if (isset($objects[$objNum]) && $objects[$objNum] instanceof PdfStream) {
            return $objects[$objNum]->data;
        }
        return null;
    }

    public function hasOutputIntents(): bool
    {
        return $this->catalog->outputIntents !== null
            && count($this->catalog->outputIntents->items) > 0;
    }

    public function hasOutputIntentWithIccProfile(): bool
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof \Phpdftk\Pdf\Core\Document\OutputIntent
                && $object->destOutputProfile !== null
            ) {
                return true;
            }
        }
        return false;
    }

    public function hasTransparency(): bool
    {
        foreach ($this->getPages() as $page) {
            if ($page->group !== null) {
                return true;
            }
        }
        return false;
    }

    public function hasJavaScript(): bool
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof JavaScriptAction) {
                return true;
            }
        }
        return false;
    }

    public function hasEmbeddedFiles(): bool
    {
        return $this->catalog->names !== null;
    }

    public function getRegisteredObjects(): iterable
    {
        yield from $this->fileWriter->getRegistry()->getAll();
    }

    public function hasThreeDAnnotations(): bool
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof ThreeDAnnotation) {
                return true;
            }
        }
        return false;
    }

    public function getThreeDStreams(): iterable
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof ThreeDStream) {
                yield $object;
            }
        }
    }

    public function hasRasterOnlyContent(): bool
    {
        // Heuristic: if any fonts are registered, pages likely contain text
        foreach ($this->getFonts() as $_) {
            return false;
        }
        return true;
    }

    public function getImageXObjects(): iterable
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof ImageXObject) {
                yield $object;
            }
        }
    }

    public function hasInteractiveForms(): bool
    {
        return $this->catalog->acroForm !== null;
    }

    public function hasMultimediaContent(): bool
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof MovieAnnotation
                || $object instanceof SoundAnnotation
                || $object instanceof ScreenAnnotation
                || $object instanceof RichMediaAnnotation
                || $object instanceof MediaRendition
            ) {
                return true;
            }
        }
        return false;
    }

    public function getReferenceXObjects(): iterable
    {
        foreach ($this->fileWriter->getRegistry()->getAll() as $object) {
            if ($object instanceof FormXObject && $object->ref !== null) {
                yield $object;
            }
        }
    }
}
