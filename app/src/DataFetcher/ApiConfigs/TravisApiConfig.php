<?php

namespace App\DataFetcher\Apis;

use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Requesters\AbstractRequester;
use App\DataFetcher\Requesters\RestRequester;
use SilverStripe\Core\Environment;


/*
Travis API States:
created - waiting for free workers
started - running
passed - green
errored - red
failed - red
canceled - grey - yes it's a typo in travis api
unknown - not in API, added by this script
*/
class TravisApiConfig implements ApiConfigInterface
{
    private const DOMAIN = 'https://api.travis-ci.com';

    public function getType(): string
    {
        return 'travis';
    }

    public function getCredentials(): string
    {
        return Environment::getEnv('TRAVIS_TOKEN');
    }

    public function getHeaders(AbstractRequester $requester, string $method): array
    {
        if (get_class($requester) == RestRequester::class) {
            if ($method == Consts::METHOD_GET) {
                return [
                    'Travis-API-Version: 3',
                    'Accept: application/vnd.travis-ci.2.1+json',
                    'Authorization: token "' . $this->getCredentials() . '"'
                ];
            } elseif ($method == Consts::METHOD_POST) {
                return [
                    'Travis-API-Version: 3',
                    'Content-Type: application/json',
                    'Authorization: token "' . $this->getCredentials() . '"'
                ];
            }
        }
        return [];
    }

    public function getCurlOptions(): array
    {
        return [];
    }

    public function deriveUrl(string $path, string $paginationOffset = ''): string
    {
        $domain = self::DOMAIN;
        $remotePath = str_replace($domain, '', $path);
        $remotePath = ltrim($remotePath, '/');
        return "{$domain}/{$remotePath}";
    }

    public function supportsPagination(string $path): bool
    {
        // not actually sure if pagination is supported or not.  simply haven't attemped to implement
        return false;
    }

    public function getPaginationOffsetInitial(): int
    {
        // untested
        return 1;
    }

    public function getPaginationOffsetIncrement(): int
    {
        // untested
        return 1;
    }

    public function getPaginationOffsetMaximum(): int
    {
        // untested
        return 1;
    }
}
