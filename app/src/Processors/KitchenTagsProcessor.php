<?php

namespace App\Processors;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Requesters\AbstractRequester;
use App\DataFetcher\Requesters\RestRequester;
use Exception;
use stdClass;

// INCOMPLETE MIGRATION, use regular pig instead

// This was once the "pig" module used to support "cow" during quarterly silverstripe releases

class KitchenTagsProcessor extends AbstractProcessor
{
    private const SUPPORTED_ACCOUNTS = [
        'silverstripe',
        'cwp',
        'symbiote',
        'tractorcow',
        'bringyourownideas',
        'dnadesign',
        'colymba',
    ];

    // We can adopt new tags for these but don't create new tags for them:
    // This list is for a CWP release
    // TODO: ideally would read from .cow.json so that this always matches with cow
    private const UPGRADE_ONLY_MODULES = [

        // core release is done seperately before a cwp release
        'silverstripe/recipe-cms',
        'silverstripe/recipe-core',
        'silverstripe/assets',
        'silverstripe/config',
        'silverstripe/framework',
        'silverstripe/mimevalidator',
        'silverstripe/recipe-cms',
        'silverstripe/admin',
        'silverstripe/asset-admin',
        'silverstripe/campaign-admin',
        'silverstripe/versioned-admin',
        'silverstripe/cms',
        'silverstripe/errorpage',
        'silverstripe/graphql',
        'silverstripe/reports',
        'silverstripe/siteconfig',
        'silverstripe/versioned',

        # These are in .cow.json
        # https://github.com/silverstripe/cwp-recipe-kitchen-sink/blob/2/.cow.json
        "dnadesign/silverstripe-elemental-userforms",
        "silverstripe/subsites",
        "tractorcow/silverstripe-fluent",

        // manual list of loose dependencies not to release new tags for
        'silverstripe/lumberjack',
        'symbiote/silverstripe-gridfieldextensions',
        'symbiote/silverstripe-multivaluefield',
        "dnadesign/silverstripe-elemental-subsites",
        "undefinedoffset/sortablegridfield",
        "tractorcow/classproxy",
        "tractorcow/silverstripe-proxy-db",
    ];

    // applicable to cwp patch release
    // this stuff will be in a .cow.json or something, cos cow knows what to do
    private const ALWAYS_RELEASE_MODULES_WITH_RC = [
        // these get "-rc1"
        'cwp/cwp',
        'cwp/cwp-core',
        'cwp/cwp-recipe-cms',
        'cwp/cwp-recipe-core',
        'cwp/cwp-installer',
        'cwp/cwp-recipe-kitchen-sink',
        'cwp/cwp-recipe-search',
        'silverstripe/recipe-authoring-tools',
        'silverstripe/recipe-blog',
        'silverstripe/recipe-collaboration',
        'silverstripe/recipe-content-blocks',
        'silverstripe/recipe-form-building',
        'silverstripe/recipe-reporting-tools',
        'silverstripe/recipe-services',
    ];

    // not relevant for doing a release
    private const SKIP_REPOS = [
        'vendor-plugin',
        'recipe-plugin',
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
        return 'kitchen-tags';
    }

    public function getSortOrder(): int
    {
        return 8;
    }

    public function getHtmlTableScript(): string
    {
        return $this->getTravisBadgeScript();
    }

    public function process(bool $refetch): array
    {
        // Read first argument to see if doing patch|minor release
        //
        $this->refetch = $refetch;

        $releaseType = $argv[1] ?? ''; // TODO: loop

        $releaseType = 'minor';
        $data = $this->deriveData($releaseType);
        return $data;
    }

    private function deriveEndpointUrl($name, $extra)
    {
        preg_match('#^([a-zA-Z0-9\-_]+?)/([a-zA-Z0-9\-_]+)$#', $name, $m);
        array_shift($m);
        list($account, $repo) = $m;
        list($account, $repo) = $this->updateAccountRepo($account, $repo);
        $url = "/repos/$account/$repo/$extra";
        return $url;
    }

