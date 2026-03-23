<?php

namespace App\Misc;

use App\DataFetcher\Apis\GitHubApiConfig;
use App\DataFetcher\Requesters\AbstractRequester;
use App\DataFetcher\Requesters\RestRequester;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use SilverStripe\SupportedModules\MetaData;

/**
 * Loads CMS roadmap and supported-modules metadata to determine which CMS versions are
 * shown in Rhino, and maps each shown version to its corresponding module branches.
 */
class CmsBuildsManager
{
    private const STATUS_FULL = 'Full support';

    private const STATUS_PARTIAL = 'Partial support';

    private const STATUS_EOL = 'End of life';

    private const STATUS_PRE = 'Pre-release';

    private const STATUS_DEV = 'In development';

    private const STATUS_PLANNED = 'Planned';

    private const STATUS_RELEASED = 'Released';

    private const STATUS_NOT_SHOWN = 'Not shown';

    private const STATUS_INVALID = 'Invalid Dates';

    private const STATUS_UNKNOWN = 'Unknown';

    private const VISIBLE_STATUSES = [
        self::STATUS_FULL,
        self::STATUS_PARTIAL,
        self::STATUS_PRE,
        self::STATUS_DEV,
        self::STATUS_PLANNED,
        self::STATUS_RELEASED,
    ];

    private const MAJOR_VISIBLE_STATUSES = [
        self::STATUS_FULL,
        self::STATUS_PARTIAL,
        self::STATUS_PRE,
        self::STATUS_DEV,
        self::STATUS_RELEASED,
    ];

    private const RELEASED_MINOR_VISIBLE_STATUSES = [
        self::STATUS_FULL,
        self::STATUS_PARTIAL,
        self::STATUS_RELEASED,
    ];

    private const GHA_GENERATE_MATRIX_ACCOUNT = 'silverstripe';

    private const GHA_GENERATE_MATRIX_REPO = 'gha-generate-matrix';

    private const GHA_GENERATE_MATRIX_BRANCH = '2';

    private const ROADMAP_ACCOUNT = 'silverstripe';

    private const ROADMAP_REPO = 'roadmap';

    private const ROADMAP_BRANCH = 'main';

    private array $installerToRepoMinorVersions = [];

    private array $cmsMajorToVersions = [];

    /**
     * @var array<string, array<string, string[]>> repo => [cmsMajor => moduleMajors[]]
     */
    private array $locksteppedRepos = [];

    private bool $isLoaded = false;

    /**
     * Fetches and caches the API data needed for CMS build processing.
     */
    public function primeApiData(bool $refetch): void
    {
        $this->load($this->createRequester(), $refetch);
    }

    /**
     * Fetches consts.php and roadmap data.json, then combines them with supported-modules metadata.
     */
    public function load(AbstractRequester $requester, bool $refetch): void
    {
        if ($this->isLoaded) {
            return;
        }

        $constsPhp = $requester->fetchFile(
            self::GHA_GENERATE_MATRIX_ACCOUNT,
            self::GHA_GENERATE_MATRIX_REPO,
            self::GHA_GENERATE_MATRIX_BRANCH,
            'consts.php',
            $refetch
        );
        if (!is_string($constsPhp) || $constsPhp === '') {
            throw new LogicException('Unable to fetch gha-generate-matrix consts.php');
        }

        $roadmapJson = $requester->fetchFile(
            self::ROADMAP_ACCOUNT,
            self::ROADMAP_REPO,
            self::ROADMAP_BRANCH,
            'data.json',
            $refetch
        );
        if (!is_object($roadmapJson) || !is_array($roadmapJson->data ?? null)) {
            throw new LogicException('Unable to fetch roadmap data.json');
        }

        $this->installerToRepoMinorVersions = $this->parseInstallerToRepoMinorVersions($constsPhp);
        $this->cmsMajorToVersions = $this->deriveVisibleCmsVersions($roadmapJson->data);
        $this->locksteppedRepos = $this->loadLocksteppedRepos($refetch);
        $this->isLoaded = true;
    }

    /**
     * Returns the major version strings (e.g. ['6', '5']) currently shown in Rhino.
     */
    public function getVisibleCmsMajors(): array
    {
        return array_map('strval', array_keys($this->cmsMajorToVersions));
    }

    /**
     * Returns the minor versions shown in Rhino for a given CMS major (e.g. ['6.4', '6.3'] for '6').
     */
    public function getVisibleCmsVersionsForMajor(string $cmsMajor): array
    {
        return $this->cmsMajorToVersions[$cmsMajor] ?? [];
    }

