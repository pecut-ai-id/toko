<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok disimpan sebagai kolom pada produk agar pembacaan cepat.
     * Kolom ini hanya boleh berubah lewat InventoryService, yang selalu
     * menulis satu baris inventory_movements pada transaksi yang sama.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock', 15, 2)->default(0)->after('business_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