    private function updateAccountRepo($account, $repo)
    {
        if ($account == 'silverstripe') {
            if (strpos($repo, 'recipe') !== 0 && $repo != 'comment-notifications' && $repo != 'vendor-plugin') {
                $repo = 'silverstripe-' . $repo;
            }
        }
        if ($account == 'cwp') {
            $account = 'silverstripe';
            if (strpos($repo, 'cwp') !== 0) {
                $repo = 'cwp-' . $repo;
            }
            if ($repo == 'cwp-agency-extensions') {
                $repo = 'cwp-agencyextensions';
            }
        }
        if ($account == 'tractorcow' && $repo == 'silverstripe-fluent') {
            $account = 'tractorcow-farm';
        }
        return [$account, $repo];
    }

    private function getRequestor(): AbstractRequester
    {
        return $this->requestor;
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
            // check is all additions + removals are in require-dev
            preg_match_all('#^[\+\-].+$#', $file->patch, $m);
            $changeCount = count($m ?: []);
            $deps = implode('|', $devComposerDeps);
            preg_match_all("#^[\+\-] *\"({$deps})\"#", $file->patch, $m);
            $devDepsCount = count($m ?: []);
            return $changeCount != $devDepsCount;
        }
        return false;
    }

    /**
     * Get the latest available:
     * - tag released
     * - sha of the latest tag released
     */
    private function deriveLatestTagItems($gitTagsJson, $currentTag, $releaseType)
    {
        $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)(-beta1|-beta2|-beta3|-rc1|-rc2|-rc3|)$#';
        if (!preg_match($rx, $currentTag, $m)) {
            return 'unknown_current_tag';
        }
        array_shift($m);
        list($currentMajor, $currentMinor, $currentPatch, $suffix) = $m;
        // released tags are listed DESC
        foreach ($gitTagsJson as $tag) {
            if (!preg_match($rx, $tag->name, $m)) {
                continue;
            }
            array_shift($m);
            list($latestMajor, $latestMinor, $latestPatch, $suffix) = $m;

            $use = false;
            if ($releaseType == 'patch' && $currentMajor == $latestMajor && $currentMinor == $latestMinor) {
                $use = true;
            } elseif ($releaseType == 'minor' && $currentMajor == $latestMajor) {
                $use = true;
            }
            if ($use) {
                return [
                    "${latestMajor}.${latestMinor}.${latestPatch}${suffix}",
                    $tag->commit->sha
                ];
            }
        }
        return ['unknown_latest_tag', 'unknown_latest_tag_sha'];
    }

    /**
     * Derive what a new tag would be for a module depending if doing a path or minor release
     * Returns an array than includes:
     * - $hasUnreleasedChanges
     * - $newTag
     * - $latestSha
     * - $devOnlyCommitsSinceLastTag
     */
    private function deriveNewTag($gitBranchCommitsJson, $latestTag, $latestTagSha, $moduleName, $tagType)
    {
        if (!is_array($gitBranchCommitsJson)) {
            return [false, 'unknown_new_tag', 'unknown_latest_sha', 'unknown_dev_only_comments_since_last_tag'];
        }

        $latestSha = $gitBranchCommitsJson[0]->sha;

        // derive account and repo
        preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $moduleName, $m);
        array_shift($m);
        list($account, $repo) = $m;

        $path = $this->deriveEndpointUrl($moduleName, "compare/$latestTagSha...$latestSha");
        $json = $this->getRequestor()->fetch($path, '', $account, $repo, $this->refetch);

        $devOnlyCommitsSinceLastTag = true;
        foreach ($json->root->files ?? [] as $file) {
            if (!$this->isDevFile($file)) {
                $devOnlyCommitsSinceLastTag = false;
                break;
            }
        }

        $rx = '#^([0-9]+)\.([0-9]+)\.([0-9]+)(\-beta1|\-beta2|\-rc1|\-rc2|)$#';
        if (!preg_match($rx, $latestTag, $m)) {
            return [true, 'unknown_new_tag', $latestSha, $devOnlyCommitsSinceLastTag];
        }
        array_shift($m);
        list($latestMajor, $latestMinor, $latestPatch) = $m;
        $newMajor = $latestMajor;
        $newMinor = $latestMinor;
        $newPatch = $latestPatch;
        if ($tagType == 'patch') {
            $newPatch = $latestPatch += 1;
            if (in_array($moduleName, self::ALWAYS_RELEASE_MODULES_WITH_RC)) {
                $newPatch .= '-rc1';
            }
        } elseif ($tagType == 'minor') {
            $newMinor = $latestMinor += 1;
            $newPatch = 0;
            if (in_array($moduleName, self::ALWAYS_RELEASE_MODULES_WITH_RC)) {
                $newPatch .= '-rc1';
            }
        }
        return [true, "$newMajor.$newMinor.$newPatch", $latestSha, $devOnlyCommitsSinceLastTag];
    }

    private function getComposerLockJson()
    {
        $baseDir = BASE_PATH . '/installs';
        $sinkDir = "{$baseDir}/cwp-recipe-kitchen-sink";
        if (!$this->refetch && file_exists("{$sinkDir}/composer.lock")) {
            $str = file_get_contents("{$sinkDir}/composer.lock");
            return json_decode($str);
        }
        // ensure composer v2+ is installed and available
        $v = shell_exec("composer -V");
        if (!preg_match('#version [2-5]#', $v)) {
            throw new Exception('composer version 2+ required');
        }
        // prepare /installs directory for installation
        if (!file_exists($baseDir)) {
            mkdir($baseDir);
        }
        if (file_exists($sinkDir)) {
            shell_exec("rm -rf {$sinkDir}");
        }
        // create a skeleton install of kitchen sink
        $cmd = "composer create-project --no-install --no-scripts --no-plugins " .
            "--no-progress cwp/cwp-recipe-kitchen-sink {$sinkDir}";
        shell_exec($cmd);
        // generate composer.lock file
        shell_exec("cd {$sinkDir} && composer update --no-install");
        // return contents of composer.lock file
        $str = file_get_contents("{$sinkDir}/composer.lock");
        return json_decode($str);
    }

    private function filterModules($composerLockJson)
    {
        $modules = [];
        foreach ($composerLockJson->packages as $package) {
            $b = preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $package->name, $m);
            array_shift($m);
            list($account, $repo) = $m;
            if (!in_array($account, self::SUPPORTED_ACCOUNTS)) {
                continue;
            }
            if (in_array($repo, self::SKIP_REPOS)) {
                continue;
            }
            $modules[] = $package;
        }
        return $modules;
    }

    private function deriveData($releaseType)
    {
        $composerLockJson = $this->getComposerLockJson();
        $modules = $this->filterModules($composerLockJson);
        $data = [];
        foreach ($modules as $module) {
            // derive account and repo
            preg_match('#^([a-zA-Z0-9\-_]+)/([a-zA-Z0-9\-_]+)$#', $module->name, $m);
            array_shift($m);
            list($account, $repo) = $m;
            $path = $this->deriveEndpointUrl($module->name, 'tags');
            $json = $this->getRequestor()->fetch($path, '', $account, $repo, $this->refetch);
            list($latestTag, $latestTagSha) = $this->deriveLatestTagItems($json->root ?? [], $module->version, $releaseType);
            $upgradeOnly = in_array($module->name, self::UPGRADE_ONLY_MODULES);

            $row = [
                'name' => $module->name,
                'prior_tag' => $module->version,
                'tags_url' => str_replace('.git', '', $module->source->url) . '/tags',
                'latest_tag' => $latestTag,
                'upgrade_only' => $upgradeOnly
            ];

            $tagTypes = [$releaseType];
            if ($releaseType == 'minor') {
                array_unshift($tagTypes, 'patch');
            }

            $useTag = '';
            foreach ($tagTypes as $tagType) {
                $hasUnreleasedChanges = '';
                $newTag = '';
                $latestSha = '';
                $branch = '';
                $devOnlyCommitsSinceLastTag = false;
                if (!$upgradeOnly) {
                    $hasUnreleasedChanges = 'unknown_has_unreleased_changes';
                    $newTag = 'unknown_new_tag';

                    if ($tagType == 'patch') {
                        $rx = '#([0-9]+\.[0-9]+)\.[0-9]+(\-beta1|\-beta2|\-rc1|\-rc2|)#';
                    } else { // minor
                        $rx = '#([0-9])+\.[0-9]+\.[0-9]+(\-beta1|\-beta2|\-rc1|\-rc2|)#';
                    }

                    if (preg_match($rx, $latestTag, $m)) {
                        $branch = $m[1];
                        if ($tagType == 'patch') {
                            $rx = '#^0\.#';
                        } else { // minor
                            $rx = '#^0$#';
                        }
                        if (preg_match($rx, $branch)) {
                            $branch = 'master';
                        }
                        // TODO: only need a single commit with no pagination
                        $path = $this->deriveEndpointUrl($module->name, "commits?sha={$branch}&per_page=1&paginate=0");
                        $json = $this->getRequestor()->fetch($path, '', $account, $repo, $this->refetch);
                        $arr = $this->deriveNewTag($json->root, $latestTag, $latestTagSha, $module->name, $tagType);
                        list($hasUnreleasedChanges, $newTag, $latestSha, $devOnlyCommitsSinceLastTag) = $arr;
                    }
                }

                // compare url
                $compareUrl = '';
                if ($branch && $newTag) {
                    // $compareUrl = str_replace('.git', '', $module->source->url) . "/compare/$latestTagSha...$latestSha";
                    $compareUrl = str_replace('.git', '', $module->source->url) . "/compare/$latestTag...$branch";
                }

                $useTag = $latestTag;
                if ($hasUnreleasedChanges && !$upgradeOnly && !$devOnlyCommitsSinceLastTag && $newTag) {
                    $useTag = $newTag;
                }
                if (in_array($module->name, self::ALWAYS_RELEASE_MODULES_WITH_RC)) {
                    $useTag = $newTag;
                }

                $buildBadge = $this->buildBlankBadge();
                if ($branch && $newTag) {
                    list($updatedAccount, $updatedRepo) = $this->updateAccountRepo($account, $repo);
                    $buildBadge = $this->buildStatusBadge($updatedAccount, $updatedRepo, $branch);
                }

                $row[$tagType . '_has_unreleased_changes'] = $hasUnreleasedChanges;
                // $row[$tagType . '_dev_only_commits_since_last_tag'] = $devOnlyCommitsSinceLastTag;
                // (^ kind of useless, I ended up just manually checking everything anyway)
                $row[$tagType . '_new_tag'] = ($upgradeOnly || $devOnlyCommitsSinceLastTag) ? '' : $newTag;
                $row[$tagType . '_travis'] = $buildBadge;
                $row[$tagType . '_compare_url'] = $compareUrl;
            }

            // for a minor release, use_tag will default to minor
            //$row['use_tag'] = $useTag;
            // (^ better to get a human to determine this)

            $row['manual_tag_type'] = $upgradeOnly ? 'none' : ($releaseType == 'minor' && in_array($module->name, self::ALWAYS_RELEASE_MODULES_WITH_RC) ? 'minor' : ''); // patch|minor|none
            $row['cow_module'] = $module->name;
            $row['cow_new_version'] = ''; // spreadsheet function
            $row['cow_prior_version'] = $module->version;

            $data[] = $row;
        }
        return $data;
    }
}
