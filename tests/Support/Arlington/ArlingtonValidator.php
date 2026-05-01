<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support\Arlington;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;

final class ArlingtonValidator
{
    /**
     * Maps PDF /Type values to Arlington TSV file names.
     * Keys without a mapping here use the /Type value directly.
     */
    private const TYPE_TO_SPEC = [
        'Page' => 'PageObject',
        'Pages' => 'PageTreeNodeRoot',
        'Font' => 'FontType1',
        'Encoding' => 'FontEncoding',
        'ExtGState' => 'GraphicsStateParameter',
        'XObject' => 'XObjectImage',
        'Annot' => 'AnnotText',
        'Action' => 'ActionGoTo',
        'Outlines' => 'Outline',
        'OCG' => 'OptContentGroup',
        'OCMD' => 'OptContentMembership',
        'XRef' => 'XRefStream',
        'ObjStm' => 'ObjectStream',
        'OutputIntent' => 'OutputIntent',
    ];

    /** @var array<string, DictionarySpec> */
    private array $specs;

    /** @param array<string, DictionarySpec> $specs */
    public function __construct(array $specs)
    {
        $this->specs = $specs;
    }

    public function validate(
        PdfDictionary $dict,
        string $specName,
        ?PdfVersion $version = null,
    ): ValidationResult {
        $spec = $this->specs[$specName] ?? null;
        if ($spec === null) {
            return new ValidationResult(warnings: ["No Arlington spec found for '{$specName}'"]);
        }

        $errors = [];
        $warnings = [];

        // 1. Check unconditionally required keys
        foreach ($spec->getRequiredFields() as $field) {
            if (!$dict->has($field->key)) {
                $errors[] = "[{$specName}] Required key /{$field->key} is missing";
            }
        }

        // 2. Check for unknown keys
        foreach (array_keys($dict->entries) as $key) {
            if (!$spec->hasField($key)) {
                $warnings[] = "[{$specName}] Unknown key /{$key}";
            }
        }

        // 3. Version constraints
        if ($version !== null) {
            foreach ($spec->fields as $field) {
                if ($field->sinceVersion === '' || !$dict->has($field->key)) {
                    continue;
                }
                $fieldVersion = PdfVersion::tryFrom($field->sinceVersion);
                if ($fieldVersion !== null && !$version->isAtLeast($fieldVersion)) {
                    $warnings[] = "[{$specName}] Key /{$field->key} requires PDF {$field->sinceVersion} but document is {$version->value}";
                }
            }

            // Check deprecated keys
            foreach ($spec->fields as $field) {
                if ($field->deprecatedIn === '' || !$dict->has($field->key)) {
                    continue;
                }
                $deprecatedVersion = PdfVersion::tryFrom($field->deprecatedIn);
                if ($deprecatedVersion !== null && $version->isAtLeast($deprecatedVersion)) {
                    $warnings[] = "[{$specName}] Key /{$field->key} is deprecated since PDF {$field->deprecatedIn}";
                }
            }
        }

        return new ValidationResult($errors, $warnings);
    }

    /**
     * Resolve the Arlington spec name from a PDF dictionary's /Type value.
     */
    public function resolveSpecName(PdfDictionary $dict): ?string
    {
        $typeValue = $dict->get('Type');
        if ($typeValue === null) {
            return null;
        }

        $typeName = match (true) {
            $typeValue instanceof PdfName => ltrim($typeValue->toPdf(), '/'),
            is_string($typeValue) => ltrim($typeValue, '/'),
            default => null,
        };

        if ($typeName === null) {
            return null;
        }

        return self::TYPE_TO_SPEC[$typeName] ?? $typeName;
    }
}
