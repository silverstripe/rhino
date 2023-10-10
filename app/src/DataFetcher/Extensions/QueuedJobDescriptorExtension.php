<?php

namespace App\DataFetcher\Extensions;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extension;
use App\DataFetcher\Jobs\AbstractLoggableJob;

/**
 * Auto-creates an instance of all subclasses of AbstractLoggableJob
 */
class QueuedJobDescriptorExtension extends Extension
{
    /**
     * Called on dev/build by DatabaseAdmin
     */
    public function onAfterBuild(): void
    {
        $classes = ClassInfo::subclassesFor(AbstractLoggableJob::class, false);
        foreach ($classes as $class) {
            $class::singleton()->requireDefaultJob();
        }
    }
}