    /**
     * Returns the names of all repos that appear in any CMS version shown in Rhino.
     */
    public function getVisibleRepoNames(): array
    {
        $repos = [];
        foreach ($this->cmsMajorToVersions as $versions) {
            foreach ($versions as $version) {
                foreach (array_keys($this->installerToRepoMinorVersions[$version] ?? []) as $repo) {
                    $repos[$repo] = true;
                }
            }
        }
        // Lockstepped repos are visible for any CMS major they have a mapping for
        foreach (array_keys($this->cmsMajorToVersions) as $cmsMajor) {
            foreach ($this->locksteppedRepos as $repo => $mapping) {
                if (isset($mapping[$cmsMajor])) {
                    $repos[$repo] = true;
                }
            }
        }
        return array_keys($repos);
    }

    /**
     * Returns the module minor branches that correspond to the given CMS version for a repo.
     */
    public function getMappedMinorBranches(string $repo, string $cmsVersion): array
    {
        // Lockstepped repos share minor version numbers with the CMS installer,
        // e.g. CMS 6.1 => silverstripe-admin 3.1 (using supported-modules majorVersionMapping metadata)
        if (isset($this->locksteppedRepos[$repo])) {
            [$cmsMajor, $cmsMinor] = array_pad(explode('.', $cmsVersion, 2), 2, '');
            $moduleMajors = $this->locksteppedRepos[$repo][$cmsMajor] ?? [];
            return array_map(fn(string $major) => "$major.$cmsMinor", $moduleMajors);
        }
        return $this->normaliseVersionList($this->installerToRepoMinorVersions[$cmsVersion][$repo] ?? null);
    }

    /**
     * Returns the module major branches that correspond to the given CMS major for a repo.
     */
    public function getMappedMajorBranches(string $repo, string $cmsMajor): array
    {
        $branches = [];
        foreach ($this->getVisibleCmsVersionsForMajor($cmsMajor) as $cmsVersion) {
            foreach ($this->getMappedMinorBranches($repo, $cmsVersion) as $minorBranch) {
                $majorBranch = explode('.', $minorBranch)[0] ?? '';
                if ($majorBranch !== '') {
                    $branches[$majorBranch] = $majorBranch;
                }
            }
        }
        $branchList = array_values($branches);
        usort($branchList, function (string $a, string $b): int {
            return version_compare($b, $a);
        });
        return $branchList;
    }

    /**
     * Derives the CMS versions shown in Rhino from roadmap status data, including majors that are
     * stable/released or in development, rather than relying only on the highest stable major.
     */
    private function deriveVisibleCmsVersions(array $roadmapData): array
    {
        $visibleMajors = [];
        $releasedMinorVersions = [];
        $futureMinorVersions = [];
        $foundInDevVersions = [];
        $currentDateNZT = $this->getCurrentDateNZT();

        foreach ($roadmapData as $record) {
            if (!is_object($record) || !is_string($record->version ?? null)) {
                continue;
            }

            $version = $record->version;
            if (!isset($this->installerToRepoMinorVersions[$version])) {
                continue;
            }

            $majorVersion = explode('.', $version)[0] ?? '';
            if ($majorVersion === '') {
                continue;
            }

            $status = $this->deriveStatus($record, $currentDateNZT, $majorVersion, $foundInDevVersions);
            if (!in_array($status, self::VISIBLE_STATUSES, true)) {
                continue;
            }

            if (in_array($status, self::MAJOR_VISIBLE_STATUSES, true)) {
                $visibleMajors[$majorVersion] = true;
            }

            if (in_array($status, self::RELEASED_MINOR_VISIBLE_STATUSES, true)) {
                $releasedMinorVersions[$majorVersion] ??= [];
                $releasedMinorVersions[$majorVersion][] = $version;
                continue;
            }

            $futureMinorVersions[$majorVersion] ??= [];
            $futureMinorVersions[$majorVersion][] = $version;
        }

        $majorVersions = array_keys($visibleMajors);
        usort($majorVersions, function (string $a, string $b): int {
            return version_compare($b, $a);
        });
        $filtered = [];
        foreach ($majorVersions as $majorVersion) {
            $versions = $futureMinorVersions[$majorVersion] ?? [];
            $latestReleasedVersion = $this->getLatestVersion($releasedMinorVersions[$majorVersion] ?? []);
            if ($latestReleasedVersion !== '') {
                $versions[] = $latestReleasedVersion;
            }
            $versions = $this->sortVersionsDescending($versions);
            if ($versions !== []) {
                $filtered[$majorVersion] = $versions;
            }
        }

        return $filtered;
    }

