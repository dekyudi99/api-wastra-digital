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
        Schema::create('cancel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnUpdate();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnUpdate();
            $table->string('reason');
            $table->enum('status', ['requested', 'seller_approved', 'admin_approved', 'rejected', 'completed'])->default('requested');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancel_requests');
    }
};
