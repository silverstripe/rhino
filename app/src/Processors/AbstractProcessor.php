<?php

namespace App\Processors;

use App\DataFetcher\Requesters\RestRequester;

abstract class AbstractProcessor
{
    abstract public function getType(): string;

    abstract public function getSortOrder(): int;

    abstract public function getHtmlTableScript(): string;

    abstract public function process(bool $refetch): array;

    protected function buildGhaStatusBadge(
        string $account,
        string $repo,
        string $runName,
        string $branch,
        string $conclusion
    ) {
        $ubranch = urlencode($branch);
        // assuming that lowercase runName matches workflow filename
        $workflow = str_replace(' ', '-', strtolower($runName));
        $href = "https://github.com/{$account}/{$repo}/actions/workflows/{$workflow}.yml"
            . "?query=branch%3A{$ubranch}+-event%3Apull_request";

        // status from cron
        $src = "/_resources/themes/rhino/images/gha-ci-$conclusion.svg";

        $sort = 9;
        if ($conclusion == 'failure') {
            $sort = 1;
        } elseif ($conclusion == 'success') {
            $sort = 2;
        } elseif ($conclusion == 'no-status') {
            $sort = 3;
        }

        // sort is used for column sorting
        // status is used for column filtering
        return "[status-badge metadata-sort=$sort metadata-status=$conclusion href=$href src=$src]";
    }

    protected function buildBlankBadge()
    {
        return "[status-badge metadata-sort=9 metadata-status=blank href= src=]";
    }

    protected function getGhaStatusBadge(
        RestRequester $requester,
        bool $refetch,
        string $account,
        string $repo,
        string $branch,
        string $runName
    ): string {
        // will retrieve the most recent completed run
        $suffix = '';
        if ($runName === 'Merge-up') {
            $suffix = '&per_page=100';
        }
        $path = "/repos/$account/$repo/actions/runs?paginate=0&branch=$branch" . $suffix;
        $json = $requester->fetch($path, '', $account, $repo, $refetch);
        $conclusion = 'no-status'; // not a real conclusion type, I made this up
        foreach ($json->root->workflow_runs ?? [] as $run) {
            if ($run->name != $runName) {
                continue;
            }
            if ($run->status != 'completed') {
                continue;
            }
            if ($run->event == 'pull_request') {
                continue;
            }
            if (!in_array($run->conclusion, ['success', 'failure'])) {
                continue;
            }
            $conclusion = $run->conclusion;
            break;
        }
        return $this->buildGhaStatusBadge($account, $repo, $runName, $branch, $conclusion);
    }

    protected function prStats(array $nodes)
    {
        $filePathsArr = array_map(fn($node) => $node->path, $nodes);
        $fileTypesArr = array_unique(array_map(fn($path) => pathinfo($path, PATHINFO_EXTENSION), $filePathsArr));
        return [
            'numFiles' => count($nodes),
            'linesAdded' => array_sum(array_map(fn($node) => $node->additions, $nodes)),
            'linesRemoved' => array_sum(array_map(fn($node) => $node->deletions, $nodes)),
            'docs' => in_array('md', $fileTypesArr) ? '1' : '',
            'unit' => count(array_filter($filePathsArr, fn($path) => strpos($path, 'Test.php') !== false)) ? '1' : '',
            'behat' => in_array('feature', $fileTypesArr) ? '1' : '',
            'jest' => count(array_filter($filePathsArr, fn($path) => strpos($path, '-test.js') !== false)) ? '1' : '',
            'fileTypes' => implode(' ', $fileTypesArr),
        ];
    }
}
