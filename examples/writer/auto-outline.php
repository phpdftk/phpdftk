<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->setTitle('Auto-generated outline');

// Turn on auto-outline before the first heading. Every subsequent
// addHeading call registers an OutlineItem; heading levels (1–6)
// drive parent/child nesting.
$pdf->enableOutline();

$pdf->addHeading('Part One: Foundations', 1);
$pdf->addText('Introductory material covering the basics.');

$pdf->addHeading('Chapter 1: Origins', 2);
$pdf->addText('The story begins with a single decision.');

$pdf->addHeading('A first principle', 3);
$pdf->addText('Three-level headings nest correctly under their level-2 parent.');

$pdf->addHeading('A second principle', 3);
$pdf->addText('Sibling at the same level chains via /Prev and /Next.');

$pdf->addHeading('Chapter 2: Aftermath', 2);
$pdf->addText('Returning to level 2 closes the deeper level-3 chain — '
    . 'the next level-3 heading would start a fresh chain under this chapter.');

$pdf->addHeading('Part Two: Practice', 1);
$pdf->addText('Returning to level 1 starts a new top-level branch.');

$pdf->addHeading('Chapter 3: Application', 2);
$pdf->addText('All headings since enableOutline() show up in the viewer\'s bookmarks panel.');

$pdf->save('auto-outline.pdf');
// endregion

rename(__DIR__ . '/auto-outline.pdf', example_output_path('writer/auto-outline.pdf'));
