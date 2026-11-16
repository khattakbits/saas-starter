<?php

use Illuminate\Support\Str;

$spec = [
    'Doctypes' => [
        'Sales' => ['Sales Invoice', 'Sales Order', 'Quotation', 'Delivery Note', 'Credit Note', 'Customer'],
        'Purchases' => ['Purchase Invoice', 'Purchase Order', 'Supplier', 'Debit Note', 'Goods Receipt'],
        'Banking' => ['Payment Entry', 'Journal Entry', 'Bank Account', 'Bank Reconciliation', 'Cheque'],
        'Setup' => ['Chart of Accounts', 'Cost Center', 'Fiscal Year', 'Tax Template', 'Currency'],
    ],
    'Reports' => [
        'Ledgers' => ['General Ledger', 'Trial Balance', 'Account Statement', 'Party Ledger'],
        'Receivables' => ['Accounts Receivable', 'Receivable Aging', 'Sales Register'],
        'Payables' => ['Accounts Payable', 'Payable Aging', 'Purchase Register'],
        'Statements' => ['Profit and Loss', 'Balance Sheet', 'Cash Flow', 'Tax Summary'],
    ],
    'Settings' => [
        '' => ['Accounting Settings', 'Payment Terms'],
    ],
];

$sections = [];
foreach ($spec as $title => $groups) {
    $items = [];
    foreach ($groups as $group => $labels) {
        foreach ($labels as $label) {
            $items[] = [
                'label' => $label,
                'path' => '/accounting-demo/'.Str::slug($title).'/'.Str::slug($label),
                'can' => null,
                'group' => $group ?: null,
            ];
        }
    }
    $sections[] = ['title' => $title, 'items' => $items];
}

return [
    'key' => 'accdemo',
    'label' => 'Accounting',
    'prefix' => '/accounting-demo',
    'order' => 50,
    'icon' => 'book',
    'permissions' => [],
    'sections' => $sections,
];
