---
title: Bookmark Editor
description: Read, add, replace, and remove PDF bookmarks (document outlines).
---

`BookmarkEditor` manages the PDF outline tree (bookmarks). It reads existing bookmarks into a simple `BookmarkEntry` tree and writes changes via incremental updates.

## Opening a PDF

```php
use Phpdftk\Pdf\Toolkit\BookmarkEditor;

// From file
$editor = BookmarkEditor::open('report.pdf');

// From string
$editor = BookmarkEditor::openString($pdfBytes);

// Encrypted PDF
$editor = BookmarkEditor::open('secured.pdf', password: 'secret');
```

## Reading bookmarks

### Get the full bookmark tree

```php
$bookmarks = $editor->getBookmarks();
// => list<BookmarkEntry>

foreach ($bookmarks as $entry) {
    echo "{$entry->title} -> page {$entry->pageNumber}\n";

    foreach ($entry->children as $child) {
        echo "  {$child->title} -> page {$child->pageNumber}\n";
    }
}
```

### Check for bookmarks

```php
if ($editor->hasBookmarks()) {
    // document has an outline
}
```

## BookmarkEntry

`BookmarkEntry` is a readonly value object representing one node in the outline tree:

```php
use Phpdftk\Pdf\Toolkit\Bookmark\BookmarkEntry;

$entry = new BookmarkEntry(
    title: 'Chapter 1',
    pageNumber: 1,          // 1-based
    children: [             // nested bookmarks
        new BookmarkEntry('Section 1.1', 1),
        new BookmarkEntry('Section 1.2', 3),
    ],
);
```

| Property | Type | Description |
|---|---|---|
| `title` | `string` | Bookmark display text |
| `pageNumber` | `int` | 1-based destination page |
| `children` | `list<BookmarkEntry>` | Nested child bookmarks |

## Replacing all bookmarks

```php
$editor->setBookmarks(
    new BookmarkEntry('Introduction', 1),
    new BookmarkEntry('Chapter 1', 3, [
        new BookmarkEntry('Section 1.1', 3),
        new BookmarkEntry('Section 1.2', 7),
    ]),
    new BookmarkEntry('Chapter 2', 12),
    new BookmarkEntry('Appendix', 20),
);
```

## Adding a bookmark

Append a single bookmark to the existing outline:

```php
$editor->addBookmark('New Chapter', 15);
```

## Removing all bookmarks

```php
$editor->removeBookmarks();
```

## Saving

```php
// To file
$editor->save('bookmarked.pdf');

// To string
$bytes = $editor->toBytes();
```

## Complete example

```php
BookmarkEditor::open('report.pdf')
    ->setBookmarks(
        new BookmarkEntry('Cover', 1),
        new BookmarkEntry('Table of Contents', 2),
        new BookmarkEntry('Body', 3, [
            new BookmarkEntry('Part I', 3),
            new BookmarkEntry('Part II', 15),
        ]),
        new BookmarkEntry('Index', 30),
    )
    ->save('bookmarked.pdf');
```

## Document info

```php
$editor->getPageCount(); // int
```

## Escape hatch

```php
$reader = $editor->getReader(); // PdfReader
```
