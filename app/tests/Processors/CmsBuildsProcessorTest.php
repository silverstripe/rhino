<?php

namespace App\Tests\Processors;

use App\DataFetcher\Models\ApiData;
use SilverStripe\Dev\SapphireTest;

class CmsBuildsProcessorTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        ApiData::class,
    ];

    public function testProcessOmitsSeparatorColumns(): void
    {
        $processor = new TestCmsBuildsProcessor(
            new FakeCmsBuildsManager(
                ['silverstripe-cms'],
                ['6', '5'],
                [
                    '6' => ['6.1'],
                    '5' => ['5.4'],
                ],
                [
                    'silverstripe-cms' => [
                        'major' => ['6' => ['6'], '5' => ['5']],
                        'minor' => ['6.1' => ['6.1'], '5.4' => ['5.4']],
                    ],
                ]
            ),
            [
                'silverstripe-cms' => [
                    'minorBranches' => ['6.1', '5.4'],
                    'majorBranches' => ['6', '5'],
                ],
            ]
        );

        $rows = $processor->process(false);
        $row = $rows[0];
        $headers = array_map('strval', array_keys($row));

        $this->assertSame(['account', 'repo', '6', '6.1', '5', '5.4'], $headers);
        $this->assertStringContainsString('6.x-dev', $row[6]);
        $this->assertStringContainsString('6.1.x-dev', $row['6.1']);
    }

    public function testProcessKeepsMajorAndMinorBranchesSeparate(): void
    {
        $processor = new TestCmsBuildsProcessor(
            new FakeCmsBuildsManager(
                ['silverstripe-cms'],
                ['6'],
                [
                    '6' => ['6.0'],
                ],
                [
                    'silverstripe-cms' => [
                        'major' => ['6' => ['6']],
                        'minor' => ['6.0' => ['6.0']],
                    ],
                ]
            ),
            [
                'silverstripe-cms' => [
                    'minorBranches' => ['6.0'],
                    'majorBranches' => ['6'],
                ],
            ]
        );

        $rows = $processor->process(false);
        $row = $rows[0];

        $this->assertStringContainsString('6.x-dev', $row[6]);
        $this->assertStringContainsString('6.0.x-dev', $row['6.0']);
    }

    public function testFutureMinorVersionFallsBackToLatestExistingMinorBranch(): void
    {
        $processor = new TestCmsBuildsProcessor(
            new FakeCmsBuildsManager(
                ['silverstripe-cms'],
                ['6'],
                ['6' => ['6.2', '6.1']],
                [
                    'silverstripe-cms' => [
                        'major' => ['6' => ['6']],
                        'minor' => ['6.2' => ['6.2'], '6.1' => ['6.1']],
                    ],
                ],
            ),
            [
                'silverstripe-cms' => [
                    'minorBranches' => ['6.1'],  // 6.2 doesn't exist yet
                    'majorBranches' => ['6'],
                ],
            ]
        );

        $rows = $processor->process(false);
        $row = $rows[0];

        $this->assertStringContainsString('6.1.x-dev', $row['6.2']);
        $this->assertStringContainsString('6.1.x-dev', $row['6.1']);
    }

    public function testBranchDataFetchedOnceAcrossBothProcessors(): void
    {
        $requester = new CountingRestRequester();
        $path = '/repos/silverstripe/silverstripe-admin/branches?paginate=0&per_page=100';

        // Simulate the builds run (refetch=true): fetches from API and caches in DB
        $requester->fetch($path, '', 'silverstripe', 'silverstripe-admin', true);

        // Simulate the cms-builds run (refetch=false): should use the cached DB record
        $requester->fetch($path, '', 'silverstripe', 'silverstripe-admin', false);

        $this->assertSame(1, $requester->fetchCount);
    }
}
