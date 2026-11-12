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
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livestock_id')->constrained('livestocks')->cascadeOnDelete();
            $table->string('treatment_type', 50); // vaksinasi, pengobatan, vitamin, culling
            $table->string('medicine_name')->nullable(); // nama obat/vaksin
            $table->string('dosage', 100)->nullable(); // dosis atau takaran pemakaian
            $table->date('date'); // tanggal pelaksanaan
            $table->text('notes')->nullable(); // catatan tambahan / gejala klinis
            $table->timestamps();

            $table->index('date');
            $table->index('treatment_type');
            $table->index(['livestock_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};
