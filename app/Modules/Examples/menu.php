<?php

return [
    'key' => 'examples',
    'label' => 'Examples',
    'icon' => 'layers',
    'prefix' => '/examples',
    'order' => 90,
    'permissions' => [],
    'sections' => [
        [
            'title' => 'Page types',
            'items' => [
                ['label' => 'List', 'href' => '/examples/list', 'can' => null],
                ['label' => 'Report', 'href' => '/examples/report', 'can' => null],
                ['label' => 'New form', 'href' => '/examples/new-form', 'can' => null],
                ['label' => 'Submitted form', 'href' => '/examples/submitted', 'can' => null],
                ['label' => 'Settings', 'href' => '/examples/settings', 'can' => null],
                ['label' => 'Empty states', 'href' => '/examples/empty', 'can' => null],
            ]
        ],
    ],
];