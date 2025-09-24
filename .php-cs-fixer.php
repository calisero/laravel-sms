<?php

$paths = [
    __DIR__ . '/src',
    __DIR__ . '/tests',
    __DIR__ . '/config',
    __DIR__ . '/routes',
];

$finder = (new PhpCsFixer\Finder())
    ->in($paths)
    ->files()
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (function () use ($finder) {
    $config = new PhpCsFixer\Config();

    // Allow running on newer PHP versions when supported by the library.
    // Guarded so it works with older php-cs-fixer versions that lack the method.
    if (method_exists($config, 'setUnsupportedPhpVersionAllowed')) {
        $config->setUnsupportedPhpVersionAllowed(true);
    }

    // Enable parallel processing when available (PHP_CS_FIXER_PARALLEL=1 env or detect)
    if (method_exists('PhpCsFixer\\Runner\\Parallel\\ParallelConfigFactory', 'detect')) {
        $config->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
    }

    return $config
        ->setRules([
            '@PSR12' => true,
            'array_syntax' => ['syntax' => 'short'],
            'ordered_imports' => ['sort_algorithm' => 'alpha'],
            'no_unused_imports' => true,
            'not_operator_with_successor_space' => true,
            'trailing_comma_in_multiline' => true,
            'phpdoc_scalar' => true,
            'unary_operator_spaces' => true,
            'binary_operator_spaces' => true,
            'blank_line_before_statement' => [
                'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
            ],
            'phpdoc_single_line_var_spacing' => true,
            'phpdoc_var_without_name' => true,
            'class_attributes_separation' => [
                'elements' => [
                    'method' => 'one',
                ],
            ],
            'method_argument_space' => [
                'on_multiline' => 'ensure_fully_multiline',
                'keep_multiple_spaces_after_comma' => true,
            ],
            'single_trait_insert_per_statement' => true,
        ])
        ->setFinder($finder);
})();
