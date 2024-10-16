<?php

/**
 * Wicket's PHP CS Fixer Configuration
 *
 * @link https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
 * @link https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst
 */

$finder = PhpCsFixer\Finder::create()
    ->exclude([
        '.ci',
        'node_modules',
        'vendor',
        'languages',
        'dist',
    ])
    ->notName([
        'composer.lock',
    ])
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'            => true,
        '@PER-CS'           => true,
        '@PHP81Migration'   => true,
        'array_syntax'      => ['syntax' => 'short'],   // Enforce short array syntax
        'no_unused_imports' => true,                    // Remove unused imports
    ])
    ->setFinder($finder)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
