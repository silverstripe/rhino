<?php

namespace App\Tests\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Requesters\RestRequester;
use stdClass;

/**
 * RestRequester stub that returns empty data without making any HTTP requests.
 */
class FakeRestRequester extends RestRequester
{
    /**
     * Initialises the requester with a real GitHubApiConfig.
     */
    public function __construct()
    {
        parent::__construct(new GitHubApiConfig());
    }

    /**
     * Returns an empty root object without making any HTTP request or touching the DB.
     */
    public function fetch(
        string $path,
        string $postBody,
        string $account,
        string $repo,
        bool $refetch
    ): stdClass {
        return (object) ['root' => []];
    }

    /**
     * Returns an empty string — no HTTP requests are made by this requester.
     */
    protected function fetchDataFromApi(string $path, string $postBody = ''): string
    {
        return '';
    }
}
