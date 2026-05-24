<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PageContext;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\Theme;

// Reserve 28pt at the top and bottom of every page for the decorator
// closures registered below — body content automatically respects the
// reserve and never overlaps headers or footers.
$theme = new Theme(
    margin: 48.0,
    headerHeight: 28.0,
    footerHeight: 28.0,
);

$pdf = new Pdf(theme: $theme);
$pdf->setTitle('Quarterly Report');

// The header closure renders text and needs a font handle. The footer
// uses showPageNumbers() below, which resolves fonts itself.
$boldFont = $pdf->writer()->addFont(new Type1Font(StandardFont::HelveticaBold));

// Header: document title, left-aligned in the reserved top band.
$pdf->setHeader(function (PageContext $ctx) use ($boldFont): void {
    $ctx->page->drawText(
        'Quarterly Report — FY2026',
        $ctx->theme->margin,
        $ctx->pageHeight - 22.0,
        $boldFont,
        9.0,
    );
});

// Footer: "Page X of Y" centered. `showPageNumbers()` is sugar over
// setFooter() that uses the deferred totalPages — equivalent to writing
// the closure by hand, just shorter.
$pdf->showPageNumbers();

// Watermark: the string form renders centered, diagonal, light-grey text.
$pdf->setWatermark('DRAFT', opacity: 0.18, angleDeg: 30.0);

// Now build the body — addText paginates automatically and never
// overlaps the header / footer regions.
$pdf->addHeading('Executive summary', 1);
$pdf->addText(
    'This document demonstrates the per-page render hooks: setHeader, setFooter, '
    . 'and setWatermark. The hooks fire in a single deferred pass after all flow '
    . 'content is placed, which is why the footer can show the correct total page '
    . 'count even though it draws at the top-down build time the total was unknown.',
);

for ($i = 1; $i <= 3; $i++) {
    $pdf->addHeading("Section {$i}", 2);
    for ($p = 0; $p < 4; $p++) {
        $pdf->addText(
            'The quick brown fox jumps over the lazy dog. ' . str_repeat(
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ',
                6,
            ),
        );
    }
}

$pdf->save('headers-footers-watermark.pdf');
// endregion

rename(
    __DIR__ . '/headers-footers-watermark.pdf',
    example_output_path('writer/headers-footers-watermark.pdf'),
);
