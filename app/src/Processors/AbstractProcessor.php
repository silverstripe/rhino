<?php

namespace App\Processors;

use App\DataFetcher\Requesters\RestRequester;

abstract class AbstractProcessor
{
    abstract public function getType(): string;

    abstract public function getSortOrder(): int;

    abstract public function getHtmlTableScript(): string;

    abstract public function process(bool $refetch): array;

    protected function buildStatusBadge(string $account, string $repo, string $branch)
    {
        $href = "https://travis-ci.com/github/{$account}/{$repo}/branches";
        $src = "https://travis-ci.com/{$account}/{$repo}.svg?branch={$branch}&t=0";
        // sort is used for column sorting
        // status is used for column filtering
        return "[status-badge metadata-sort= metadata-status= href=$href src=$src]";
    }

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

    protected function getTravisBadgeScript(): string
    {
        return <<<EOT

            (function() {

                function buildSrc(status) {
                    return [
                        'https://raw.githubusercontent.com',
                        '/travis-ci/travis-api/master/public/images/result',
                        '/' + status + '.png'
                    ].join('');
                }

                // add sample images to calculate image width based on browser zoom level
                var statuses = [
                    'passing',
                    'failing',
                    'error',
                    'unknown',
                    'canceled' // travis spells it with a single 'l'
                ];

                var statusToSort = {
                    'failing': 1,
                    'error': 1,
                    'canceled': 2,
                    'passing': 3,
                    'unknown': 4,
                    'blank': 9 // not a travis status, hard coded in html
                };

                var widthToStatus = {};

                var table = document.getElementsByTagName('table')[0]
                var parent = table.parentNode;
                var div = document.createElement('div');
                div.id = 'samples';
                parent.appendChild(div);

                for (var i = 0; i < statuses.length; i++) {
                    var status = statuses[i];
                    var img = document.createElement('img');
                    img.src = buildSrc(status);
                    div.appendChild(img);
                }

                // update timestamp on all badges to prevent caching
                var t = Date.now();
                var badges = document.querySelectorAll('.travis img[src*="&t=0"]');
                for (var i = 0; i < badges.length; i++) {
                    var badge = badges[i];
                    badge.src = badge.src.replace('&t=0', '&t=' + t);
                }

                var samplesLoaded = false;
                var c = 0;
                var interval = setInterval(function() {
                    var imgs = document.querySelectorAll('#samples img');
                    var allLoaded = true;
                    for (var i = 0; i < imgs.length; i++) {
                        var img = imgs[i];
                        var status = img.src.match(/\/([a-z\-]+)\.(png|svg)$/)[1];
                        if (img.hasAttribute('data-width')) {
                            continue;
                        }
                        var style = getComputedStyle(img);
                        var width = style.width.replace('px', '');
                        if (width == '0') {
                            allLoaded = false;
                            continue;
                        } else {
                            widthToStatus[width] = status;
                        }
                    }
                    if (allLoaded || c++ > 4 * 10) {
                        samplesLoaded = true;
                        clearInterval(interval);
                    }
                }, 250);

                // change error to failing cos error is grey and more helpful if it's red

                // update metadata based on badge width
                window.badgesLoaded = false;
                var c = 0;
                var interval2 = setInterval(function() {
                    if (!samplesLoaded) {
                        return;
                    }
                    var statusBadges = document.querySelectorAll('.status-badge.travis');
                    var allLoaded = true;
                    for (var i = 0; i < statusBadges.length; i++) {
                        var statusBadge = statusBadges[i];
                        var img = statusBadge.querySelector('img');
                        if (!img) {
                            // blank status for prevMinorBranch if it doesn't exist
                            continue;
                        }
                        var metadataSort = statusBadge.querySelector('.metadata-sort');
                        var metadataStatus = statusBadge.querySelector('.metadata-status');
                        if (img.hasAttribute('data-width')) {
                            continue;
                        }
                        var style = getComputedStyle(img);
                        var width = style.width.replace('px', '');
                        if (width == '0') {
                            allLoaded = false;
                            continue;
                        } else {
                            if (widthToStatus.hasOwnProperty(width)) {
                                var status = widthToStatus[width];
                                // 'error' is grey, want it red, so change it to 'failing'
                                if (status == 'error') {
                                    status = 'failing';
                                    img.src = buildSrc(status);
                                }
                                var sort = statusToSort[status];
                                metadataSort.innerHTML = sort;
                                metadataStatus.innerHTML = status;
                            }
                        }
                    }
                    if (allLoaded || c++ > 4 * 10) {
                        window.badgesLoaded = true;
                        clearInterval(interval2);
                    }
                }, 250);
            })();
EOT;
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
