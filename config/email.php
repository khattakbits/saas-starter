<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private mail hosts
    |--------------------------------------------------------------------------
    |
    | Production mein private ranges (10/8, 172.16/12, 192.168/16) se SMTP
    | block hota hai, jab tak ye flag true na ho. Dev/test mein ye check
    | chalta hi nahi, is liye local mail catchers (Mailhog) chal jate hain.
    |
    */

    'allow_private_hosts' => filter_var(env('EMAIL_ALLOW_PRIVATE_HOSTS', true), FILTER_VALIDATE_BOOLEAN),

];
