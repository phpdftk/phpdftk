<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

use ApprLabs\Pdf\Core\File\IncrementalWriter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDate;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Reader\PdfReader;

/**
 * Read and modify PDF document metadata (Info dictionary).
 *
 * Usage:
 *   $info = MetadataEditor::openString($bytes)->getAll();
 *
 *   MetadataEditor::open('doc.pdf')
 *       ->setTitle('My Document')
 *       ->setAuthor('Jane Doe')
 *       ->save('updated.pdf');
 */
final class MetadataEditor
{
    private string $originalBytes;

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    /** @var array<string, PdfString|PdfName|null> Pending field changes */
    private array $changes = [];

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
    }

    public static function open(string $path, string $password = ''): self
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read file: $path");
        }
        return new self(PdfReader::fromString($bytes, $password), $bytes);
    }

    public static function openString(string $pdfBytes, string $password = ''): self
    {
        return new self(PdfReader::fromString($pdfBytes, $password), $pdfBytes);
    }

    // -----------------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------------

    public function getTitle(): ?string
    {
        return $this->getStringField('Title');
    }

    public function getAuthor(): ?string
    {
        return $this->getStringField('Author');
    }

    public function getSubject(): ?string
    {
        return $this->getStringField('Subject');
    }

    public function getKeywords(): ?string
    {
        return $this->getStringField('Keywords');
    }

    public function getCreator(): ?string
    {
        return $this->getStringField('Creator');
    }

    public function getProducer(): ?string
    {
        return $this->getStringField('Producer');
    }

    public function getCreationDate(): ?\DateTimeImmutable
    {
        return $this->getDateField('CreationDate');
    }

    public function getModDate(): ?\DateTimeImmutable
    {
        return $this->getDateField('ModDate');
    }

    public function getTrapped(): ?string
    {
        $info = $this->reader->getInfo();
        if ($info === null) {
            return null;
        }
        $val = $info->get('Trapped');
        return $val instanceof PdfName ? $val->value : null;
    }

    public function getAll(): MetadataInfo
    {
        return new MetadataInfo(
            title: $this->getTitle(),
            author: $this->getAuthor(),
            subject: $this->getSubject(),
            keywords: $this->getKeywords(),
            creator: $this->getCreator(),
            producer: $this->getProducer(),
            creationDate: $this->getCreationDate(),
            modDate: $this->getModDate(),
            trapped: $this->getTrapped(),
        );
    }

    // -----------------------------------------------------------------------
    // Write (fluent)
    // -----------------------------------------------------------------------

    public function setTitle(string $value): self
    {
        $this->changes['Title'] = new PdfString($value);
        return $this;
    }

    public function setAuthor(string $value): self
    {
        $this->changes['Author'] = new PdfString($value);
        return $this;
    }

    public function setSubject(string $value): self
    {
        $this->changes['Subject'] = new PdfString($value);
        return $this;
    }

    public function setKeywords(string $value): self
    {
        $this->changes['Keywords'] = new PdfString($value);
        return $this;
    }

    public function setCreator(string $value): self
    {
        $this->changes['Creator'] = new PdfString($value);
        return $this;
    }

    public function setProducer(string $value): self
    {
        $this->changes['Producer'] = new PdfString($value);
        return $this;
    }

    public function setCreationDate(\DateTimeInterface $date): self
    {
        $this->changes['CreationDate'] = PdfDate::fromDateTime($date);
        return $this;
    }

    public function setModDate(\DateTimeInterface $date): self
    {
        $this->changes['ModDate'] = PdfDate::fromDateTime($date);
        return $this;
    }

    public function setTrapped(string $value): self
    {
        $this->changes['Trapped'] = new PdfName($value);
        return $this;
    }

    public function setCustom(string $key, string $value): self
    {
        $this->changes[$key] = new PdfString($value);
        return $this;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $this->toBytes());
    }

    public function toBytes(): string
    {
        if (empty($this->changes)) {
            return $this->originalBytes;
        }

        $trailer = $this->reader->getTrailer();
        $existingInfoRef = $trailer->get('Info');

        // Build merged dictionary: existing fields + changes
        $dict = new PdfDictionary();
        $existingInfo = $this->reader->getInfo();
        if ($existingInfo !== null) {
            foreach (array_keys($existingInfo->entries) as $key) {
                $dict->set($key, $existingInfo->entries[$key]);
            }
        }
        foreach ($this->changes as $key => $value) {
            $dict->set($key, $value);
        }

        // Create a PdfObject wrapper for the Info dictionary
        $info = new class ($dict) extends PdfObject {
            public function __construct(private readonly PdfDictionary $dict) {}
            public function toPdf(): string { return $this->dict->toPdf(); }
        };

        if ($existingInfoRef instanceof PdfReference) {
            // Modify existing Info object
            $info->objectNumber = $existingInfoRef->objectNumber;
            $info->generationNumber = 0;
            $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
            $writer->addModifiedObject($info);
        } else {
            // No existing Info — construct IncrementalWriter manually with new /Info ref
            $xrefOffset = $this->findStartxrefOffset();
            $sizeVal = $trailer->get('Size');
            $size = $sizeVal instanceof PdfNumber ? (int) $sizeVal->toPdf() : 0;
            $root = $trailer->get('Root');
            if (!$root instanceof PdfReference) {
                throw new \RuntimeException('Trailer missing /Root');
            }
            $id = $trailer->get('ID');
            $idArray = $id instanceof PdfArray ? $id : null;

            // Pre-assign object number
            $info->objectNumber = $size;
            $info->generationNumber = 0;
            $infoRef = new PdfReference($size);

            $writer = new IncrementalWriter(
                $this->originalBytes, $size, $xrefOffset, $root,
                $infoRef, $idArray,
            );
            $writer->addModifiedObject($info);
        }

        $result = $writer->generate();
        $this->lastVersionWarnings = $writer->getVersionWarnings();
        return $result;
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->lastVersionWarnings;
    }

    public function getReader(): PdfReader
    {
        return $this->reader;
    }

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    private function getStringField(string $key): ?string
    {
        $info = $this->reader->getInfo();
        if ($info === null) {
            return null;
        }
        $val = $info->get($key);
        return $val instanceof PdfString ? $val->value : null;
    }

    private function getDateField(string $key): ?\DateTimeImmutable
    {
        $raw = $this->getStringField($key);
        if ($raw === null) {
            return null;
        }
        return PdfDate::parse($raw);
    }

    private function findStartxrefOffset(): int
    {
        $tailLen = min(1024, strlen($this->originalBytes));
        $tail = substr($this->originalBytes, -$tailLen);
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
