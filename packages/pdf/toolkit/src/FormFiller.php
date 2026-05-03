<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Form\FieldInfo;
use Phpdftk\Pdf\Toolkit\Form\FieldType;

/**
 * Fill interactive PDF form fields (AcroForm).
 *
 * Uses incremental updates to preserve the original PDF structure,
 * existing signatures, and other content.
 *
 * Usage:
 *   FormFiller::open('form.pdf')
 *       ->fill('name', 'Jane Doe')
 *       ->check('subscribe')
 *       ->select('country', 'Canada')
 *       ->save('filled.pdf');
 *
 * @api
 */
final class FormFiller
{
    private string $originalBytes;

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    /**
     * Resolved field data: fully-qualified name => [objNum, dict].
     * @var array<string, array{int, PdfDictionary}>
     */
    private array $fields = [];

    /** @var array<string, mixed> Pending modifications: field name => new value */
    private array $modifications = [];

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
        $this->discoverFields();
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

    /**
     * Get all field names in the form.
     *
     * @return list<string>
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Get detailed information about a specific field.
     */
    public function getFieldInfo(string $name): ?FieldInfo
    {
        if (!isset($this->fields[$name])) {
            return null;
        }

        [, $dict] = $this->fields[$name];

        $type = $this->resolveFieldType($dict);
        if ($type === null) {
            return null;
        }

        return new FieldInfo(
            name: $name,
            type: $type,
            value: $this->extractValue($dict),
            flags: $this->extractFlags($dict),
            rect: $this->extractRect($dict),
            maxLen: $this->extractMaxLen($dict),
            options: $this->extractOptions($dict),
        );
    }

    /**
     * Get all field values as an associative array.
     *
     * @return array<string, string|null>
     */
    public function getFieldValues(): array
    {
        $values = [];
        foreach ($this->fields as $name => [, $dict]) {
            $values[$name] = $this->extractValue($dict);
        }
        return $values;
    }

    /**
     * Check whether a field exists in the form.
     */
    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    // -----------------------------------------------------------------------
    // Write (fluent)
    // -----------------------------------------------------------------------

    /**
     * Set the value of a text or choice field.
     */
    public function fill(string $fieldName, string $value): self
    {
        if (!isset($this->fields[$fieldName])) {
            throw new \InvalidArgumentException("Field not found: $fieldName");
        }
        $this->modifications[$fieldName] = ['type' => 'text', 'value' => $value];
        return $this;
    }

    /**
     * Fill multiple fields at once.
     *
     * @param array<string, string> $values Field name => value
     */
    public function fillMany(array $values): self
    {
        foreach ($values as $name => $value) {
            $this->fill($name, $value);
        }
        return $this;
    }

    /**
     * Check or uncheck a checkbox field.
     */
    public function check(string $fieldName, bool $checked = true): self
    {
        if (!isset($this->fields[$fieldName])) {
            throw new \InvalidArgumentException("Field not found: $fieldName");
        }
        $this->modifications[$fieldName] = ['type' => 'check', 'checked' => $checked];
        return $this;
    }

    /**
     * Select an option in a choice field.
     */
    public function select(string $fieldName, string $option): self
    {
        if (!isset($this->fields[$fieldName])) {
            throw new \InvalidArgumentException("Field not found: $fieldName");
        }
        $this->modifications[$fieldName] = ['type' => 'select', 'value' => $option];
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
        if (empty($this->modifications)) {
            return $this->originalBytes;
        }

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);