    /**
     * Determines the support status of a single roadmap record based on its dates and the current date.
     */
    private function deriveStatus(
        object $record,
        DateTimeImmutable $currentDateNZT,
        string $majorVersion,
        array &$foundInDevVersions
    ): string {
        $statusOverride = is_string($record->statusOverride ?? null) ? $record->statusOverride : '';
        if ($statusOverride !== '') {
            return $statusOverride;
        }

        $releaseDateStr = is_string($record->releaseDate ?? null) ? $record->releaseDate : '';
        $partialSupportStr = is_string($record->partialSupport ?? null) ? $record->partialSupport : '';
        $supportEndsStr = is_string($record->supportEnds ?? null) ? $record->supportEnds : '';
        if ($releaseDateStr === '' || $partialSupportStr === '' || $supportEndsStr === '') {
            return self::STATUS_INVALID;
        }

        $releaseDate = $this->createDate($releaseDateStr);
        $partialSupportDate = $this->createDate($partialSupportStr);
        $endOfLifeDate = $this->createDate($supportEndsStr);

        if ($currentDateNZT >= $releaseDate && $currentDateNZT < $partialSupportDate) {
            return self::STATUS_FULL;
        }
        if ($currentDateNZT >= $partialSupportDate && $currentDateNZT < $endOfLifeDate) {
            return self::STATUS_PARTIAL;
        }
        if ($currentDateNZT >= $endOfLifeDate) {
            $twelveMonthsAfterEol = $endOfLifeDate->add(new DateInterval('P1Y'));
            if ($currentDateNZT >= $twelveMonthsAfterEol) {
                return self::STATUS_NOT_SHOWN;
            }
            return self::STATUS_EOL;
        }
        if ($currentDateNZT < $endOfLifeDate) {
            $twelveMonthsBeforeRelease = $releaseDate->sub(new DateInterval('P1Y'));
            if ($currentDateNZT >= $twelveMonthsBeforeRelease) {
                if (!array_key_exists($majorVersion, $foundInDevVersions)) {
                    $foundInDevVersions[$majorVersion] = false;
                }
                if ($foundInDevVersions[$majorVersion] === false) {
                    $foundInDevVersions[$majorVersion] = true;
                    return self::STATUS_DEV;
                }
            }
            return self::STATUS_PLANNED;
        }
        return self::STATUS_UNKNOWN;
    }

    /**
     * Creates the GitHub REST requester used to fetch remote files.
     */
    private function createRequester(): RestRequester
    {
        $apiConfig = new GitHubApiConfig();
        return new RestRequester($apiConfig);
    }

    /**
     * Returns the current date in NZT, used to evaluate CMS version support windows.
     */
    protected function getCurrentDateNZT(): DateTimeImmutable
    {
        $timezone = new DateTimeZone('Pacific/Auckland');
        $dateString = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        return new DateTimeImmutable($dateString, $timezone);
    }

    /**
     * Returns the highest version from a list, or an empty string if the list is empty.
     */
    private function getLatestVersion(array $versions): string
    {
        $versionList = $this->sortVersionsDescending($versions);
        return $versionList[0] ?? '';
    }

