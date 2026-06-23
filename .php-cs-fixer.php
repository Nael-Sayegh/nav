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
        'mtcaptcha',
    ])
    ->notPath('#^include/lib/facebook/composer\.(json|lock)$#');

return (new PhpCsFixer\Config)
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        'control_structure_braces' => true,
        'curly_braces_position' => [
            'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'functions_opening_brace'          => 'next_line_unless_newline_at_signature_end',
            'classes_opening_brace'            => 'next_line_unless_newline_at_signature_end',
            'anonymous_classes_opening_brace'  => 'next_line_unless_newline_at_signature_end',
        ],
        'control_structure_continuation_position' => ['position' => 'next_line'],
        'blank_line_after_opening_tag' => true,
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'no_unused_imports' => true,
        'global_namespace_import' => ['import_classes' => true, 'import_functions' => true, 'import_constants' => true],
        'single_quote' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_useless_else' => true,
        'no_superfluous_elseif' => true,
        'simplified_if_return' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_curly_braces' => true,
        'yoda_style' => false,
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'strict_param' => true,
        'declare_strict_types' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'phpdoc_to_param_type' => true,
        'phpdoc_to_return_type' => true,
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_separation' => true,
    ])
    ->setFinder($finder);
