<?php

namespace App\Core\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DocumentViews
{
    /**
     * Aik view ginta hai. Pehla view insert karta hai (unique index duplicate rok deta hai),
     * baad ke views aik hi atomic UPDATE se barhte hain — koi read-then-write race nahi.
     */
    public static function record(string $docType, int $docId, User $user): void
    {
        $now = now();
        $window = (int) config('documents.view_dedupe_minutes', 10);

        $inserted = DB::table('document_views')->insertOrIgnore([
            'doc_type' => $docType,
            'doc_id' => $docId,
            'user_id' => $user->id,
            'user_name' => mb_substr((string) $user->name, 0, 100),
            'views' => 1,
            'first_viewed_at' => $now,
            'last_viewed_at' => $now,
        ]);

        if ($inserted > 0) {
            return;
        }

        // Dedupe window ke andar wala view sirf last_viewed_at ko chhedta hai (count same rehta hai)
        DB::table('document_views')
            ->where('doc_type', $docType)
            ->where('doc_id', $docId)
            ->where('user_id', $user->id)
            ->where('last_viewed_at', '<=', $now->copy()->subMinutes($window))
            ->update([
                'views' => DB::raw('views + 1'),
                'last_viewed_at' => $now,
            ]);
    }
}
