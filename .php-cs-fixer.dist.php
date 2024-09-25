<?php
/**
 * Wicket's PHP CS Fixer Configuration
 * @link https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
 * @link https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst
 */

$finder = (new PhpCsFixer\Finder())
	->in(__DIR__)
	->exclude([
			'node_modules',
			'vendor',
			'assets',
			'ci',
			'languages',
		])
	->notName([
		'.php-cs-fixer.dist.php',
	]);

return (new PhpCsFixer\Config())
	->setRules([
		'@PSR12'            => true,
		'@PER-CS'           => true,
		'@PHP81Migration'   => true,
		'array_syntax'      => ['syntax' => 'short'],   // Enforce short array syntax
		'no_unused_imports' => true,                    // Remove unused imports
	])
	->setFinder($finder);
