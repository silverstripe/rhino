<?php

namespace App\Misc;

class MetaData
{
    public const TEAMS = [
        'product' => [
            'maxime-rainville',
            'emteknetnz',
            'GuySartorelli',
            'sabina-talipova',
        ],
        'core-committers' => [
            'sminnee',
            'wilr',
            'chillu',
            'michalkleiner',
            'Cheddam',
            'dhensby',
            'unclecheese',
            'madmatt',
            'kinglozzer',
            'ScopeyNZ',
            'Cheddam',
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
            'sspak',
            'MinkFacebookWebDriver'
        ]
    ];
}
