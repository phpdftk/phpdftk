<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\File;

use Phpdftk\Filters\FlateFilter;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\CrossReferenceStream;
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Incremental update writer — ISO 32000-2 §7.5.6.
 *
 * Appends modified/new objects to an existing PDF without rewriting
 * the original file content. The result is a valid PDF where the
 * original bytes are preserved intact, followed by:
 *
 *   1. New/modified indirect objects
 *   2. A new cross-reference section (covering only the changed objects)
 *   3. A new trailer with `/Prev` pointing to the original xref offset
 *   4. `startxref` + `%%EOF`
 *
 * This preserves existing digital signatures and is significantly
 * more efficient than a full rewrite for small modifications (e.g.,
 * form filling, annotation additions).
 */
final class IncrementalWriter
{
    /** @var array<int, PdfObject> Objects to append (objNum => object) */
    private array $objects = [];

    /** @var list<int> Object numbers to mark as free (deleted) */
    private array $deletedObjects = [];

    private int $nextObjectNumber;
    private bool $compressStreams;
    private bool $useXRefStream;
    private ?PdfEncryptor $encryptor = null;
    private PdfVersion $version;
    private bool $versionBumped = false;
    private bool $strictVersionMode = false;
    /** @var list<string> */
    private array $versionWarnings = [];
    /** @var (\Closure(string): void)|null */
    private ?\Closure $deprecationHandler = null;
    private bool $strictDeprecation = false;

    /**
     * @param string $originalPdf The complete bytes of the original PDF
     * @param int    $originalSize Total object count from the original trailer /Size
     * @param int    $originalXrefOffset The startxref byte offset from the original PDF
     * @param PdfReference $rootRef The /Root reference from the original trailer
     * @param PdfReference|null $infoRef The /Info reference (if present)
     * @param PdfArray|null $idArray The /ID array from the original trailer
     * @param PdfReference|null $encryptRef The /Encrypt reference (if present)
     */
    public function __construct(
        private readonly string $originalPdf,
        private readonly int $originalSize,
        private readonly int $originalXrefOffset,
        private readonly PdfReference $rootRef,
        private readonly ?PdfReference $infoRef = null,
        private readonly ?PdfArray $idArray = null,
        private readonly ?PdfReference $encryptRef = null,
        bool $compressStreams = true,
        bool $useXRefStream = false,
        PdfVersion $version = PdfVersion::V1_7,
    ) {
        $this->nextObjectNumber = $originalSize;
        $this->compressStreams = $compressStreams;
        $this->useXRefStream = $useXRefStream;
        $this->version = $version;
    }

    /**
     * Configure encryption for new/modified objects in the incremental update.
     *
     * The encryptor must use the same key as the original PDF's encryption.
     * New and modified objects will be encrypted before serialization.
     */
    public function setEncryption(PdfEncryptor $encryptor): void
    {
        $this->encryptor = $encryptor;
    }

    /**
     * Create an IncrementalWriter from a PdfReader instance.
     *
     * Extracts the necessary metadata (size, xref offset, root, info,
     * ID, encrypt) from the reader's trailer.
     */
    public static function fromReader(
        \Phpdftk\Pdf\Reader\PdfReader $reader,
        string $originalPdf,
        bool $compressStreams = true,
        bool $useXRefStream = false,
    ): self {
        $trailer = $reader->getTrailer();

        // Extract original startxref offset
        $xrefOffset = self::findStartxrefOffset($originalPdf);

        // Extract /Size
        $sizeVal = $trailer->get('Size');
        $size = $sizeVal instanceof \Phpdftk\Pdf\Core\PdfNumber
            ? (int) $sizeVal->toPdf()
            : 0;

        // Extract /Root
        $root = $trailer->get('Root');
        if (!$root instanceof PdfReference) {
            throw new \RuntimeException('Original PDF trailer missing /Root reference');
        }

        // Extract optional /Info
        $info = $trailer->get('Info');
        $infoRef = $info instanceof PdfReference ? $info : null;

        // Extract optional /ID
        $id = $trailer->get('ID');
        $idArray = $id instanceof PdfArray ? $id : null;

        // Extract optional /Encrypt
        $encrypt = $trailer->get('Encrypt');
        $encryptRef = $encrypt instanceof PdfReference ? $encrypt : null;

        return new self(
            $originalPdf,
            $size,
            $xrefOffset,
            $root,
            $infoRef,
            $idArray,
            $encryptRef,
            $compressStreams,
            $useXRefStream,
            $reader->getPdfVersion(),
        );
    }

