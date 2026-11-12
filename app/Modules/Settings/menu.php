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
        'settings.recycle_bin.restore',
        'settings.recycle_bin.purge',
        'settings.email_accounts.create',
        'settings.email_accounts.update',
        'settings.email_accounts.delete',
        'settings.email_templates.update',
        'settings.email.update',
    ],
    'sections' => [
        [
            'title' => 'Doctypes',
            'items' => [
                ['label' => 'Users', 'href' => '/settings/users', 'can' => 'settings.users.view'],
                ['label' => 'Roles', 'href' => '/settings/roles', 'can' => 'settings.roles.view'],
                ['label' => 'Recycle Bin', 'href' => '/settings/recycle-bin', 'can' => 'settings.recycle_bin.view'],
                ['label' => 'Email Accounts', 'href' => '/settings/email-accounts', 'can' => 'settings.email_accounts.view'],
                ['label' => 'Email Templates', 'href' => '/settings/email-templates', 'can' => 'settings.email_templates.view'],
            ],
        ],
        [
            'title' => 'Reports',
            'items' => [
                ['label' => 'Audit Log', 'href' => '/settings/audit-log', 'can' => 'settings.audit.view'],
                ['label' => 'Email Log', 'href' => '/settings/email-log', 'can' => 'settings.email_log.view'],
            ],
        ],
        [
            'title' => 'Settings',
            'items' => [
                ['label' => 'System Settings', 'href' => '/settings/system', 'can' => 'settings.system.view'],
                ['label' => 'Email Settings', 'href' => '/settings/email', 'can' => 'settings.email.view'],
            ],
        ],
    ],
];
