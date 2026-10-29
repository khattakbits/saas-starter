<?php

use App\Modules\Examples\Models\DemoEntry;

return [
    'key' => 'examples',
    'label' => 'Examples',
    'prefix' => '/examples',
    'order' => 90,
    'icon' => 'layers',
    'permissions' => [],
    // Is module ke documents: Draft/Submit/Cancel/Amend, Recycle Bin, permissions sab yahin se khud banti hain
    'documents' => [
        'demo_entry' => [
            'label' => 'Demo Entry',
            'model' => DemoEntry::class,
            'can' => 'examples.entries',
            'url' => '/examples/entries/{id}',
        ],
    ],
    'sections' => [
        [
            'title' => 'Doctypes',
            'items' => [
                ['label' => 'Demo Entries', 'href' => '/examples/entries', 'can' => 'examples.entries.view'],
            ],
        ],
        [
            'title' => 'Page types',
            'items' => [
                ['label' => 'List', 'href' => '/examples/list', 'can' => null],
                ['label' => 'Report', 'href' => '/examples/report', 'can' => null],
                ['label' => 'New form', 'href' => '/examples/new-form', 'can' => null],
                ['label' => 'Submitted form', 'href' => '/examples/submitted', 'can' => null],
                ['label' => 'Settings', 'href' => '/examples/settings', 'can' => null],
                ['label' => 'Empty states', 'href' => '/examples/empty', 'can' => null],
            ],
        ],
    ],
];
