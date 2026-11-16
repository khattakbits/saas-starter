<?php

use Illuminate\Support\Facades\Route;

/*
| Route registration **`path`** par hota hai, `href` par nahi (`03` D3.2, `07` §5.3).
|
| `path` static hai (slug-free route URI); `href` runtime hai (sidebar link, tenancy on
| hone par slug ke saath). Agar yahan `href` use karein to slug route registration mein
| bake ho jayega — tenant A ki routes banti hain, tenant B ki 404.
|
*/

$accountingDemoMenu = require __DIR__.'/menu.php';

foreach ($accountingDemoMenu['sections'] as $section) {
    foreach ($section['items'] as $item) {
        Route::inertia($item['path'], 'Examples/List');   // demo: har link wohi List page kholta hai
    }
}
