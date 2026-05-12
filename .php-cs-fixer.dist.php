<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'public/bundles',
    ])
    ->name('*.php')
    ->notName('*.cache')
    // Symfony 7.4+ generates this file on cache warmup; do not lint or it fails CI after bin/console.
    ->notPath('#^config/reference\\.php$#');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'no_superfluous_phpdoc_tags' => [
            'remove_inheritdoc' => true,
        ],
        'phpdoc_to_comment' => false,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'concat_space' => ['spacing' => 'none'],
        'phpdoc_align' => ['align' => 'left'],
    ])
    ->setFinder($finder)
    ->setCacheFile('var/.php-cs-fixer.cache');
