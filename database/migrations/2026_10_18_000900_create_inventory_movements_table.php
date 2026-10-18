<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buku besar pergerakan stok. Tidak ada angka stok yang boleh berubah
     * tanpa satu baris di tabel ini (requirements.md Bagian 4 & 6).
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Bertanda: positif = stok masuk, negatif = stok keluar.
            $table->decimal('quantity', 15, 2);
            // Saldo stok produk setelah mutasi ini, untuk penelusuran.
            $table->decimal('balance_after', 15, 2);
            $table->string('reason', 255);
            // Referensi transaksi sumber (mis. App\Models\Purchase).
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(
                ['business_id', 'product_id', 'occurred_at'],
                'inv_mov_business_product_time'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
