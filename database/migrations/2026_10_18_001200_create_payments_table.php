<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pembayaran untuk dokumen yang punya saldo (saat ini penjualan;
     * nanti pembelian, lihat requirements.md Bagian 4 dan 5).
     *
     * Relasi polimorfik `payable` dipakai agar satu tabel `payments`
     * dapat melayani piutang pelanggan maupun utang supplier tanpa
     * migrasi tambahan.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            // Metode opsional (mis. "Tunai", "Transfer").
            $table->string('method', 50)->nullable();
            // Referensi transaksi opsional (mis. nomor transfer).
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
