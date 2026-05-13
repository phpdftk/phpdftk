<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 20)->moveTextPosition(72, 720)
    ->showText('Confidential — Internal Use Only')->endText();
$cs->beginText()->setFont($font, 12)->moveTextPosition(72, 690)
    ->showText('This document is encrypted with AES-256.')->endText();
$cs->beginText()->setFont($font, 12)->moveTextPosition(72, 670)
    ->showText('User password: "open-sesame" — owner password: "owner"')->endText();
$cs->beginText()->setFont($font, 12)->moveTextPosition(72, 650)
    ->showText('Printing is allowed; copying and modification are not.')->endText();

// AES-256 needs a stable 16-byte file id. Use any deterministic source.
$fileId = md5('phpdftk-encrypted-showcase', true);

$encryptor = PdfEncryptor::aes256(
    userPassword:  'open-sesame',
    ownerPassword: 'owner',
    fileId:        $fileId,
    permissions:   PdfEncryptor::PERM_PRINT, // copy/modify implicitly denied
);
$writer->setEncryption($encryptor);

$writer->save('encrypted.pdf');
// endregion

rename(__DIR__ . '/encrypted.pdf', example_output_path('writer/encrypted.pdf'));
