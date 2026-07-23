<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mai Seeder',
    'description' => 'Laravel-inspired, versioned content migrations for TYPO3 - seed and clean up records and their relations across environments.',
    'category' => 'module',
    'author' => 'Maispace',
    'state' => 'stable',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'backend' => '13.4.0-14.99.99',
            'extbase' => '13.4.0-14.99.99',
            'fluid' => '13.4.0-14.99.99',
            'mai_base' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