        foreach ($this->modifications as $fieldName => $mod) {
            [$objNum, $dict] = $this->fields[$fieldName];

            // Clone the dictionary so we don't mutate the cached version
            $modifiedDict = new PdfDictionary($dict->entries);

            match ($mod['type']) {
                'text', 'select' => $modifiedDict->set('V', new PdfString($mod['value'])),
                'check' => $modifiedDict->set(
                    'V',
                    $mod['checked'] ? new PdfName('Yes') : new PdfName('Off')
                ),
            };

            // Create a PdfObject wrapper with the original object number
            $wrapper = new class ($modifiedDict) extends PdfObject {
                public function __construct(private readonly PdfDictionary $dict) {}
                public function toPdf(): string { return $this->dict->toPdf(); }
            };
            $wrapper->objectNumber = $objNum;
            $wrapper->generationNumber = 0;

            $writer->addModifiedObject($wrapper);
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
    // Internal: field discovery
    // -----------------------------------------------------------------------

    /**
     * Walk the AcroForm /Fields array and build the field index.
     */
    private function discoverFields(): void
    {
        $trailer = $this->reader->getTrailer();
        $rootRef = $trailer->get('Root');
        if (!$rootRef instanceof PdfReference) {
            return;
        }

        $catalog = $this->reader->resolveReference($rootRef);
        if (!$catalog instanceof PdfDictionary) {
            return;
        }

        $acroFormVal = $catalog->get('AcroForm');
        $acroForm = $this->resolve($acroFormVal);
        if (!$acroForm instanceof PdfDictionary) {
            return;
        }

        $fieldsVal = $acroForm->get('Fields');
        $fieldsArray = $this->resolve($fieldsVal);
        if (!$fieldsArray instanceof PdfArray) {
            return;
        }

        foreach ($fieldsArray->items as $fieldRef) {
            $this->walkField($fieldRef, '');
        }
    }

    /**
     * Recursively walk a field and its /Kids, building fully-qualified names.
     */
    private function walkField(mixed $fieldRefOrDict, string $parentName): void
    {
        $fieldDict = $this->resolve($fieldRefOrDict);
        if (!$fieldDict instanceof PdfDictionary) {
            return;
        }

        // Determine object number for this field
        $objNum = 0;
        if ($fieldRefOrDict instanceof PdfReference) {
            $objNum = $fieldRefOrDict->objectNumber;
        }

        // Build fully-qualified name
        $partialName = '';
        $tVal = $fieldDict->get('T');
        if ($tVal instanceof PdfString) {
            $partialName = $tVal->value;
        }

        $fullName = $parentName !== '' && $partialName !== ''
            ? $parentName . '.' . $partialName
            : ($partialName !== '' ? $partialName : $parentName);

        // Check for /Kids
        $kidsVal = $fieldDict->get('Kids');
        $kids = $this->resolve($kidsVal);

        if ($kids instanceof PdfArray && !empty($kids->items)) {
            // Check if kids are widget annotations (have /Subtype /Widget but no /T)
            // or child fields (have /T)
            $hasFieldKids = false;
            foreach ($kids->items as $kidRef) {
                $kidDict = $this->resolve($kidRef);
                if ($kidDict instanceof PdfDictionary && $kidDict->has('T')) {
                    $hasFieldKids = true;
                    break;
                }
            }

            if ($hasFieldKids) {
                // Recurse into child fields
                foreach ($kids->items as $kidRef) {
                    $this->walkField($kidRef, $fullName);
                }
                return;
            }
        }

        // This is a terminal field (leaf node) — index it
        if ($fullName !== '' && $objNum > 0) {
            $this->fields[$fullName] = [$objNum, $fieldDict];
        }
    }

    /**
     * Resolve a value that might be a PdfReference to the actual object.
     */
    private function resolve(mixed $val): mixed
    {
        if ($val instanceof PdfReference) {
            return $this->reader->resolveReference($val);
        }
        return $val;
    }

    // -----------------------------------------------------------------------
    // Internal: field property extraction
    // -----------------------------------------------------------------------

    private function resolveFieldType(PdfDictionary $dict): ?FieldType
    {
        $ft = $dict->get('FT');

        // If not on this dict, check /Parent (inherited field type)
        if ($ft === null) {
            $parentRef = $dict->get('Parent');
            if ($parentRef instanceof PdfReference) {
                $parent = $this->resolve($parentRef);
                if ($parent instanceof PdfDictionary) {
                    $ft = $parent->get('FT');
                }
            }
        }

        if ($ft instanceof PdfName) {
            return FieldType::tryFrom($ft->value);
        }

        return null;
    }

    private function extractValue(PdfDictionary $dict): ?string
    {
        $v = $dict->get('V');
        if ($v instanceof PdfString) {
            return $v->value;
        }
        if ($v instanceof PdfName) {
            return $v->value;
        }
        return null;
    }

    private function extractFlags(PdfDictionary $dict): int
    {
        $ff = $dict->get('Ff');
        if ($ff instanceof PdfNumber) {
            return (int) $ff->toPdf();
        }
        return 0;
    }

    private function extractRect(PdfDictionary $dict): ?array
    {
        $rect = $dict->get('Rect');
        if (!$rect instanceof PdfArray) {
            return null;
        }

        $floats = [];
        foreach ($rect->items as $item) {
            if ($item instanceof PdfNumber) {
                $floats[] = (float) $item->toPdf();
            }
        }

        return count($floats) === 4 ? $floats : null;
    }

    private function extractMaxLen(PdfDictionary $dict): ?int
    {
        $ml = $dict->get('MaxLen');
        if ($ml instanceof PdfNumber) {
            return (int) $ml->toPdf();
        }
        return null;
    }

    /**
     * @return string[]|null
     */
    private function extractOptions(PdfDictionary $dict): ?array
    {
        $opt = $dict->get('Opt');
        if (!$opt instanceof PdfArray) {
            return null;
        }

        $options = [];
        foreach ($opt->items as $item) {
            if ($item instanceof PdfString) {
                $options[] = $item->value;
            } elseif ($item instanceof PdfArray && count($item->items) >= 2) {
                // [export_value, display_value] pairs — use display value
                $display = $item->items[1] ?? $item->items[0];
                $options[] = $display instanceof PdfString ? $display->value : '';
            }
        }

        return $options;
    }
}
