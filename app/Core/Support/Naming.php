<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\DB;

final class Naming
{
    public static function next(string $prefix, int $pad = 5): string
    {
        return DB::transaction(function () use ($prefix, $pad) {
            DB::table('document_sequences')->insertOrIgnore(['prefix' => $prefix, 'last' => 0]);

            $row = DB::table('document_sequences')->where('prefix', $prefix)->lockForUpdate()->first();
            $next = (int) $row->last + 1;
            DB::table('document_sequences')->where('prefix', $prefix)->update(['last' => $next]);

            return $prefix.'-'.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
        });
    }
}
