<?php

namespace App\Tests\Misc;

use App\Misc\CmsBuildsManager;
use DateTimeImmutable;
use SilverStripe\Dev\SapphireTest;

class CmsBuildsManagerTest extends SapphireTest
{
    public function testVisibleCmsVersionsHideOlderReleasedMinorVersions(): void
    {
        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2026-03-23 Pacific/Auckland'));
        $manager->load(new ArrayRequester($this->getFiles()), false);

        $this->assertSame(['6.4', '6.3', '6.2', '6.1'], $manager->getVisibleCmsVersionsForMajor('6'));
    }

    public function testMappedMajorBranchesRemainDistinctFromMinorBranches(): void
    {
        $manager = new FixedDateCmsBuildsManager(
            new DateTimeImmutable('2026-03-23 Pacific/Auckland'),
            $this->getDefaultLocksteppedRepos()
        );
        $manager->load(new ArrayRequester($this->getFiles()), false);

        $this->assertSame(['3.1'], $manager->getMappedMinorBranches('silverstripe-admin', '6.1'));
        $this->assertSame(['3'], $manager->getMappedMajorBranches('silverstripe-admin', '6'));
        $this->assertSame(['6.1'], $manager->getMappedMinorBranches('silverstripe-cms', '6.1'));
        $this->assertSame(['6'], $manager->getMappedMajorBranches('silverstripe-cms', '6'));
    }

    public function testGetVisibleCmsMajors(): void
    {
        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2026-03-23 Pacific/Auckland'));
        $manager->load(new ArrayRequester($this->getFiles()), false);

        $this->assertSame(['6', '5'], $manager->getVisibleCmsMajors());
    }

    public function testGetVisibleCmsVersionsForUnknownMajorReturnsEmpty(): void
    {
        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2026-03-23 Pacific/Auckland'));
        $manager->load(new ArrayRequester($this->getFiles()), false);

        $this->assertSame([], $manager->getVisibleCmsVersionsForMajor('9'));
    }

    public function testEolVersionIsHidden(): void
    {
        // Date is between supportEnds and 12 months after supportEnds -> STATUS_EOL (not visible)
        $files = $this->makeFilesForSingleVersion('6.0', '2024-01-01', '2024-06-01', '2025-01-01');
        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2025-06-01'));
        $manager->load(new ArrayRequester($files), false);

        $this->assertSame([], $manager->getVisibleCmsMajors());
    }

    public function testVersionMoreThan12MonthsAfterEolIsHidden(): void
    {
        // Date is more than 12 months after supportEnds -> STATUS_NOT_SHOWN (not visible)
        $files = $this->makeFilesForSingleVersion('6.0', '2024-01-01', '2024-06-01', '2025-01-01');
        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2026-06-01'));
        $manager->load(new ArrayRequester($files), false);

        $this->assertSame([], $manager->getVisibleCmsMajors());
    }

    public function testStatusOverrideIsRespected(): void
    {
        // Dates would make this EOL, but statusOverride='Released' bypasses date logic
        $files = $this->makeFilesForSingleVersion('6.0', '2024-01-01', '2024-06-01', '2025-01-01', 'Released');
        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2025-06-01'));
        $manager->load(new ArrayRequester($files), false);

        $this->assertSame(['6.0'], $manager->getVisibleCmsVersionsForMajor('6'));
    }

    public function testGetVisibleRepoNamesIncludesNonLocksteppedRepos(): void
    {
        $files = [
            'consts.php' => <<<'PHP2'
<?php
const INSTALLER_TO_REPO_MINOR_VERSIONS = [
    '6.1' => ['silverstripe-admin' => '3.1', 'silverstripe-staticpublishqueue' => '7.0'],
];
PHP2,
            'data.json' => json_encode([
                'data' => [[
                    'version' => '6.1',
                    'releaseDate' => '2025-01-01',
                    'partialSupport' => '2027-01-01',
                    'supportEnds' => '2028-01-01',
                ]],
            ], JSON_THROW_ON_ERROR),
        ];

        $manager = new FixedDateCmsBuildsManager(
            new DateTimeImmutable('2026-01-01'),
            ['silverstripe-admin' => ['6' => ['3']]]
        );
        $manager->load(new ArrayRequester($files), false);

        $repos = $manager->getVisibleRepoNames();
        $this->assertContains('silverstripe-admin', $repos);
        $this->assertContains('silverstripe-staticpublishqueue', $repos);
    }

