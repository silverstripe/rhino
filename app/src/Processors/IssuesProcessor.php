<?php

namespace App\Processors;

use App\Misc\MetaData;
use App\Utils\DateTimeUtil;
use App\Utils\MiscUtil;
use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Requesters\RestRequester;

class IssuesProcessor extends AbstractProcessor
{
    public function getType(): string
    {
        return 'issues';
    }

    public function getSortOrder(): int
    {
        return 1;
    }

    public function getHtmlTableScript(): string
    {
        return <<<EOT
            (function() {
                // sort by createdAt desc
                var interval = window.setInterval(function() {
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == 'createdAt') {
                            window.clearInterval(interval);
                            // click twice to sort desc
                            th.click();
                            th.click();
                            return;
                        }
                    }
                }, 250);
            })();
EOT;
    }

    public function process(bool $refetch): array
    {
        $apiConfig = new GitHubApiConfig();
        $requester = new RestRequester($apiConfig);
        $modules = Consts::MODULES;
    
        $rows = [];
        
        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    $varsList[] = [$account, $repo];
                }
            }
        }

        foreach ($varsList as $vars) {
            list($account, $repo) = $vars;
            $path = "/repos/$account/$repo/issues?state=open";
            $json = $requester->fetch($path, '', $account, $repo, $refetch);
            foreach ($json->root ?? [] as $issue) {
                if (!is_object($issue)) {
                    continue;
                }
                // issues api also contains pull requests for some weird reason
                if (preg_match('@/pull/[0-9]+$@', $issue->html_url)) {
                    continue;
                }
                $labels = empty($issue->labels) ? [] : $issue->labels;
                
                $row = [
                    'title' => $issue->title,
                    'account' => $account,
                    'repo' => $repo,
                    'url' => $issue->html_url,
                    'label_type' => '',
                    'label_impact' => '',
                    'label_complexity' => '',
                    'label_affects' => '',
                    'author' => $issue->user->login,
                    'authorType' => MiscUtil::deriveUserType($issue->user->login, MetaData::TEAMS),
                    'createdAt' => DateTimeUtil::timestampToNZDate($issue->created_at),
                    'updatedAt' => DateTimeUtil::timestampToNZDate($issue->updated_at),
                ];
                foreach ($labels as $label) {
                    foreach (array_keys($row) as $key) {
                        if (strpos($key, 'label_') !== 0) {
                            continue;
                        }
                        $s = str_replace('label_', '', $key);
                        if (preg_match("@^$s/(.+)$@", strtolower($label->name), $m)) {
                            $row[$key] = strtolower($m[1]);
                        }
                        if (strtolower($label->name) == 'epic') {
                            $row['label_type'] = 'epic';
                        }
                    }
                }
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
