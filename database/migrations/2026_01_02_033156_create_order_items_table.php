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
            $table->string('name_at_purchase');
            $table->text('description_at_purchase');
            $table->integer('price_at_purchase');
            $table->bigInteger('subtotal');
            $table->string('status')->default(null);
            $table->boolean('is_commisioned')->default(0);
            $table->timestamps();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate();
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
