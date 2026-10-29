<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document views
    |--------------------------------------------------------------------------
    |
    | Aik hi user ka same document itni der ke andar dobara kholna naya view
    | nahi ginta (refresh/page reload se counter nahi barhta).
    |
    */

    'view_dedupe_minutes' => (int) env('DOCUMENT_VIEW_DEDUPE_MINUTES', 10),

];
