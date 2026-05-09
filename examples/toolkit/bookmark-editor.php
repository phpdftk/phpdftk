<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate a 6-page input PDF.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    foreach (['Introduction', 'Methods', 'Materials', 'Procedure', 'Results', 'Discussion'] as $i => $title) {
        if ($i > 0) {
            $seed->newPage();
        }
        $seed->addHeading($title, 1);
        $seed->addText("Content for {$title}.");
    }
    $seed->save('paper.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\Bookmark\BookmarkEntry;
use Phpdftk\Pdf\Toolkit\BookmarkEditor;

BookmarkEditor::open('paper.pdf')
    ->setBookmarks(
        new BookmarkEntry('Introduction', 1),
        new BookmarkEntry('Methods', 2, [
            new BookmarkEntry('Materials', 3),
            new BookmarkEntry('Procedure', 4),
        ]),
        new BookmarkEntry('Results', 5),
        new BookmarkEntry('Discussion', 6),
    )
    ->save('bookmarked.pdf');
// endregion

rename(__DIR__ . '/paper.pdf', example_output_path('toolkit/bookmark-editor/input.pdf'));
rename(__DIR__ . '/bookmarked.pdf', example_output_path('toolkit/bookmark-editor/output.pdf'));
