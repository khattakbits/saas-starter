<?php

return [
    'max_kb' => 10240,   // 10 MB
    'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'],

    // type => attachments dekhne/lagane ke liye permission (null = har login user)
    // Asli doctype jaise 'invoice' => ['can' => 'sales.invoices.update'] yahan add honge
    'types' => [
        'demo' => ['can' => null],
    ],
];