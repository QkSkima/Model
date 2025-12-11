<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'QkSkima Model',
    'description' => 'A lean but powerful DSL-based model system for TYPO3 with Symfony validation, property access, nested relations, and business rule validation. QkSkima Model is an alternative approach, when you think Extbase is too heavy for the job.',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'QkSkima\\Model\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Colin Atkins',
    'author_email' => 'atkins@hey.com',
    'author_company' => 'QkSkima Inc.',
    'version' => '0.0.3',
];
