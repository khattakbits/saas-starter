<?php

use Illuminate\Support\Facades\Route;

$accountingDemoMenu = require __DIR__.'/menu.php';

foreach ($accountingDemoMenu['sections'] as $section) {
    foreach ($section['items'] as $item) {
        Route::inertia($item['href'], 'Examples/List');   // demo: har link wohi List page kholta hai
    }
}
