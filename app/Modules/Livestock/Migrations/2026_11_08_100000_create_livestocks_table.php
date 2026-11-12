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
        Schema::create('livestocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->integer('initial_population'); // populasi awal saat ayam dimasukkan
            $table->integer('current_population'); // populasi ayam hidup saat ini
            $table->date('entry_date'); // tanggal ayam masuk kandang
            $table->integer('age_weeks')->default(0); // umur ayam dalam minggu
            $table->string('status')->default('active'); // active, culled, empty
            $table->timestamps();

            $table->index(['farm_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livestocks');
    }
};
