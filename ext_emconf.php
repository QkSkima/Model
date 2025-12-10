<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'QkSkima Model',
    'description' => 'A lightweight model layer for TYPO3 CMS providing BaseModel and BaseRepository classes that add validation and business rule guards to plain PHP classes. Validation rules are defined declaratively using PHP attributes, enabling clean and minimal domain modeling without Extbase or heavy frameworks.',
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
    'version' => '0.0.2',
];
