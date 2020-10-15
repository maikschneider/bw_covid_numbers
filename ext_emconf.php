<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'COVID-19 numbers',
    'description' => 'Display number of corona virus cases in Germany',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3' => '7.0.0-10.9.99',
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Blueways\\BwCovidNumbers\\' => 'Classes'
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'clearCacheOnLoad' => 1,
    'author' => 'Maik Schneider',
    'author_email' => 'm.schneider@blueways.de',
    'author_company' => 'blueways',
    'version' => '1.0.0',
];
