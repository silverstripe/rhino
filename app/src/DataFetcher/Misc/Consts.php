<?php

namespace App\DataFetcher\Misc;

class Consts
{
    public const METHOD_GET = 'get';

    public const METHOD_POST = 'post';

    public const MODULES = [
        'regular' => [
            'bringyourownideas' => [
                'silverstripe-maintenance',
                'silverstripe-composer-update-checker',
            ],
            'colymba' => [
                // 'GridFieldBulkEditingTools' // supported dependency
            ],
            'dnadesign' => [
                'silverstripe-elemental-subsites', // supported depenendecy
                'silverstripe-elemental-userforms', // supported depenendecy
            ],
            'silverstripe' => [
                'silverstripe-reports',
                'silverstripe-siteconfig',
                'silverstripe-versioned',
                'silverstripe-versioned-admin',
                'silverstripe-userhelp-content', // not an installed module, though still relevant
                'comment-notifications',
                'cwp',
                'cwp-agencyextensions',
                'cwp-core',
                'cwp-pdfexport',
                'cwp-search',
                'cwp-starter-theme',
                'cwp-watea-theme',
                'doorman',
                'silverstripe-simple',
                'silverstripe-akismet',
                'silverstripe-auditor',
                'silverstripe-admin',
                'silverstripe-asset-admin',
                'silverstripe-assets',
                'silverstripe-blog',
                'silverstripe-campaign-admin',
                'silverstripe-ckan-registry',
                'silverstripe-cms',
                'silverstripe-config',
                'silverstripe-errorpage',
                'silverstripe-framework',
                'silverstripe-graphql',
                'silverstripe-installer',
                'silverstripe-comments',
                'silverstripe-content-widget',
                'silverstripe-contentreview',
                'silverstripe-crontask',
                'silverstripe-documentconverter',
                'silverstripe-dynamodb',
                'silverstripe-elemental',
                'silverstripe-elemental-bannerblock',
                'silverstripe-elemental-fileblock',
                'silverstripe-environmentcheck',
                'silverstripe-event-dispatcher',
                'silverstripe-externallinks',
                'silverstripe-fulltextsearch',
                'silverstripe-gridfieldqueuedexport',
                'silverstripe-html5',
                'silverstripe-hybridsessions',
                'silverstripe-iframe',
                'silverstripe-ldap',
                'silverstripe-lumberjack',
                'silverstripe-mimevalidator',
                'silverstripe-postgresql',
                'silverstripe-realme',
                'silverstripe-session-manager',
                'recipe-authoring-tools',
                'recipe-blog',
                'recipe-ccl',
                'recipe-cms',
                'recipe-collaboration',
                'recipe-content-blocks',
                'recipe-core',
                'recipe-form-building',
                'recipe-reporting-tools',
                'recipe-kitchen-sink',
                'recipe-plugin',
                'recipe-services',
                'recipe-solr-search',
                'silverstripe-registry',
                'silverstripe-restfulserver',
                'silverstripe-securityreport',
                'silverstripe-segment-field',
                'silverstripe-selectupload',
                'silverstripe-sharedraftcontent',
                'silverstripe-sitewidecontent-report',
                'silverstripe-spamprotection',
                'silverstripe-spellcheck',
                'silverstripe-sqlite3',
                'silverstripe-staticpublishqueue',
                'silverstripe-subsites',
                'silverstripe-tagfield',
                'silverstripe-taxonomy',
                'silverstripe-textextraction', // only a supported dependency, though ...
                'silverstripe-userforms',
                'silverstripe-widgets',
                'silverstripe-mfa',
                'silverstripe-totp-authenticator',
                'silverstripe-webauthn-authenticator',
                'silverstripe-login-forms',
                'silverstripe-security-extensions',
                // 'silverstripe-upgrader',
                'silverstripe-versionfeed', // not in commercially supported list, though is in cwp
                'vendor-plugin',
                'developer-docs',
                '.github',
                'silverstripe-frameworktest',
                'silverstripe-linkfield',
            ],
            'symbiote' => [
                'silverstripe-advancedworkflow',
                'silverstripe-gridfieldextensions', // only a supported dependency, though ...
                'silverstripe-multivaluefield',
                'silverstripe-queuedjobs',
            ],
            'tractorcow' => [
            //    'classproxy', // supported dependency
                // 'silverstripe-fluent', // supported dependency - misrepoerting next minor branch as only 4.1.x-dev
            //    'silverstripe-proxy-db', // supported dependency
            ],
            'undefinedoffset' => [
            //    'sortablegridfield'
            ]
        ],
        'ss3' => [
            'silverstripe' => [
                // 'cwp-recipe-basic',
                // 'cwp-recipe-basic-dev',
                // 'cwp-recipe-blog',
                // 'silverstripe-activedirectory',
                // 'silverstripe-dms',
                // 'silverstripe-dms-cart',
                // 'silverstripe-secureassets',
                // 'silverstripe-staticpublishqueue',
                // 'silverstripe-translatable',
            ],
            'symbiote' => [
                'silverstripe-versionedfiles',
            ],
        ],
        'legacy' => [
            'silverstripe' => [
                'cwp-installer',
                'cwp-recipe-cms',
                'cwp-recipe-core',
                'cwp-recipe-kitchen-sink',
                'cwp-recipe-search',
                'cwp-theme-default',
                'silverstripe-controllerpolicy',
                'silverstripe-elemental-blocks',
                'silverstripe-sqlite3',
            ],
            'bringyourownideas' => [
                'silverstripe-composer-security-checker', // abandoned
            ],
        ],
        'tooling' => [
            'composer' => [
                // 'installers' // supported depenendecy
            ],
            'lekoala' => [
                // 'silverstripe-debugbar',
            ],
            'hafriedlander' => [
                // 'phockito', // supported depenendecy
                // 'silverstripe-phockito' // supported depenendecy
            ],
            'silverstripe' => [
                'cow',
                'eslint-config',
                'github-actions-ci-cd',
                'MinkFacebookWebDriver',
                'recipe-testing',
                'silverstripe-behat-extension',
                'silverstripe-graphql-devtools',
                'silverstripe-testsession',
                'webpack-config',
                'gha-merge-up',
                'gha-generate-matrix',
                'gha-gauge-release',
                'gha-action-ci',
                'gha-update-js',
                'gha-keepalive',
                'gha-pull-request',
                'gha-auto-tag',
                'gha-run-tests',
                'gha-trigger-ci',
                'gha-tag-release',
                'gha-ci',
                'gha-dispatch-ci',
                'gha-issue',
                'markdown-php-codesniffer',
                'silverstripe-standards',
                'documentation-lint',
            ]
        ],
    ];
}
