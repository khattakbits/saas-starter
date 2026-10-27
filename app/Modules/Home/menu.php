<?php

return [
    'key' => 'home',
    'label' => 'Home',
    'icon' => 'home',
    'prefix' => '/',
    'order' => 0,
    'permissions' => [],   // is module ki extra permissions (menu ke 'can' ke ilawa)
    'sections' => [
        [
            'title' => 'Doctypes',
            'items' => [
                ['label' => 'Dashboard', 'href' => '/', 'can' => null],   // null = har login user
            ]
        ],
    ],
];