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
        Schema::create('deleted_documents', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 60);
            $table->unsignedBigInteger('doc_id');
            $table->string('title', 190);
            // No FK to users (central DB) — name is snapshotted below.
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->string('deleted_by_name', 100)->nullable();
            $table->unsignedTinyInteger('original_docstatus')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['doc_type', 'doc_id']);
            $table->index('created_at');
            $table->index('title');
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('prefix', 40)->primary();
            $table->unsignedBigInteger('last')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('deleted_documents');
    }
};
