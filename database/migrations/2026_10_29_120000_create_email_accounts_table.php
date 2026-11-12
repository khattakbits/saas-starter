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
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('from_email', 190);
            $table->string('from_name', 100)->nullable();
            $table->string('host', 190);
            $table->unsignedSmallInteger('port');
            $table->string('encryption', 10)->default('tls');   // none | tls | ssl
            $table->string('username', 190)->nullable();
            $table->text('password')->nullable();               // 'encrypted' cast
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
