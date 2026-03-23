<?php

namespace App\Tests\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Requesters\RestRequester;

/**
 * RestRequester subclass that counts how many times the API is actually called.
 */
class CountingRestRequester extends RestRequester
{
    public int $fetchCount = 0;

    /**
     * Initialises the requester with a real GitHubApiConfig.
     */
    public function __construct()
    {
        parent::__construct(new GitHubApiConfig());
    }

    /**
     * Delegates to the parent fetch, suppressing output, to allow DB caching to function normally.
     */
    public function fetch(
        string $path,
        string $postBody,
        string $account,
        string $repo,
        bool $refetch
    ): \stdClass {
        ob_start();
        $result = parent::fetch($path, $postBody, $account, $repo, $refetch);
        ob_end_clean();
        return $result;
    }

    /**
     * Increments the fetch counter and returns an empty JSON array instead of hitting the API.
     */
    protected function fetchDataFromApi(string $path, string $postBody = ''): string
    {
        $this->fetchCount++;
        return '[]';
    }
}
