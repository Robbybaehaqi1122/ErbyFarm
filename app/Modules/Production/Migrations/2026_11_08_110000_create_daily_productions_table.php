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
        Schema::create('daily_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livestock_id')->constrained('livestocks')->cascadeOnDelete();
            $table->date('date'); // tanggal pencatatan
            $table->integer('egg_count')->default(0); // jumlah butir telur
            $table->decimal('egg_weight_kg', 8, 2)->default(0); // total berat telur (kg)
            $table->decimal('feed_consumed_kg', 8, 2)->default(0); // konsumsi pakan harian (kg)
            $table->decimal('water_consumed_liter', 10, 2)->default(0); // konsumsi air harian (liter)
            $table->integer('mortality')->default(0); // ayam mati/afkir hari ini
            $table->timestamps();

            $table->unique(['livestock_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_productions');
    }
};
