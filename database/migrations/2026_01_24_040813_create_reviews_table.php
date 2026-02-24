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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained()
                ->cascadeOnDelete()
                ->unique();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->foreignId('artisan_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->tinyInteger('rating');

            $table->text('comment')->nullable();

            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
