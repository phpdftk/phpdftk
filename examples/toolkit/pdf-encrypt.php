<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate an unencrypted input PDF.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    $seed->addHeading('Sealed Bid', 1);
    $seed->addText('Confidential pricing — do not distribute.');
    $seed->save('plain.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\Encryption\EncryptionMethod;
use Phpdftk\Pdf\Toolkit\Encryption\Permission;
use Phpdftk\Pdf\Toolkit\PdfEncrypt;

PdfEncrypt::open('plain.pdf')
    ->encrypt(
        userPassword: 'reader',
        ownerPassword: 'admin',
        method: EncryptionMethod::Aes128,    // also: Aes256 (PDF 2.0), Rc4128, Rc440
        permissions: Permission::PRINT | Permission::COPY,
    )
    ->save('encrypted.pdf');
// endregion

rename(__DIR__ . '/plain.pdf', example_output_path('toolkit/pdf-encrypt/input.pdf'));
rename(__DIR__ . '/encrypted.pdf', example_output_path('toolkit/pdf-encrypt/output.pdf'));
