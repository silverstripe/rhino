<?php

namespace App\Misc;

class MetaData
{
    public const TEAMS = [
        'product' => [
            'emteknetnz',
            'GuySartorelli',
        ],
        'core-committers' => [
            'sminnee',
            'wilr',
            'chillu',
            'maxime-rainville',
            'michalkleiner',
            'Cheddam',
            'unclecheese',
            'madmatt',
            'kinglozzer',
        ],
        'bot' => [
            'github-actions',
            'dependabot',
        ],
    ];

    public const MODULES_WITHOUT_NEXT_MINOR_BRANCH = [
        'bringyourownideas' => [
            'silverstripe-maintenance',
            'silverstripe-composer-update-checker',
            'silverstripe-composer-security-checker',
        ],
        'lekoala' => [
            'silverstripe-debugbar',
        ],
    ];

    public const MODULES_WITH_CUSTOM_TRAVIS = [
        'silverstripe' => [
            'cwp-starter-theme', // watea-theme uses shared config
            'silverstripe-upgrader',
            'MinkFacebookWebDriver'
        ]
    ];
}
