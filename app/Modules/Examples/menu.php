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
    // Module apni email templates bhi register kar sakta hai (defaults code mein rehte hain)
    'email_templates' => [
        'demo_entry_submitted' => [
            'name' => 'Demo entry submitted',
            'subject' => '{{ app_name }}: {{ entry_name }} was submitted',
            'body' => "Hi {{ user_name }},\n\n{{ entry_name }} was submitted by {{ user_name }}.\n\nYou are receiving this because you follow the Examples module.",
            'placeholders' => [
                'app_name' => 'Workspace name from the settings',
                'user_name' => 'Name of the person receiving the email',
                'entry_name' => 'Name of the demo entry, e.g. DEMO-00001',
            ],
            'required' => ['entry_name'],
            'samples' => [
                'user_name' => 'Ayesha Khan',
                'entry_name' => 'DEMO-00001',
            ],
        ],
    ],
    'sections' => [
        [
            'title' => 'Doctypes',
            'items' => [
                ['label' => 'Demo Entries', 'path' => '/examples/entries', 'can' => 'examples.entries.view'],
            ],
        ],
        [
            'title' => 'Page types',
            'items' => [
                ['label' => 'List', 'path' => '/examples/list', 'can' => null],
                ['label' => 'Report', 'path' => '/examples/report', 'can' => null],
                ['label' => 'New form', 'path' => '/examples/new-form', 'can' => null],
                ['label' => 'Submitted form', 'path' => '/examples/submitted', 'can' => null],
                ['label' => 'Settings', 'path' => '/examples/settings', 'can' => null],
                ['label' => 'Empty states', 'path' => '/examples/empty', 'can' => null],
            ],
        ],
    ],
];
