<?php

declare(strict_types = 1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfig;

return (new Config())
    ->registerCustomFixers(new PhpCsFixerCustomFixers\Fixers())
    ->setRules([
        '@PHP84Migration'                                                     => true,
        '@Symfony'                                                            => true,
        'array_push'                                                          => true,
        'array_syntax'                                                        => ['syntax' => 'short'],
        'cast_spaces'                                                         => true,
        'constant_case'                                                       => ['case' => 'lower'],
        'declare_equal_normalize'                                             => ['space' => 'single'],
        'declare_strict_types'                                                => true,
        'elseif'                                                              => true,
        'full_opening_tag'                                                    => true,
        'fully_qualified_strict_types'                                        => true,
        'function_to_constant'                                                => true,
        'global_namespace_import'                                             => [
            'import_classes'   => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'psr_autoloading'                                                     => true,
        'no_useless_else'                                                     => true,
        'yoda_style'                                                          => [
            'equal'                => true,
            'identical'            => true,
            'less_and_greater'     => true,
            'always_move_variable' => true,
        ],
        'line_ending'                                                         => true,
        'list_syntax'                                                         => ['syntax' => 'short'],
        'logical_operators'                                                   => true,
        'lowercase_cast'                                                      => true,
        'lowercase_keywords'                                                  => true,
        'native_constant_invocation'                                          => true,
        'native_function_invocation'                                          => true,
        'no_leading_import_slash'                                             => true,
        'no_null_property_initialization'                                     => true,
        'no_whitespace_before_comma_in_array'                                 => true,
        'pow_to_exponentiation'                                               => true,
        'set_type_to_cast'                                                    => true,
        'single_import_per_statement'                                         => true,
        'ternary_operator_spaces'                                             => true,
        'ternary_to_null_coalescing'                                          => true,
        'binary_operator_spaces'                                              => ['operators' => ['=>' => 'align_single_space_minimal']],
        'concat_space'                                                        => ['spacing' => 'one'],
        'no_unneeded_final_method'                                            => true,
        'no_trailing_comma_in_singleline'                                     => true,
        'trailing_comma_in_multiline'                                         => [
            'after_heredoc' => true,
            'elements'      => ['array_destructuring', 'arrays', 'match', 'parameters'],
        ],
        'method_argument_space'                                               => [
            'after_heredoc'                    => true,
            'attribute_placement'              => 'ignore',
            'keep_multiple_spaces_after_comma' => false,
            'on_multiline'                     => 'ensure_fully_multiline',
        ],
        'braces_position'                                                     => [
            'allow_single_line_anonymous_functions'     => true,
            'allow_single_line_empty_anonymous_classes' => true,
            'functions_opening_brace'                   => 'next_line_unless_newline_at_signature_end',
        ],
        'single_line_throw'                                                   => false,
        PhpCsFixerCustomFixers\Fixer\NoTrailingCommaInSinglelineFixer::name() => true,
        PhpCsFixerCustomFixers\Fixer\ConstructorEmptyBracesFixer::name()      => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        Finder::create()
            ->in(
                [
                    __DIR__.'/config',
                    __DIR__.'/public',
                    __DIR__.'/src'
                ]
            )
            ->exclude('var')
            ->notPath([
                'secrets',
            ])
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
    )
    ->setLineEnding("\n")
    ->setCacheFile(__DIR__.'/var/build/phpcsfixer/.php_cs.cache')
    ->setUsingCache(true)
    ->setParallelConfig(
        config: new ParallelConfig(
            maxProcesses: 4,
            filesPerProcess: 32
        )
    );
