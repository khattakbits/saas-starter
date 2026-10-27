<?php

return [
    'key' => 'settings',
    'label' => 'Settings',
    'prefix' => '/settings',
    'order' => 80,
    'icon' => 'sliders',
    'permissions' => [
        'settings.users.create',
        'settings.users.update',
        'settings.roles.create',
        'settings.roles.update',
        'settings.roles.delete',
        'settings.system.update',
    ],
    'sections' => [
        [
            'title' => 'Doctypes',
            'items' => [
                ['label' => 'Users', 'href' => '/settings/users', 'can' => 'settings.users.view'],
                ['label' => 'Roles', 'href' => '/settings/roles', 'can' => 'settings.roles.view'],
            ]
        ],
        [
            'title' => 'Reports',
            'items' => [
                ['label' => 'Audit Log', 'href' => '/settings/audit-log', 'can' => 'settings.audit.view'],
            ]
        ],
        [
            'title' => 'Settings',
            'items' => [
                ['label' => 'System Settings', 'href' => '/settings/system', 'can' => 'settings.system.view'],
            ]
        ],
    ],
];