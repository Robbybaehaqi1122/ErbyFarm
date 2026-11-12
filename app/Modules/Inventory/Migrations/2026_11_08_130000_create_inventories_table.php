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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('item_name'); // nama barang, misal: Pakan Layer BR-1
            $table->string('category', 50); // pakan, obat, vitamin, perlengkapan
            $table->decimal('stock_quantity', 12, 2)->default(0); // stok saat ini (kg/pcs/botol)
            $table->string('unit', 30); // satuan, misal: sak, kg, botol, pack
            $table->decimal('minimum_stock', 12, 2)->default(0); // batas minimal peringatan stok
            $table->timestamps();

            $table->index('category');
            $table->index('item_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