    public function testSupportedModuleMajorMappingsProvideFallbackVisibilityAndBranchMappings(): void
    {
        $manager = new FixedDateCmsBuildsManager(
            new DateTimeImmutable('2026-03-23 Pacific/Auckland'),
            $this->getDefaultLocksteppedRepos(),
            [
                'silverstripe-config' => [
                    '5' => ['2'],
                    '6' => ['3'],
                ],
                'startup-theme' => [
                    '6' => ['1'],
                ],
                'legacy-only-module' => [
                    '4' => ['9'],
                ],
            ]
        );
        $manager->load(new ArrayRequester($this->getFiles()), false);

        $repos = $manager->getVisibleRepoNames();
        $this->assertContains('silverstripe-config', $repos);
        $this->assertContains('startup-theme', $repos);
        $this->assertNotContains('legacy-only-module', $repos);

        $this->assertSame(['3'], $manager->getMappedMinorBranches('silverstripe-config', '6.1'));
        $this->assertSame(['2'], $manager->getMappedMinorBranches('silverstripe-config', '5.4'));
        $this->assertSame(['3'], $manager->getMappedMajorBranches('silverstripe-config', '6'));
        $this->assertSame(['2'], $manager->getMappedMajorBranches('silverstripe-config', '5'));
        $this->assertSame(['1'], $manager->getMappedMinorBranches('startup-theme', '6.1'));
        $this->assertSame([], $manager->getMappedMinorBranches('startup-theme', '5.4'));
    }

    public function testLoadSupportedModuleMajorMappingsExcludesNullPackagistRepos(): void
    {
        $rawItems = [
            // Has packagist — should be included
            [
                'github' => 'silverstripe/silverstripe-config',
                'packagist' => 'silverstripe/config',
                'majorVersionMapping' => ['6' => ['3'], '5' => ['2']],
            ],
            // packagist is null — should be excluded (e.g. webpack-config, eslint-config)
            [
                'github' => 'silverstripe/webpack-config',
                'packagist' => null,
                'majorVersionMapping' => ['6' => ['3'], '5' => ['2']],
            ],
            // packagist key absent — should also be excluded
            [
                'github' => 'silverstripe/eslint-config',
                'majorVersionMapping' => ['6' => ['1']],
            ],
            // hardcoded exclusion: skeleton template repo with a packagist entry
            [
                'github' => 'silverstripe/silverstripe-module',
                'packagist' => 'silverstripe-module/skeleton',
                'majorVersionMapping' => ['5' => ['5']],
            ],
        ];

        $date = new DateTimeImmutable('2026-03-23 Pacific/Auckland');
        $manager = new class ($date, $rawItems) extends CmsBuildsManager {
            public function __construct(
                private DateTimeImmutable $currentDate,
                private array $rawItems
            ) {
            }

            protected function fetchAllRepositoryMetaData(bool $refetch): array
            {
                return $this->rawItems;
            }

            protected function loadLocksteppedRepos(bool $refetch): array
            {
                return [];
            }

            protected function getCurrentDateNZT(): DateTimeImmutable
            {
                return $this->currentDate;
            }
        };
        $manager->load(new ArrayRequester($this->getFiles()), false);

        $repos = $manager->getVisibleRepoNames();
        $this->assertContains('silverstripe-config', $repos);
        $this->assertNotContains('webpack-config', $repos);
        $this->assertNotContains('eslint-config', $repos);
        $this->assertNotContains('silverstripe-module', $repos);
    }

    public function testGetMappedMinorBranchesForNonLocksteppedRepo(): void
    {
        $files = [
            'consts.php' => "<?php\nconst INSTALLER_TO_REPO_MINOR_VERSIONS = "
                . "['6.1' => ['silverstripe-staticpublishqueue' => '7.0']];",
            'data.json' => json_encode([
                'data' => [[
                    'version' => '6.1',
                    'releaseDate' => '2025-01-01',
                    'partialSupport' => '2027-01-01',
                    'supportEnds' => '2028-01-01',
                ]],
            ], JSON_THROW_ON_ERROR),
        ];

        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2026-01-01'));
        $manager->load(new ArrayRequester($files), false);

        $this->assertSame(['7.0'], $manager->getMappedMinorBranches('silverstripe-staticpublishqueue', '6.1'));
    }

    public function testGetMappedMinorBranchesWithArrayOfVersionsInConstsAreSortedDescending(): void
    {
        $files = [
            'consts.php' => "<?php\nconst INSTALLER_TO_REPO_MINOR_VERSIONS = "
                . "['6.1' => ['silverstripe-foo' => ['7.0', '7.1']]];",
            'data.json' => json_encode([
                'data' => [[
                    'version' => '6.1',
                    'releaseDate' => '2025-01-01',
                    'partialSupport' => '2027-01-01',
                    'supportEnds' => '2028-01-01',
                ]],
            ], JSON_THROW_ON_ERROR),
        ];

        $manager = new FixedDateCmsBuildsManager(new DateTimeImmutable('2026-01-01'));
        $manager->load(new ArrayRequester($files), false);

        $this->assertSame(['7.1', '7.0'], $manager->getMappedMinorBranches('silverstripe-foo', '6.1'));
    }

