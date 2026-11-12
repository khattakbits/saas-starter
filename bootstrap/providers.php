<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // KhattakBits\Core\CoreServiceProvider auto-discovered hai
    // (khattakbits/core ke composer.json ka extra.laravel.providers), is liye
    // yahan dobara register karne se wo double boot ho jata hai.
];
