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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->integer('quantity');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate();
            $table->foreignId('artisan_id')->constrained('users')->cascadeOnUpdate();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate();
            $table->string('name_at_purchase');
            $table->text('description_at_purchase');
            $table->integer('price_at_purchase');
            $table->enum('item_status', ['pending', 'processing', 'shipped', 'completed', 'cancelled'])->default('pending');
            $table->boolean('is_processed')->default(0);
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->bigInteger('subtotal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
