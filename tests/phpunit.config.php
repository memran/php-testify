<?php
return [
    'bootstrap' => __DIR__ . '/vendor/autoload.php',
    'colors' => 'always',
    'verbose' => true,
    'stopOnFailure' => false,
    'processIsolation' => false,
    'backupGlobals' => false,
    'backupStaticAttributes' => false,
    'beStrictAboutChangesToGlobalState' => false,
    'convertDeprecationsToExceptions' => false,
    'convertErrorsToExceptions' => true,
    'convertNoticesToExceptions' => true,
    'convertWarningsToExceptions' => true,
    'testSuite' => [
        'name' => 'Testify Test Suite',
        'directories' => [__DIR__ . '/tests']
    ],
    'coverage' => [
        'include' => [__DIR__ . '/src'],
        'report' => [
            'html' => __DIR__ . '/coverage',
            'text' => 'php://stdout'
        ]
    ],
    'php' => [
        'ini' => [
            'error_reporting' => '-1',
            'display_errors' => '1',
            'display_startup_errors' => '1'
        ]
    ]
];
