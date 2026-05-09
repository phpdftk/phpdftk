<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate an input PDF with placeholder metadata.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    $seed->addHeading('Q4 Report', 1);
    $seed->addText('Internal copy.');
    $seed->save('untagged.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\MetadataEditor;

MetadataEditor::open('untagged.pdf')
    ->setTitle('Quarterly Financial Report — Q4 2026')
    ->setAuthor('Finance Department')
    ->setSubject('Earnings summary')
    ->setKeywords('finance, q4, earnings, internal')
    ->setCreationDate(new DateTimeImmutable('2026-12-31'))
    ->save('tagged.pdf');

// Read it back
$info = MetadataEditor::open('tagged.pdf')->getAll();
echo "Title:  {$info->title}\n";
echo "Author: {$info->author}\n";
// endregion

rename(__DIR__ . '/untagged.pdf', example_output_path('toolkit/metadata-editor/input.pdf'));
rename(__DIR__ . '/tagged.pdf', example_output_path('toolkit/metadata-editor/output.pdf'));
