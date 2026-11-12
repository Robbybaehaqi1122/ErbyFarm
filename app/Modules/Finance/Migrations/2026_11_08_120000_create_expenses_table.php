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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('category', 50); // pakan, obat_vaksin, operasional, gaji, lain_lain
            $table->decimal('amount', 15, 2); // total biaya pengeluaran (Rupiah)
            $table->date('date'); // tanggal transaksi
            $table->text('description')->nullable(); // keterangan detail
            $table->timestamps();

            $table->index('date');
            $table->index('category');
            $table->index(['farm_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
