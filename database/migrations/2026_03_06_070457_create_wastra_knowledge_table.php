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
        Schema::create('wastra_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('nama_wastra'); // Contoh: Tenun Sidemen Cagcag
            $table->string('image_path');   // Simpan path: wastra/cagcag.jpg
            $table->text('deskripsi');      // Filosofi dan detail
            $table->json('panduan_sketsa'); // Simpan array langkah-langkah [1, 2, 3]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wastra_knowledge');
    }
};
