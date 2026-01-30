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
        Schema::create('ai_insight_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mode'); // buyer / seller
            $table->string('endpoint'); // contoh: /api/ai/buyer-insight
            $table->string('payload_hash');
            $table->json('payload');
            $table->json('response');
            $table->unsignedTinyInteger('manual_score')->nullable(); // 1–5
            $table->text('manual_note')->nullable();
            $table->string('prompt_version');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_insight_logs');
    }
};
