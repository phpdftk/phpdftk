<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/packages',
        __DIR__ . '/benchmarks',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(false);
