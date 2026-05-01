<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support\Arlington;

final class ArlingtonLoader
{
    private const DEFAULT_TSV_PATH = __DIR__ . '/../../../vendor-data/arlington-pdf-model/tsv/latest';

    /** @var array<string, DictionarySpec>|null */
    private static ?array $cache = null;

    /** @return array<string, DictionarySpec> keyed by TSV filename stem (e.g., 'Catalog', 'PageObject') */
    public static function load(?string $tsvDir = null): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $dir = $tsvDir ?? self::DEFAULT_TSV_PATH;
        $specs = [];

        foreach (glob($dir . '/*.tsv') as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $spec = self::parseTsv($name, $file);
            if ($spec !== null) {
                $specs[$name] = $spec;
            }
        }

        return self::$cache = $specs;
    }

    public static function isAvailable(): bool
    {
        return is_dir(self::DEFAULT_TSV_PATH);
    }

    private static function parseTsv(string $name, string $file): ?DictionarySpec
    {
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return null;
        }

        try {
            // Skip header row
            fgetcsv($handle, 0, "\t", escape: '');

            $fields = [];
            while (($row = fgetcsv($handle, 0, "\t", escape: '')) !== false) {
                if (count($row) < 12) {
                    continue;
                }

                $key = $row[0];
                if ($key === '' || $key === '*') {
                    continue;
                }

                $types = $row[1] !== '' ? explode(';', $row[1]) : [];

                $possibleValues = [];
                if ($row[8] !== '') {
                    // Format: [value1,value2,...] — strip brackets and split
                    $pv = trim($row[8], '[]');
                    if ($pv !== '') {
                        $possibleValues = explode(',', $pv);
                    }
                }

                $fields[$key] = new FieldSpec(
                    key: $key,
                    types: $types,
                    sinceVersion: $row[2],
                    deprecatedIn: $row[3],
                    required: $row[4],
                    indirectReference: $row[5],
                    inheritable: $row[6] === 'TRUE',
                    defaultValue: $row[7] !== '' ? $row[7] : null,
                    possibleValues: $possibleValues,
                    specialCase: $row[9],
                    link: $row[10],
                    note: $row[11] ?? '',
                );
            }

            return new DictionarySpec($name, $fields);
        } finally {
            fclose($handle);
        }
    }
}