    /**
     * Parses a roadmap date string (YYYY-MM-DD or YYYY-MM) into a DateTimeImmutable in NZT.
     */
    private function createDate(string $dateStr): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return new DateTimeImmutable($dateStr, new DateTimeZone('Pacific/Auckland'));
        }

        if (!preg_match('/^(\d{4})-(\d{2})$/', $dateStr, $matches)) {
            throw new LogicException("Invalid roadmap date: {$dateStr}");
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d', $year, $month, $lastDay),
            new DateTimeZone('Pacific/Auckland')
        );
    }

    /**
     * Loads the lockstepped repo -> CMS major -> module major mapping from supported-modules metadata.
     *
     * @return array<string, array<string, string[]>>
     */
    protected function loadLocksteppedRepos(bool $refetch): array
    {
        $result = [];
        $allItems = MetaData::getAllRepositoryMetaData(false, $refetch);
        foreach ($allItems as $item) {
            if (!is_array($item) || !($item['lockstepped'] ?? false)) {
                continue;
            }
            $github = is_string($item['github'] ?? null) ? $item['github'] : '';
            if ($github === '' || !str_contains($github, '/')) {
                continue;
            }
            [, $repo] = explode('/', $github, 2);
            $rawMapping = is_array($item['majorVersionMapping'] ?? null) ? $item['majorVersionMapping'] : [];
            if (empty($rawMapping)) {
                continue;
            }
            $mapping = [];
            foreach ($rawMapping as $cmsMajor => $moduleMajors) {
                $mapping[(string) $cmsMajor] = array_values(array_filter(
                    array_map('strval', (array) $moduleMajors),
                    fn (string $major): bool => $major !== ''
                ));
            }
            if ($mapping === []) {
                continue;
            }
            $result[$repo] = $mapping;
        }
        return $result;
    }

    /**
     * Tokenises consts.php and extracts the INSTALLER_TO_REPO_MINOR_VERSIONS array value.
     */
    private function parseInstallerToRepoMinorVersions(string $constsPhp): array
    {
        $tokens = token_get_all($constsPhp);
        $numTokens = count($tokens);

        for ($index = 0; $index < $numTokens; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || $token[0] !== T_CONST) {
                continue;
            }

            $index++;
            $this->skipIgnorableTokens($tokens, $index);
            $token = $tokens[$index] ?? null;
            if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== 'INSTALLER_TO_REPO_MINOR_VERSIONS') {
                continue;
            }

            $index++;
            $this->skipIgnorableTokens($tokens, $index);
            if (($tokens[$index] ?? null) !== '=') {
                throw new LogicException('INSTALLER_TO_REPO_MINOR_VERSIONS is missing an assignment operator');
            }

            $index++;
            $value = $this->parsePhpValue($tokens, $index);
            if (!is_array($value)) {
                throw new LogicException('INSTALLER_TO_REPO_MINOR_VERSIONS is not an array');
            }
            return $value;
        }

        throw new LogicException('Could not find INSTALLER_TO_REPO_MINOR_VERSIONS in consts.php');
    }

    /**
     * Recursively parses a PHP value (array, string, or integer) from a token stream.
     */
    private function parsePhpValue(array $tokens, int &$index): mixed
    {
        $this->skipIgnorableTokens($tokens, $index);
        $token = $tokens[$index] ?? null;

        if ($token === '[') {
            return $this->parsePhpArray($tokens, $index);
        }

        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $index++;
            return $this->decodePhpStringLiteral($token[1]);
        }

        if (is_array($token) && $token[0] === T_LNUMBER) {
            $index++;
            return $token[1];
        }

        throw new LogicException('Unexpected token while parsing consts.php');
    }

    /**
     * Parses a PHP array literal from a token stream, returning it as a PHP array.
     */
    private function parsePhpArray(array $tokens, int &$index): array
    {
        if (($tokens[$index] ?? null) !== '[') {
            throw new LogicException('Expected array opening bracket');
        }

        $index++;
        $result = [];
        while (true) {
            $this->skipIgnorableTokens($tokens, $index);
            $token = $tokens[$index] ?? null;

            if ($token === ']') {
                $index++;
                return $result;
            }

            $keyOrValue = $this->parsePhpValue($tokens, $index);
            $this->skipIgnorableTokens($tokens, $index);
            $token = $tokens[$index] ?? null;

            if (is_array($token) && $token[0] === T_DOUBLE_ARROW) {
                $index++;
                $value = $this->parsePhpValue($tokens, $index);
                $result[(string) $keyOrValue] = $value;
            } else {
                $result[] = $keyOrValue;
            }

            $this->skipIgnorableTokens($tokens, $index);
            $token = $tokens[$index] ?? null;
            if ($token === ',') {
                $index++;
                continue;
            }
            if ($token === ']') {
                $index++;
                return $result;
            }
            throw new LogicException('Expected comma or closing bracket while parsing consts.php');
        }
    }

    /**
     * Advances the token index past any whitespace, comment, or doc-comment tokens.
     */
    private function skipIgnorableTokens(array $tokens, int &$index): void
    {
        $numTokens = count($tokens);
        while ($index < $numTokens) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                return;
            }
            if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return;
            }
            $index++;
        }
    }

    /**
     * Decodes a PHP string literal token (single- or double-quoted) to its runtime string value.
     */
    private function decodePhpStringLiteral(string $literal): string
    {
        $quote = substr($literal, 0, 1);
        $value = substr($literal, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }
        return stripcslashes($value);
    }

    /**
     * Normalises a version value that may be a string, array of strings, or null into a sorted array.
     */
    private function normaliseVersionList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $versions = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $versions[$item] = true;
            }
        }
        return $this->sortVersionsDescending(array_keys($versions));
    }

    /**
     * Sorts a list of version strings in descending order (newest first), deduplicating as it goes.
     */
    private function sortVersionsDescending(array $versions): array
    {
        $versionList = array_values(array_unique(array_filter($versions, 'is_string')));
        usort($versionList, function (string $a, string $b): int {
            return version_compare($b, $a);
        });
        return $versionList;
    }
}
