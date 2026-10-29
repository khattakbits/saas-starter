<?php

namespace App\Core\Support;

use Illuminate\Database\Schema\Blueprint;

final class DocumentColumns
{
    public static function add(Blueprint $table): void
    {
        $table->string('name', 60)->unique();
        $table->unsignedTinyInteger('docstatus')->default(0)->index();
        $table->unsignedBigInteger('amended_from')->nullable()->index();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->softDeletes();
        $table->index('deleted_at');
    }
}
