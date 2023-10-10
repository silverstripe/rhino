<?php

namespace App\Pages;

use Page;

class RhinoTablesPage extends Page
{
    private static $description = 'Shows html tables created by rhino queuedjobs';

    public function requireDefaultRecords()
    {
        if (self::get()->count() > 0) {
            return;
        }
        /** @var RhinoTablesPage|Versioned $page */
        $page = self::create();
        $page->update([
            'Title' => 'Tables'
        ]);
        $page->write();
        $page->publishRecursive();
    }
}
