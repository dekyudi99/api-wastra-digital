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
        Schema::create('cancel_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancel_request_id')->constrained('cancel_requests')->cascadeOnUpdate();
            $table->enum('role', ['admin', 'artisan']);
            $table->boolean('approved');
            $table->string('note');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancel_approvals');
    }
};
