<?php

namespace App\Tests\Misc;

use App\DataFetcher\Requesters\AbstractRequester;

/**
 * Test requester that serves files from an in-memory array instead of making HTTP requests.
 */
class ArrayRequester extends AbstractRequester
{
    /**
     * Initialises the requester with the in-memory file map.
     */
    public function __construct(private array $files)
    {
    }

    /**
     * Returns 'array' as the requester type identifier.
     */
    public function getType(): string
    {
        return 'array';
    }

    /**
     * Returns the parsed file content from the in-memory array keyed by filename.
     */
    public function fetchFile(
        string $account,
        string $repo,
        string $branch,
        string $filePath,
        bool $refetch
    ): mixed {
        return match ($filePath) {
            'consts.php' => $this->files['consts.php'],
            'data.json' => json_decode($this->files['data.json']),
            'repositories.json' => json_decode($this->files['repositories.json']),
            default => null,
        };
    }

    /**
     * Returns an empty string — no HTTP requests are made by this requester.
     */
    protected function fetchDataFromApi(string $path, string $postBody = ''): string
    {
        return '';
    }
}
