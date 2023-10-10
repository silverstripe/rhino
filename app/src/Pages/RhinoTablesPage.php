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
        // delete all existing pages
        foreach (Page::get() as $page) {
            $page->doArchive();
        }
        // create a rhino tables page as the homepage
        /** @var RhinoTablesPage|Versioned $page */
        $page = self::create();
        $page->update([
            'Title' => 'Home',
            'Content' => '<p>This page will show tables</p>',
        ]);
        $page->write();
        $page->publishRecursive();
    }
}
