<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use KhattakBits\Core\Support\DocumentColumns;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_entries', function (Blueprint $table) {
            $table->id();
            DocumentColumns::add($table);
            $table->string('party', 120)->index();
            $table->decimal('amount', 14, 2)->default(0)->index();
            $table->date('posting_date')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_entries');
    }
};