    public function testLocksteppedRepoWithMultipleModuleMajors(): void
    {
        $files = [
            'consts.php' => "<?php\nconst INSTALLER_TO_REPO_MINOR_VERSIONS = "
                . "['6.1' => ['silverstripe-admin' => '3.1']];",
            'data.json' => json_encode([
                'data' => [[
                    'version' => '6.1',
                    'releaseDate' => '2025-01-01',
                    'partialSupport' => '2027-01-01',
                    'supportEnds' => '2028-01-01',
                ]],
            ], JSON_THROW_ON_ERROR),
        ];

        $manager = new FixedDateCmsBuildsManager(
            new DateTimeImmutable('2026-01-01'),
            ['silverstripe-something' => ['6' => ['3', '4']]]
        );
        $manager->load(new ArrayRequester($files), false);

        $this->assertSame(['3.1', '4.1'], $manager->getMappedMinorBranches('silverstripe-something', '6.1'));
        $this->assertSame(['4', '3'], $manager->getMappedMajorBranches('silverstripe-something', '6'));
    }

    /**
     * Builds a minimal file map for a single CMS version with the given support dates.
     */
    private function makeFilesForSingleVersion(
        string $version,
        string $releaseDate,
        string $partialSupport,
        string $supportEnds,
        string $statusOverride = ''
    ): array {
        $entry = [
            'version' => $version,
            'releaseDate' => $releaseDate,
            'partialSupport' => $partialSupport,
            'supportEnds' => $supportEnds,
        ];
        if ($statusOverride !== '') {
            $entry['statusOverride'] = $statusOverride;
        }
        return [
            'consts.php' => "<?php\nconst INSTALLER_TO_REPO_MINOR_VERSIONS = "
                . "['{$version}' => ['silverstripe-admin' => '3.0']];",
            'data.json' => json_encode(['data' => [$entry]], JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Returns a full file map covering CMS 5.4 and 6.0-6.4 with realistic support dates.
     */
    private function getFiles(): array
    {
        return [
            'consts.php' => <<<'PHP2'
<?php

const INSTALLER_TO_REPO_MINOR_VERSIONS = [
    '5.4' => [
        'silverstripe-admin' => '2.4',
    ],
    '6.0' => [
        'silverstripe-admin' => '3.0',
    ],
    '6.1' => [
        'silverstripe-admin' => '3.1',
    ],
    '6.2' => [
        'silverstripe-admin' => '3.2',
    ],
    '6.3' => [
        'silverstripe-admin' => '3.3',
    ],
    '6.4' => [
        'silverstripe-admin' => '3.4',
    ],
];
PHP2,
            'data.json' => json_encode([
                'data' => [
                    [
                        'version' => '5.4',
                        'releaseDate' => '2025-04-10',
                        'partialSupport' => '2026-04-11',
                        'supportEnds' => '2027-04',
                    ],
                    [
                        'version' => '6.0',
                        'releaseDate' => '2025-06-10',
                        'partialSupport' => '2025-10-13',
                        'supportEnds' => '2026-04-14',
                    ],
                    [
                        'version' => '6.1',
                        'releaseDate' => '2025-10-13',
                        'partialSupport' => '2026-04',
                        'supportEnds' => '2026-10',
                    ],
                    [
                        'version' => '6.2',
                        'releaseDate' => '2026-04',
                        'partialSupport' => '2026-10',
                        'supportEnds' => '2027-04',
                    ],
                    [
                        'version' => '6.3',
                        'releaseDate' => '2026-10',
                        'partialSupport' => '2027-04',
                        'supportEnds' => '2027-10',
                    ],
                    [
                        'version' => '6.4',
                        'releaseDate' => '2027-04',
                        'partialSupport' => '2028-04',
                        'supportEnds' => '2029-04',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Returns the lockstepped repo mappings used by the default fixture data.
     *
     * @return array<string, array<string, string[]>>
     */
    private function getDefaultLocksteppedRepos(): array
    {
        return [
            'silverstripe-admin' => [
                '6' => ['3'],
            ],
            'silverstripe-cms' => [
                '6' => ['6'],
            ],
        ];
    }
}
