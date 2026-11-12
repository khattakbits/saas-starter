<?php

use App\Modules\Examples\Models\DemoEntry;

return [
    'max_kb' => 10240,                    // 10 MB per file
    'max_files_per_record' => 50,
    'max_mb_per_user_per_day' => 200,
    'extensions' => [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'xls',
        'xlsx',
        'csv',
        'mp3',
        'm4a',
        'wav',
        'ogg',
        'oga',
        'opus',
        'aac',
        'weba',
        'webm',
    ],

    // Jis type ka key yahan nahi, wo 404. Jis action ka key nahi, wo 403.
    // Value null ka matlab "har login user" (sirf local demo ke liye).
    'types' => array_filter([
        'demo' => env('APP_ENV') === 'local'
            ? ['model' => null, 'view' => null, 'update' => null, 'delete' => null]
            : null,

        'demo_entry' => [
            'model' => DemoEntry::class,
            'view' => 'examples.entries.view',
            'update' => 'examples.entries.update',
            'delete' => 'examples.entries.update',
            // Record-level scope: sirf record ka owner (created_by) ya manage permission
            // wala user attachment dekh/saka aur delete kar sakta hai (B-1 fix).
            'scope' => 'owner',
            'owner_column' => 'created_by',
            'manage' => 'examples.entries.update',
        ],

        // Asli doctype ka namuna (key model ke snake_case naam se milni chahiye, ya attachmentType() override karein):
        // 'invoice' => [
        //     'model'  => App\Modules\Sales\Models\Invoice::class,
        //     'view'   => 'sales.invoices.view',
        //     'update' => 'sales.invoices.update',
        //     'delete' => 'sales.invoices.update',
        // ],
    ]),
];
