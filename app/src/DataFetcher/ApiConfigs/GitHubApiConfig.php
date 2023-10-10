<?php

namespace App\DataFetcher\Apis;

use App\DataFetcher\Requesters\AbstractRequester;
use SilverStripe\Core\Environment;

class GitHubApiConfig implements ApiConfigInterface
{
    private const DOMAIN = 'https://api.github.com';

    public function getType(): string
    {
        return 'github';
    }

    public function getCredentials(): string
    {
        return implode(':', [Environment::getEnv('GITHUB_USER'), Environment::getEnv('GITHUB_TOKEN')]);
    }

    public function getHeaders(AbstractRequester $requester, string $method): array
    {
        return [
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0'
        ];
    }

    public function getCurlOptions(): array
    {
        return [
            CURLOPT_USERPWD => $this->getCredentials()
        ];
    }

    public function deriveUrl(string $path, string $paginationOffset = ''): string
    {
        $domain = self::DOMAIN;
        $remotePath = str_replace($domain, '', $path);
        $remotePath = ltrim($remotePath, '/');
        // requesting details
        if (!$this->supportsPagination($path)) {
            $remotePath = str_replace('?paginate=0&', '?', $remotePath);
            $remotePath = str_replace('?paginate=0', '', $remotePath);
            $remotePath = str_replace('&paginate=0&', '&', $remotePath);
            $remotePath = str_replace('&paginate=0', '', $remotePath);
            return "{$domain}/{$remotePath}";
        }
        // requesting a list
        $op = strpos($remotePath, '?') ? '&' : '?';
        $offset = $paginationOffset ? "page={$paginationOffset}" : '';
        if (strpos($remotePath, 'per_page=') !== false) {
            $off = $offset ? "{$op}{$offset}" : '';
            return "{$domain}/{$remotePath}{$off}";
        } else {
            $off = $offset ? "{$op}{$offset}" : '';
            return "{$domain}/{$remotePath}{$op}per_page=100{$off}";
        }
    }

    public function supportsPagination(string $path): bool
    {
        // manually disable pagination
        if (strpos($path, '?paginate=0') !== false || strpos($path, '&paginate=0') !== false) {
            return false;
        }
        // requesting details
        if (preg_match('#/[0-9]+$#', $path) || preg_match('@/[0-9]+/files$@', $path)) {
            return false;
        }
        // compare
        if (preg_match('#/compare/#', $path)) {
            return false;
        }
        // requesting contents of a file
        if (preg_match('#/contents/#', $path)) {
            return false;
        }
        // returning a list
        return true;
    }

    public function getPaginationOffsetInitial(): int
    {
        // page=
        return 1;
    }

    public function getPaginationOffsetIncrement(): int
    {
        // page=(+1)
        return 1;
    }

    public function getPaginationOffsetMaximum(): int
    {
        // page=
        return 10;
    }
}
