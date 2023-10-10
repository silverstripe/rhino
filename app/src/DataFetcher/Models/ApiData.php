<?php

namespace App\DataFetcher\Models;

use SilverStripe\ORM\DataObject;

class ApiData extends DataObject
{
    private static $table_name = 'ApiData';

    private static $db = [
        // TODO: datetime of insertion
        'Api' => 'Varchar',
        'Requester' => 'Varchar',
        'Path' => 'Varchar',
        'PostBodyHash' => 'Varchar',
        'Account' => 'Varchar',
        'Repo' => 'Varchar',
        'ResponseBody' => 'Text'
    ];

    // TODO: composite index
    private static $indexes = [
        'Api' => true,
        'Requester' => true,
        'Path' => true,
        'PostBodyHash' => true,
        'Account' => true,
        'Repo' => true,
    ];
}
