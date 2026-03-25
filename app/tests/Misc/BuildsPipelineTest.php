<?php

namespace App\Tests\Misc;

use SilverStripe\Dev\SapphireTest;

class BuildsPipelineTest extends SapphireTest
{
    public function testRunBuildsBeforeCmsBuilds(): void
    {
        $pipeline = new TestBuildsPipeline();
        $pipeline->run(true);

        $this->assertSame([
            ['run', 'builds', true],
            ['prime', true],
            ['run', 'cms-builds', false],
        ], $pipeline->events);
    }
}