    public function getPdfVersion(): PdfVersion
    {
        return $this->version;
    }

    /**
     * Whether the version was auto-bumped during object registration.
     *
     * When true, callers that have access to the Catalog should update
     * its /Version entry via an incremental update, since the original
     * PDF header bytes are immutable.
     */
    public function wasVersionBumped(): bool
    {
        return $this->versionBumped;
    }

    public function setStrictVersionMode(bool $strict = true): void
    {
        $this->strictVersionMode = $strict;
    }

    public function setDeprecationHandler(\Closure $handler): void
    {
        $this->deprecationHandler = $handler;
    }

    public function setStrictDeprecation(bool $strict = true): void
    {
        $this->strictDeprecation = $strict;
    }

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->versionWarnings;
    }

    /**
     * Add a modified object (preserving its original object number).
     *
     * The object must already have its `objectNumber` set to the
     * original value from the PDF being updated.
     */
    public function addModifiedObject(PdfObject $object): void
    {
        if ($object->objectNumber <= 0) {
            throw new \InvalidArgumentException(
                'Modified object must have its original objectNumber set',
            );
        }
        $this->checkVersionRequirements($object);
        $this->objects[$object->objectNumber] = $object;
    }

    /**
     * Mark an object as deleted (free) in the incremental update.
     *
     * The object will appear as a free entry in the new xref section.
     */
    public function deleteObject(int $objNum): void
    {
        if ($objNum <= 0) {
            throw new \InvalidArgumentException('Cannot delete object 0');
        }
        $this->deletedObjects[] = $objNum;
    }

    /**
     * Add a new object (assigns a new sequential object number).
     *
     * @return PdfReference Reference to the newly assigned object
     */
    public function addNewObject(PdfObject $object): PdfReference
    {
        $this->checkVersionRequirements($object);
        $objNum = $this->nextObjectNumber++;
        $object->objectNumber = $objNum;
        $object->generationNumber = 0;
        $this->objects[$objNum] = $object;
        return new PdfReference($objNum);
    }

    /**
     * Generate the incremental update and return the complete PDF
     * (original bytes + appended update).
     */
    public function generate(): string
    {
        if (empty($this->objects) && empty($this->deletedObjects)) {
            return $this->originalPdf;
        }

        $chunks = [];

        // Start with the original PDF bytes
        $chunks[] = $this->originalPdf;

        // Ensure we start on a new line after %%EOF
        if (!str_ends_with($this->originalPdf, "\n")) {
            $chunks[] = "\n";
        }

        $offset = strlen(implode('', $chunks));

        // Optionally compress streams
        if ($this->compressStreams) {
            $flate = new FlateFilter();
            foreach ($this->objects as $object) {
                if ($object instanceof ContentStream) {
                    // Materialize operators
                    $ops = $object->getOperators();
                    if (!empty($ops)) {
                        $object->data = implode("\n", $ops);
                        $object->clearOperators();
                    }
                }
                if ($object instanceof PdfStream
                    && !$object->dictionary->has('Filter')
                    && $object->data !== ''
                ) {
                    $object->setFilter($flate, 'FlateDecode');
                }
            }
        }

        // Write modified/new objects and build xref entries.
        // When encryption is active, clone objects and encrypt the clones
        // so originals stay untouched (same pattern as PdfFileWriter).
        $objectsToWrite = $this->objects;
        if ($this->encryptor !== null) {
            $flate = $this->compressStreams ? new FlateFilter() : null;
            $clones = [];
            foreach ($objectsToWrite as $objNum => $object) {
                $clone = clone $object;
                if ($clone instanceof PdfStream) {
                    $clone->dictionary = clone $clone->dictionary;
                }
                // Materialize ContentStream operators into data
                if ($clone instanceof ContentStream) {
                    $ops = $clone->getOperators();
                    if (!empty($ops)) {
                        $clone->data = implode("\n", $ops);
                        $clone->clearOperators();
                    }
                }
                // Compress before encrypting (spec order: compress → encrypt)
                if (
                    $flate !== null
                    && $clone instanceof PdfStream
                    && !$clone->dictionary->has('Filter')
                    && $clone->data !== ''
                ) {
                    $clone->data = $flate->encode($clone->data);
                    $clone->dictionary->set('Filter', new PdfName('FlateDecode'));
                }
                $this->encryptor->encryptObject($clone);
                $clones[$objNum] = $clone;
            }
            $objectsToWrite = $clones;
        }

        $xrefEntries = [];
        foreach ($objectsToWrite as $objNum => $object) {
            $xrefEntries[$objNum] = $offset;
            $chunk = $object->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        // Compute new /Size
        $newSize = max($this->nextObjectNumber, $this->originalSize);
        foreach (array_keys($xrefEntries) as $objNum) {
            $newSize = max($newSize, $objNum + 1);
        }

        // Updated /ID (first element preserved, second changes)
        $newIdArray = null;
        if ($this->idArray !== null) {
            $firstId = $this->idArray->items[0] ?? new PdfString('', hex: true);
            $secondId = new PdfString(md5(microtime() . $offset, true), hex: true);
            $newIdArray = new PdfArray([$firstId, $secondId]);
        }

        if ($this->useXRefStream) {
            // PDF 1.5+ cross-reference stream for the incremental update
            $xrefStream = new CrossReferenceStream();

            // Add entries for modified/new objects
            foreach ($xrefEntries as $objNum => $byteOffset) {
                $xrefStream->addInUseEntry($byteOffset);
            }
            // Add free entries for deleted objects
            foreach ($this->deletedObjects as $delObjNum) {
                $xrefStream->addFreeEntry(0, 0);
            }

            // The xref stream itself is a new object
            $xrefStreamObjNum = $newSize;
            $xrefStream->objectNumber = $xrefStreamObjNum;
            $newSize = $xrefStreamObjNum + 1;
            $xrefStream->size = $newSize;
            $xrefStream->prev = $this->originalXrefOffset;
            $xrefStream->root = $this->rootRef;
            if ($this->infoRef !== null) {
                $xrefStream->info = $this->infoRef;
            }
            if ($this->encryptRef !== null) {
                $xrefStream->encrypt = $this->encryptRef;
            }
            if ($newIdArray !== null) {
                $xrefStream->id = $newIdArray;
            }

            // Build /Index array with subsection ranges
            $allObjNums = array_keys($xrefEntries);
            foreach ($this->deletedObjects as $d) {
                $allObjNums[] = $d;
            }
            $allObjNums[] = $xrefStreamObjNum;
            sort($allObjNums);

            $indexPairs = [];
            $currentStart = -1;
            $currentCount = 0;
            $lastNum = -2;
            foreach ($allObjNums as $num) {
                if ($num !== $lastNum + 1) {
                    if ($currentCount > 0) {
                        $indexPairs[] = new PdfNumber($currentStart);
                        $indexPairs[] = new PdfNumber($currentCount);
                    }
                    $currentStart = $num;
                    $currentCount = 1;
                } else {
                    $currentCount++;
                }
                $lastNum = $num;
            }
            $indexPairs[] = new PdfNumber($currentStart);
            $indexPairs[] = new PdfNumber($currentCount);
            $xrefStream->index = new PdfArray($indexPairs);

            // Record the xref stream's own offset and add its entry
            $xrefOffset = $offset;
            $xrefStream->addInUseEntry($xrefOffset);

            if ($this->compressStreams) {
                $xrefStream->setFilter(new FlateFilter(), 'FlateDecode');
            }

            $chunks[] = $xrefStream->toIndirectObject() . "\n";
            $chunks[] = "startxref\n" . $xrefOffset . "\n";
            $chunks[] = '%%EOF';
        } else {
            // Classic cross-reference table
            $xrefOffset = $offset;
            $xrefChunk = $this->buildIncrementalXref($xrefEntries);
            $chunks[] = $xrefChunk;

            $trailer = new TrailerDictionary($this->rootRef);
            $trailer->size = $newSize;
            $trailer->prev = $this->originalXrefOffset;

            if ($this->infoRef !== null) {
                $trailer->info = $this->infoRef;
            }
            if ($this->encryptRef !== null) {
                $trailer->encrypt = $this->encryptRef;
            }
            if ($newIdArray !== null) {
                $trailer->id = $newIdArray;
            }

            $chunks[] = "trailer\n" . $trailer->toPdf() . "\n";
            $chunks[] = "startxref\n" . $xrefOffset . "\n";
            $chunks[] = '%%EOF';
        }

        return implode('', $chunks);
    }

    /**
     * Write the incrementally updated PDF to a file.
     */
    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $this->generate());
    }

    /**
     * Check version requirements and deprecation status for an object.
     */
    private function checkVersionRequirements(PdfObject $object): void
    {
        $deprecation = VersionRequirementResolver::getDeprecation($object);
        if ($deprecation !== null) {
            $msg = sprintf(
                '%s is deprecated since PDF %s%s',
                $object::class,
                $deprecation->since,
                $deprecation->replacement ? "; use {$deprecation->replacement} instead" : '',
            );
            $this->versionWarnings[] = $msg;
            if ($this->deprecationHandler !== null) {
                ($this->deprecationHandler)($msg);
            }

            $this->enforceRemoval($object::class, $deprecation);
        }

        $required = VersionRequirementResolver::getEffectiveRequirement($object);
        if ($required->isGreaterThan($this->version)) {
            if ($this->strictVersionMode) {
                throw new VersionRequirementException($object::class, $required, $this->version);
            }
            $this->versionWarnings[] = sprintf(
                'Auto-bumped PDF version to %s for %s',
                $required->value,
                $object::class,
            );
            $this->version = $required;
            $this->versionBumped = true;
        }

        // Walk public Serializable properties for inline children
        $ref = new \ReflectionClass($object);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isInitialized($object)) {
                continue;
            }
            $value = $prop->getValue($object);
            if ($value instanceof Serializable && !$value instanceof PdfObject) {
                $childRequired = VersionRequirementResolver::getEffectiveRequirement($value);
                if ($childRequired->isGreaterThan($this->version)) {
                    if ($this->strictVersionMode) {
                        throw new VersionRequirementException($value::class, $childRequired, $this->version);
                    }
                    $this->versionWarnings[] = sprintf(
                        'Auto-bumped PDF version to %s for %s',
                        $childRequired->value,
                        $value::class,
                    );
                    $this->version = $childRequired;
                    $this->versionBumped = true;
                }
            }
        }
    }

    /**
     * Enforce removal: throw if the feature has a removedIn version and
     * the target version is at or above it (in strict deprecation mode).
     */
    private function enforceRemoval(string $class, \Phpdftk\Pdf\Core\DeprecatedPdfFeature $deprecation): void
    {
        if ($deprecation->removedInVersion === null) {
            return;
        }

        if ($this->version->isAtLeast($deprecation->removedInVersion) && $this->strictDeprecation) {
            throw new DeprecatedFeatureException($class, $deprecation, $this->version);
        }
    }

    /**
     * Build a cross-reference table covering only the updated objects.
     *
     * Uses subsection format: each contiguous range of object numbers
     * gets its own subsection header.
     *
     * @param array<int, int> $entries objNum => byte offset
     */
    private function buildIncrementalXref(array $entries): string
    {
        // Merge deleted objects as free entries (type 'f')
        $allEntries = [];
        foreach ($entries as $objNum => $byteOffset) {
            $allEntries[$objNum] = sprintf("%010d 00000 n \r\n", $byteOffset);
        }
        foreach ($this->deletedObjects as $objNum) {
            // Free entry: next free obj = 0, generation = 0
            $allEntries[$objNum] = sprintf("%010d 00000 f \r\n", 0);
        }
        ksort($allEntries);

        $xref = "xref\n";

        // Group into contiguous subsections
        $subsections = [];
        $currentStart = -1;
        $currentEntries = [];
        $lastObjNum = -2;

        foreach ($allEntries as $objNum => $line) {
            if ($objNum !== $lastObjNum + 1) {
                if (!empty($currentEntries)) {
                    $subsections[] = [$currentStart, $currentEntries];
                }
                $currentStart = $objNum;
                $currentEntries = [];
            }
            $currentEntries[] = $line;
            $lastObjNum = $objNum;
        }
        if (!empty($currentEntries)) {
            $subsections[] = [$currentStart, $currentEntries];
        }

        foreach ($subsections as [$start, $entryLines]) {
            $xref .= sprintf("%d %d\n", $start, count($entryLines));
            foreach ($entryLines as $line) {
                $xref .= $line;
            }
        }

        return $xref;
    }

    /**
     * Find the startxref byte offset from the end of a PDF.
     */
    private static function findStartxrefOffset(string $pdf): int
    {
        $tailLen = min(1024, strlen($pdf));
        $tail = substr($pdf, -$tailLen);
        $pos = strrpos($tail, 'startxref');
        if ($pos === false) {
            throw new \RuntimeException('Cannot find startxref in PDF');
        }
        $after = substr($tail, $pos + strlen('startxref'));
        if (!preg_match('/\s+(\d+)/', $after, $m)) {
            throw new \RuntimeException('Cannot parse startxref offset');
        }
        return (int) $m[1];
    }
}
