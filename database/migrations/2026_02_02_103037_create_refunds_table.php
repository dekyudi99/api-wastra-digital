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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancel_request_id')->constrained('cancel_requests')->cascadeOnUpdate();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate();
            $table->string('midtrans_refund_key')->unique();
            $table->bigInteger('amount');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->json('response');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
