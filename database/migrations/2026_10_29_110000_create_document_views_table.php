<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 60);
            $table->unsignedBigInteger('doc_id');
            // No FK to users (central DB) — user_name is snapshotted below.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 100)->nullable();
            $table->unsignedInteger('views')->default(1);
            $table->timestamp('first_viewed_at');
            $table->timestamp('last_viewed_at');

            // Aik user ka aik document sirf aik row (counter usi row mein barhta hai)
            $table->unique(['doc_type', 'doc_id', 'user_id']);
            $table->index(['doc_type', 'doc_id', 'last_viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_views');
    }
};
