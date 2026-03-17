<?php

declare(strict_types=1);

namespace Phpdftk\Content;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\Serializable;

/**
 * Resource dictionary used by Page and FormXObject.
 * Maps resource names to their referenced objects.
 */
class Resources implements Serializable
{
    /** @var array<string, PdfReference> */
    public array $extGState = [];   // /ExtGState
    /** @var array<string, PdfReference> */
    public array $colorSpace = [];  // /ColorSpace
    /** @var array<string, PdfReference> */
    public array $pattern = [];     // /Pattern
    /** @var array<string, PdfReference> */
    public array $shading = [];     // /Shading
    /** @var array<string, PdfReference> */
    public array $xObject = [];     // /XObject
    /** @var array<string, PdfReference> */
    public array $font = [];        // /Font - key => resource name, value => PdfReference
    /** @var array<int, string> */
    public array $procSet = [];     // /ProcSet
    /** @var array<string, PdfReference> */
    public array $properties = [];  // /Properties

    public function addFont(string $name, PdfReference $ref): void
    {
        $this->font[$name] = $ref;
    }

    public function addXObject(string $name, PdfReference $ref): void
    {
        $this->xObject[$name] = $ref;
    }

    public function addExtGState(string $name, PdfReference $ref): void
    {
        $this->extGState[$name] = $ref;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        // /ProcSet
        if (!empty($this->procSet)) {
            $procItems = array_map(fn($p) => new PdfName($p), $this->procSet);
            $dict->set('ProcSet', new PdfArray($procItems));
        } else {
            // Default ProcSet for most documents
            $dict->set('ProcSet', new PdfArray([
                new PdfName('PDF'),
                new PdfName('Text'),
                new PdfName('ImageB'),
                new PdfName('ImageC'),
                new PdfName('ImageI'),
            ]));
        }

        // /Font
        if (!empty($this->font)) {
            $fontDict = new PdfDictionary();
            foreach ($this->font as $name => $ref) {
                $fontDict->set($name, $ref);
            }
            $dict->set('Font', $fontDict);
        }

        // /XObject
        if (!empty($this->xObject)) {
            $xoDict = new PdfDictionary();
            foreach ($this->xObject as $name => $ref) {
                $xoDict->set($name, $ref);
            }
            $dict->set('XObject', $xoDict);
        }

        // /ExtGState
        if (!empty($this->extGState)) {
            $gsDict = new PdfDictionary();
            foreach ($this->extGState as $name => $ref) {
                $gsDict->set($name, $ref);
            }
            $dict->set('ExtGState', $gsDict);
        }

        // /ColorSpace
        if (!empty($this->colorSpace)) {
            $csDict = new PdfDictionary();
            foreach ($this->colorSpace as $name => $ref) {
                $csDict->set($name, $ref);
            }
            $dict->set('ColorSpace', $csDict);
        }

        // /Pattern
        if (!empty($this->pattern)) {
            $patDict = new PdfDictionary();
            foreach ($this->pattern as $name => $ref) {
                $patDict->set($name, $ref);
            }
            $dict->set('Pattern', $patDict);
        }

        // /Shading
        if (!empty($this->shading)) {
            $shadDict = new PdfDictionary();
            foreach ($this->shading as $name => $ref) {
                $shadDict->set($name, $ref);
            }
            $dict->set('Shading', $shadDict);
        }

        // /Properties
        if (!empty($this->properties)) {
            $propDict = new PdfDictionary();
            foreach ($this->properties as $name => $ref) {
                $propDict->set($name, $ref);
            }
            $dict->set('Properties', $propDict);
        }

        return $dict->toPdf();
    }
}
