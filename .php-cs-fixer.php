<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__,
        __DIR__.'/admin',
        __DIR__.'/include',
        __DIR__.'/tasks',
        __DIR__.'/a',
        __DIR__.'/c',
        __DIR__.'/r',
        __DIR__.'/u',
        __DIR__.'/403',
        __DIR__.'/scripts',
    ])
    ->exclude([
        'adminer',
        'vendor',
        'cache',
        'mtcaptcha'
    ])
    ->notPath('#^include/lib/facebook/composer\.(json|lock)$');

return (new PhpCsFixer\Config)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'logical_operators' => true,
        'control_structure_braces'     => true,
        'heredoc_indentation' => true,
        'curly_braces_position' => [
            'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'functions_opening_brace'          => 'next_line_unless_newline_at_signature_end',
            'classes_opening_brace'            => 'next_line_unless_newline_at_signature_end',
            'anonymous_classes_opening_brace'  => 'next_line_unless_newline_at_signature_end',
        ],
        'control_structure_continuation_position' => [
            'position' => 'next_line',
        ],
        'array_syntax'   => ['syntax' => 'short'],
        'single_quote'   => true,
        'ordered_imports'=> true,
    ])
    ->setFinder($finder);
