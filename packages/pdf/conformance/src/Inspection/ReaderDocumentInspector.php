<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Inspection;

use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Annotation\Annotation;
use Phpdftk\Pdf\Core\Annotation\MovieAnnotation;
use Phpdftk\Pdf\Core\Annotation\RichMediaAnnotation;
use Phpdftk\Pdf\Core\Annotation\ScreenAnnotation;
use Phpdftk\Pdf\Core\Annotation\SoundAnnotation;
use Phpdftk\Pdf\Core\Annotation\ThreeDAnnotation;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Font\Font;
use Phpdftk\Pdf\Core\Font\Type0Font;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\Graphics\XObject\ImageXObject;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Multimedia\MediaRendition;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;
use Phpdftk\Pdf\Reader\PdfReader;

/**
 * Inspects a parsed PDF (via PdfReader) for conformance validation.
 *
 * Uses the reader's hydration API to obtain typed objects where possible,
 * and falls back to raw dictionary inspection for fields not yet hydrated.
 */
final class ReaderDocumentInspector implements DocumentInspector
{
    private ?Catalog $typedCatalog = null;
    private ?Info $typedInfo = null;

    public function __construct(
        private readonly PdfReader $reader,
    ) {}

    public function getCatalog(): Catalog
    {
        if ($this->typedCatalog === null) {
            $this->typedCatalog = $this->reader->getTypedCatalog();
        }
        return $this->typedCatalog;
    }

    public function getInfo(): ?Info
    {
        if ($this->typedInfo !== null) {
            return $this->typedInfo;
        }

        $infoDict = $this->reader->getInfo();
        if ($infoDict === null) {
            return null;
        }

        // Build a typed Info from the raw dictionary
        $info = new Info();
        $info->objectNumber = 0;
        $info->generationNumber = 0;

        $title = $infoDict->get('Title');
        if ($title instanceof PdfString) {
            $info->title = $title;
        }
        $author = $infoDict->get('Author');
        if ($author instanceof PdfString) {
            $info->author = $author;
        }
        $producer = $infoDict->get('Producer');
        if ($producer instanceof PdfString) {
            $info->producer = $producer;
        }
        $trapped = $infoDict->get('Trapped');
        if ($trapped instanceof PdfName) {
            $info->trapped = $trapped;
        }

        $this->typedInfo = $info;
        return $info;
    }

    public function getPages(): iterable
    {
        return $this->reader->getTypedPages();
    }

    public function getFonts(): iterable
    {
        // Walk all objects looking for Font and Type0Font instances
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof Font || $obj instanceof Type0Font) {
                yield $obj;
            }
        }
    }

    public function hasEncryption(): bool
    {
        return $this->reader->getTrailer()->has('Encrypt');
    }

    public function hasXmpMetadata(): bool
    {
        $catalogDict = $this->reader->getCatalog();
        return $catalogDict->has('Metadata');
    }

    public function getXmpBytes(): ?string
    {
        $catalogDict = $this->reader->getCatalog();
        $metaRef = $catalogDict->get('Metadata');
        if (!$metaRef instanceof PdfReference) {
            return null;
        }

        $obj = $this->reader->getResolver()->resolveReference($metaRef);
        if ($obj instanceof PdfStream) {
            return $obj->data;
        }

        return null;
    }

    public function hasOutputIntents(): bool
    {
        $catalogDict = $this->reader->getCatalog();
        $oi = $catalogDict->get('OutputIntents');
        if ($oi instanceof \Phpdftk\Pdf\Core\PdfArray) {
            return count($oi->items) > 0;
        }
        return false;
    }

    public function hasOutputIntentWithIccProfile(): bool
    {
        $catalogDict = $this->reader->getCatalog();
        $oi = $catalogDict->get('OutputIntents');
        if (!$oi instanceof \Phpdftk\Pdf\Core\PdfArray) {
            return false;
        }

        $resolver = $this->reader->getResolver();
        foreach ($oi->items as $item) {
            $dict = $item;
            if ($item instanceof PdfReference) {
                $dict = $resolver->resolveReference($item);
            }
            if ($dict instanceof PdfDictionary && $dict->has('DestOutputProfile')) {
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
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof JavaScriptAction) {
                return true;
            }
        }
        return false;
    }

    public function hasEmbeddedFiles(): bool
    {
        $catalogDict = $this->reader->getCatalog();
        if (!$catalogDict->has('Names')) {
            return false;
        }

        $namesRef = $catalogDict->get('Names');
        if ($namesRef instanceof PdfReference) {
            $names = $this->reader->getResolver()->resolveReference($namesRef);
            if ($names instanceof PdfDictionary) {
                return $names->has('EmbeddedFiles');
            }
        }

        return false;
    }

    public function getRegisteredObjects(): iterable
    {
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof PdfObject) {
                yield $obj;
            }
        }
    }

    public function hasThreeDAnnotations(): bool
    {
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof ThreeDAnnotation) {
                return true;
            }
        }
        return false;
    }

    public function getThreeDStreams(): iterable
    {
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof ThreeDStream) {
                yield $obj;
            }
        }
    }

    public function hasRasterOnlyContent(): bool
    {
        // Heuristic: if any fonts exist in the document, content is not raster-only
        foreach ($this->getFonts() as $_) {
            return false;
        }
        return true;
    }

    public function getImageXObjects(): iterable
    {
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof ImageXObject) {
                yield $obj;
            }
        }
    }

    public function hasInteractiveForms(): bool
    {
        $catalogDict = $this->reader->getCatalog();
        return $catalogDict->has('AcroForm');
    }

    public function hasMultimediaContent(): bool
    {
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof MovieAnnotation
                || $obj instanceof SoundAnnotation
                || $obj instanceof ScreenAnnotation
                || $obj instanceof RichMediaAnnotation
                || $obj instanceof MediaRendition
            ) {
                return true;
            }
        }
        return false;
    }

    public function getReferenceXObjects(): iterable
    {
        $resolver = $this->reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $objNum) {
            $obj = $this->reader->getTypedObject($objNum);
            if ($obj instanceof FormXObject && $obj->ref !== null) {
                yield $obj;
            }
        }
    }
}
