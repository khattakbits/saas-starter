<?php

return [
    // off | report-only | enforce. Pehle report-only par chalayen, console mein koi violation na ho to enforce
    'csp' => env('CSP_MODE', 'report-only'),
];
