<?php

namespace App\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Misc\Consts;
use App\DataFetcher\Requesters\AbstractRequester;
use App\DataFetcher\Requesters\RestRequester;
use App\Utils\DateTimeUtil;
use stdClass;

class ReleasesProcessor extends AbstractProcessor
{
    // set to false when CMS 4 is no longer supported
    public const LAST_MAJOR_SUPPORTED = true;

    private const EXCLUDE_MODULES = [
        'tractorcow/classproxy',
        'tractorcow/silverstripe-fluent',
        'tractorcow/silverstripe-proxy-db',
    ];

    private $requestor;

    private $refetch = false;

    public function __construct()
    {
        $apiConfig = new GitHubApiConfig();
        $this->requestor = new RestRequester($apiConfig);
    }

    public function getType(): string
    {
        return 'releases';
    }

    public function getSortOrder(): int
    {
        return 5;
    }

    public function getHtmlTableScript(): string
    {
        return $this->getTravisBadgeScript() . <<<EOT
            (function() {
                // convert changelog line break to <br>
                var tds = document.getElementsByTagName('td');
                for (var i = 0; i < tds.length; i++) {
                    var td = tds[i];
                    td.innerHTML = td.innerHTML.replace(/\\n\*/g, '<br>*', td.innerHTML);
                }
                // sort by curMnr_compare
                // sort by createdAt desc
                var interval = window.setInterval(function() {
                    if (!window.ghaBadgesLoaded) {
                        return;
                    }
                    var ths = document.getElementsByTagName('th');
                    for (var j = 0; j < ths.length; j++) {
                        var th = ths[j];
                        if (th.hasAttribute('_sorttype') && th.innerText == 'curMnr_compare') {
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
        $this->refetch = $refetch;
        return $this->deriveData();
    }

    private function getRequestor(): AbstractRequester
    {
        return $this->requestor;
    }

    private function deriveData(): array
    {
        $modules = Consts::MODULES;
        $rows = [];

        $varsList = [];
        foreach (['regular', 'tooling'] as $moduleType) {
            foreach ($modules[$moduleType] as $account => $repos) {
                foreach ($repos as $repo) {
                    if (in_array("{$account}/{$repo}", self::EXCLUDE_MODULES)) {
                        continue;
                    }
                    $varsList[] = [$account, $repo];
                }
            }
        }

        // $varsList = [
        //     ['silverstripe', 'silverstripe-assets'],
        // ];

        foreach ($varsList as $vars) {
            list($account, $repo) = $vars;
            $moduleName = "{$account}/{$repo}";
            $path = "/repos/{$account}/{$repo}/tags";
            $json = $this->getRequestor()->fetch($path, '', $account, $repo, $this->refetch);
            $tagList = $this->deriveTagList($json->root ?? []);

            $row = [
                'account' => $account,
                'repo' => $repo,
                'tagsLink' => "https://github.com/{$account}/{$repo}/tags",

                'curr_type' => 'current-minor',
                'curr_branch' => $tagList['currMinor']['minorBranch'] ?? '',
                'curr_latestTag' => $tagList['currMinor']['tag'] ?? '',
                'curr_newTag' => '',
                'curr_changelog' => '',
                'curr_compare' => '',
                'curr_warning' => '',
                'curr_release' => '',

                'next_type' => 'next-minor',
                'next_branch' => $tagList['nextMinor']['minorBranch'] ?? '',
                'next_latestTag' => '',
                'next_newTag' => '',
                'next_changelog' => '',
                'next_compare' => '',
                'next_warning' => '',
                'next_release' => '',

                'prev_type' => 'previous-minor',
                'prev_branch' => $tagList['prevMinor']['minorBranch'] ?? '',
                'prev_latestTag' => $tagList['prevMinor']['tag'] ?? '',
                'prev_newTag' => '',
                'prev_changelog'  => '',
                'prev_compare' => '',
                'prev_warning' => '',
                'prev_release' => '',
            ];

            if (self::LAST_MAJOR_SUPPORTED) {
                $row['lastmaj_type'] = 'last-major';
                $row['lastmaj_branch'] = $tagList['lastmajMinor']['minorBranch'] ?? '';
                $row['lastmaj_latestTag'] = $tagList['lastmajMinor']['tag'] ?? '';
                $row['lastmaj_newTag'] = '';
                $row['lastmaj_changelog'] = '';
                $row['lastmaj_compare'] = '';
                $row['lastmaj_warning'] = '';
                $row['lastmaj_release'] = '';
            }

            $minorTypes = ['next', 'curr', 'prev'];
            if (self::LAST_MAJOR_SUPPORTED) {
                $minorTypes[] = 'lastmaj';
            }
            foreach ($minorTypes as $minorType) {
                if (!isset($tagList["{$minorType}Minor"]['minorBranch'])) {
                    continue;
                }
                $minorBranch = $tagList["{$minorType}Minor"]['minorBranch'];
                $currentTag = $tagList["{$minorType}Minor"]['tag'];

                // this will include a fetch to a /compare endpoint
                $newTagData = $this->deriveNewTagData(
                    $currentTag,
                    $moduleName,
                    $minorType
                );

                if (!$newTagData['hasUnreleasedChanges'] || $newTagData['devOnlyCommitsSinceLastTag']) {
                    continue;
                }

                // https://github.com/silverstripe/silverstripe-framework/releases/new
                if ($minorType == 'next') {
                    $tmp = explode('.', $this->stripTrailingVersion($currentTag));
                    $tag = implode('.', [$tmp[0], $tmp[1] - 1]);
                    $branch = $this->stripTrailingVersion($minorBranch);
                } else {
                    $tag = $currentTag;
                    $branch = $minorBranch;
                }
                $compareLink = "https://github.com/{$account}/{$repo}/compare/{$tag}...{$branch}";
                $buildBadge = $this->buildStatusBadge($account, $repo, $branch);
                $newTag = $newTagData['newTag'];
                if ($minorType == 'next') {
                    $newTag = $newTagData['changelog'] ? $tagList['nextMinor']['tag'] ?? '' : '';
                }
                $row["{$minorType}_newTag"] = $newTag;
                $row["{$minorType}_changelog"] = $newTagData['changelog'];
                if ($newTag) {
                    $row["{$minorType}_compare"] = $compareLink;
                    $row["{$minorType}_travis"] = $buildBadge;
                    $row["{$minorType}_warning"] = "Count non-merge commits in compare before release";
                    $row["{$minorType}_release"] = "https://github.com/{$account}/{$repo}/releases/new";
                }
            }
            $rows[] = $row;
        }
        // split minorTypes into seperate rows
        $newRows = [];
        $minorTypes = ['next', 'curr', 'prev'];
        if (self::LAST_MAJOR_SUPPORTED) {
            $minorTypes[] = 'lastmaj';
        }
        $rx = '#^' . implode('_|', $minorTypes) . '_#';
        foreach ($rows as $row) {
            foreach ($minorTypes as $minorType) {
                $newRow = [];
                foreach (array_keys($row) as $key) {
                    if (preg_match($rx, $key) && strpos($key, $minorType) !== 0) {
                        continue;
                    }
                    $newKey = preg_replace($rx, '', $key);
                    $newRow[$newKey] = $row[$key];
                }
                if (empty($newRow['branch'])) {
                    continue;
                }
                $newRows[] = $newRow;
            }
        }
        return $newRows;
    }

    private function deriveTagList($gitTagsJson): array
    {
        $ret = [
            // 4.7.3 (.2 + .1)
            'nextMinor' => [],
            // 4.7.2
            'currMinor' => [], // [tag => 4.7.1, minor => 4.7, patch => 1, sha => abcdfe]
            // 4.6.3
            'prevMinor' => [],
            // 3.7.1
            'lastmajMinor' => []
        ];
        $objs = [];
        $minorBranches = [];
        $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)$#';
        foreach ($gitTagsJson as $tagObj) {
            if (!preg_match($rx, $tagObj->name, $m)) {
                continue;
            }
            $minorBranch = $m[1] . '.' . $m[2];
            $patch = $m[3];
            $minorBranches[] = $minorBranch;
            $objs[] = [
                'tag' => $tagObj->name,
                'minorBranch' => $minorBranch,
                'patch' => $patch,
                'sha' => $tagObj->commit->sha
            ];
        }
        $minorBranches = array_unique($minorBranches);
        usort($minorBranches, function($a, $b) {
            $tmpa = explode('.', $a);
            $tmpb = explode('.', $b);
            if ($tmpa[0] == $tmpb[0]) {
                return $tmpa[1] <=> $tmpb[1];
            }
            return $tmpa[0] <=> $tmpb[0];
        });
        $minorBranches = array_reverse($minorBranches);
        $minorBranchCurrentPatch = [];
        foreach ($minorBranches as $minorBranch) {
            $patches = [];
            foreach ($objs as $obj) {
                if ($obj['minorBranch'] !== $minorBranch) {
                    continue;
                }
                $patches[] = $obj['patch'];
            }
            sort($patches);
            $patches = array_reverse($patches);
            $currentPatch = $patches[0] ?? '';
            $minorBranchCurrentPatch[$minorBranch] = $currentPatch;
        }
        $major = '';
        if (count($minorBranches) >= 1) {
            $minorBranch = $minorBranches[0];
            $major = $minorBranch[0];
            $patch = $minorBranchCurrentPatch[$minorBranch];
            $ret['currMinor'] = $this->filterTagListObjs($objs, $minorBranch, $patch);
            $ret['currMinor']['type'] = 'curr';
            if (!empty($ret['currMinor'])) {
                $next = array_merge($ret['currMinor']);
                $tmp = explode('.', $next['minorBranch']);
                $tmp[1] = (int)$tmp[1] + 1;
                $next['minorBranch'] = implode('.', [$tmp[0], $tmp[1]]);
                $next['tag'] = $next['minorBranch'] . '.0';
                $next['patch'] = 0;
                $next['sha'] = '';
                $next['type'] = 'next';
                $ret['nextMinor'] = $next;
            }
        }
        if (count($minorBranches) >= 2) {
            $minorBranch = $minorBranches[1];
            if ($minorBranch[0] == $major) {
                $patch = $minorBranchCurrentPatch[$minorBranch];
                $ret['prevMinor'] = $this->filterTagListObjs($objs, $minorBranch, $patch);
                $ret['prevMinor']['type'] = 'prev';
            }
        }
        if (self::LAST_MAJOR_SUPPORTED) {
            foreach ($minorBranches as $minorBranch) {
                if ($minorBranch[0] == $major - 1) {
                    $ret['lastmajMinor'] = $this->filterTagListObjs($objs, $minorBranch);
                    $ret['lastmajMinor']['type'] = 'lastmaj';
                    break;
                }
            }
        }
        return $ret;
    }

    private function filterTagListObjs(array $objs, string $minorBranch, ?string $patch = null): array
    {
        $arr = array_filter($objs, function($obj) use ($minorBranch, $patch) {
            if ($patch === null) {
                return $obj['minorBranch'] === $minorBranch;
            }
            return $obj['minorBranch'] === $minorBranch && $obj['patch'] === $patch;
        });
        // reset index
        $arr = array_merge($arr);
        return $arr[0] ?? [];
    }

    private function deriveNewTagData(
        string $currentTag,
        string $moduleName,
        string $minorType // next | curr | prev | lastmaj
    ): array
    {
        $data = [
            'hasUnreleasedChanges' => false,
            'newTag' => 'unknown',
            'devOnlyCommitsSinceLastTag' => false,
            'changelog' => '',
            'minorBranch' => '',
        ];

        // get branch info from tag
        $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)$#';
        if (!preg_match($rx, $currentTag, $m)) {
            return $data;
        }
        array_shift($m);
        list($currentMajor, $currentMinor, $currentPatch) = $m;
        $minorBranch = "{$currentMajor}.{$currentMinor}";

        // derive account and repo
        preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $moduleName, $m);
        array_shift($m);
        list($account, $repo) = $m;

        // derive branch and tag
        $branch = $minorBranch;
        $tag = $currentTag;
        if ($minorType == 'next') {
            $tmp = explode('.', $this->stripTrailingVersion($currentTag));
            $tag = implode('.', [$tmp[0], $tmp[1] - 1]);
            $branch = $this->stripTrailingVersion($minorBranch);
        }

        $path = "/repos/{$account}/{$repo}/compare/{$tag}...{$branch}";
        $json = $this->getRequestor()->fetch($path, '', $account, $repo, $this->refetch);
        if (empty($json->root->files ?? [])) {
            return $data;
        }

        $data['hasUnreleasedChanges'] = true;

        $data['devOnlyCommitsSinceLastTag'] = true;
        foreach ($json->root->files as $file) {
            if (!$this->isDevFile($file)) {
                $data['devOnlyCommitsSinceLastTag'] = false;
                break;
            }
        }

        // derive newTag
        $newMajor = $currentMajor;
        $newMinor = $currentMinor;
        $newPatch = $currentPatch;

        if ($minorType == 'next') {
            // $newMinor = $currentMinor += 1;
            $newPatch = 0;
        } else {
            $newPatch = $currentPatch += 1;
        }
        $data['newTag'] = "{$newMajor}.{$newMinor}.{$newPatch}";

        // derive changelog
        // To release 3.0.1
        // git log --oneline --pretty=format:"* %s (%an) - %h" --no-merges 3.0.0...3.0
        $arr = [];
        foreach ($json->root->commits as $commit) {
            // Using ?? on author because I'm not sure what happens if author deleted from github
            $commitMessage = sprintf(
                '* %s (%s) - %s',
                explode("\n", trim($commit->commit->message))[0],
                $commit->commit->author->name ?? '',
                substr($commit->sha, 0, 9)
            );
            if (
                strpos($commitMessage, '* Merge pull request #') === 0 ||
                strpos($commitMessage, "* Merge branch '") === 0
            ) {
                continue;
            }
            // $iso8601 format
            $dtStr = $commit->commit->author->date ?? '1980-01-01T00:00:00Z';
            $dt = DateTimeUtil::parseTimestamp($dtStr);
            $ts = $dt->getTimestamp();
            $arr[$commitMessage] = $ts;
        }
        asort($arr);
        $arr = array_reverse($arr);
        $data['changelog'] = implode("\n", array_keys($arr));

        // TODO: hardcoded
        if (preg_match('#(?s)^\* Update build status badge \(Steve Boyd\) - [a-z0-9]{9}$#', str_replace("\n", '', $data['changelog']))) {
            $data['changelog'] = '';
        }
        return $data;
    }

    // TODO: Put this into PullRequestUtil
    private function isDevFile(stdClass $file): bool
    {
        // possiblly should treat .travis.yml and .scrutinizer as 'tooling'
        $path = $file->filename;
        $paths = [
            '.codeclimate.yml',
            '.codecov.yml',
            '.cow.json',
            '.editorconfig',
            '.eslintignore',
            '.eslintrc',
            '.eslintrc.js',
            '.gitattributes',
            '.gitignore',
            '.nvmrc',
            '.sass-lint.yml',
            '.scrutinizer.yml',
            '.ss-strorybook.js',
            '.travis.yml',
            'behat.yml',
            'code-of-conduct.md',
            'contributing.md',
            'CONTRIBUTING.md',
            'composer.lock',
            'package.json',
            'phpcs.xml',
            'phpcs.xml.dist',
            'phpunit.xml',
            'phpunit.xml.dist',
            'SUPPORT.md',
            'webpack.config.js',
            'yarn.lock',
        ];
        $devComposerDeps = [
            'phpunit/phpunit',
            'sminnee/phpunit',
            'squizlabs/php_codesniffer',
        ];
        if (in_array($path, $paths)) {
            return true;
        }
        if ($path == 'composer.json') {
            // check if all additions + removals are in require-dev
            preg_match_all("#\n([\+\-].+)#", $file->patch, $m1);
            $changeCount = count($m1[1] ?: []);
            $deps = implode('|', $devComposerDeps);
            preg_match_all("#^[\+\-] *\"({$deps})\"#", $file->patch, $m2);
            $devDepsCount = count(array_filter($m2) ?: []);
            return $changeCount == $devDepsCount;
        }
        return false;
    }

    // 4.7.0 => 4.7
    // 4.7 => 4
    private function stripTrailingVersion($branch): string
    {
        return preg_replace('#\.[0-9]+$#', '', $branch);
    }
}
