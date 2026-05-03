<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Support\Arlington;

use Phpdftk\Pdf\Reader\PdfReader;

trait ArlingtonValidationTrait
{
    private static ?ArlingtonValidator $arlingtonValidator = null;

    protected function assertArlingtonValid(string $pdfPath): void
    {
        if (!ArlingtonLoader::isAvailable()) {
            $this->markTestSkipped('Arlington TSV data not available (run: git submodule update --init)');
        }

        $validator = $this->getArlingtonValidator();
        $reader = PdfReader::fromFile($pdfPath);
        $version = $reader->getPdfVersion();

        // Validate Catalog
        $catalog = $reader->getCatalog();
        $catalogResult = $validator->validate($catalog, 'Catalog', $version);
        self::assertFalse(
            $catalogResult->hasErrors(),
            "Arlington Catalog validation errors:\n" . implode("\n", $catalogResult->errors),
        );

        // Validate each Page
        $pageCount = $reader->getPageCount();
        for ($i = 0; $i < $pageCount; $i++) {
            $page = $reader->getPage($i);
            $pageResult = $validator->validate($page, 'PageObject', $version);
            self::assertFalse(
                $pageResult->hasErrors(),
                "Arlington Page {$i} validation errors:\n" . implode("\n", $pageResult->errors),
            );
        }
    }

    protected function assertArlingtonValidBytes(string $pdfBytes): void
    {
        if (!ArlingtonLoader::isAvailable()) {
            $this->markTestSkipped('Arlington TSV data not available (run: git submodule update --init)');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'arlington_');
        file_put_contents($tmp, $pdfBytes);

        try {
            $this->assertArlingtonValid($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function getArlingtonValidator(): ArlingtonValidator
    {
        if (self::$arlingtonValidator === null) {
            self::$arlingtonValidator = new ArlingtonValidator(ArlingtonLoader::load());
        }
        return self::$arlingtonValidator;
    }
}
