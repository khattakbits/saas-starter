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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email', 190)->index();
            $table->string('subject', 255);
            $table->string('template_key', 60)->nullable();
            $table->string('status', 12);                       // queued | sent | failed
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            // No FK to users (central DB) — plain indexed column.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Body kabhi store nahi hoti

            $table->index(['status', 'id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
