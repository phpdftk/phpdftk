<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use Phpdftk\Pdf\Writer\PdfWriter;

// Public-key encryption locks the PDF to one or more X.509 recipients. Each
// recipient unlocks the document with their own private key — no shared password.
// Here we generate a throwaway RSA keypair and self-signed cert so the example
// runs anywhere. In production, you would import a recipient's certificate.
$config = [
    'digest_alg'       => 'sha256',
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];
$privateKey = openssl_pkey_new($config);
$csr  = openssl_csr_new(['commonName' => 'phpdftk-recipient'], $privateKey, $config);
$cert = openssl_csr_sign($csr, null, $privateKey, 365, $config);
openssl_x509_export($cert, $certPem);
openssl_pkey_export($privateKey, $keyPem);

// Save the keypair alongside the PDF so docs can demonstrate decryption.
$samplesDir = dirname(example_output_path('core/security/public-key.pdf'));
file_put_contents($samplesDir . '/recipient.cert.pem', $certPem);
file_put_contents($samplesDir . '/recipient.key.pem',  $keyPem);

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 20)->moveTextPosition(72, 720)
    ->showText('Public-Key Encrypted PDF')->endText();
$cs->beginText()->setFont($body, 12)->moveTextPosition(72, 690)
    ->showText('This document is sealed for a single X.509 recipient.')
    ->endText();
$cs->beginText()->setFont($body, 12)->moveTextPosition(72, 670)
    ->showText('Only the holder of the matching private key can open it.')
    ->endText();
$cs->beginText()->setFont($body, 12)->moveTextPosition(72, 650)
    ->showText('Recipient certificate and key are written next to this file.')
    ->endText();

$fileId = md5('phpdftk-public-key-showcase', true);
$encryptor = PdfEncryptor::publicKeyAes256(
    recipients: [['cert' => $certPem]],
    fileId:     $fileId,
);
$writer->setEncryption($encryptor);

$writer->save('public-key.pdf');
// endregion

rename(__DIR__ . '/public-key.pdf', example_output_path('core/security/public-key.pdf'));
