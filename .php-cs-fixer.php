<?php

$finder = new PhpCsFixer\Finder()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->append([
        __FILE__,
        __DIR__ . '/bin/elmigrator',
    ]);

return new PhpCsFixer\Config()
    ->setFinder($finder)
    ->setRules([
        '@PER-CS' => true,
        '@PSR12' => true,
        '@PHP8x4Migration' => true,
        'new_expression_parentheses' => false,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
        'no_unused_imports' => true,
        'operator_linebreak' => ['only_booleans' => true],
    ]);
