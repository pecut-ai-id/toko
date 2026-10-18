<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pembatalan pembayaran (void) ditambahkan agar dokumen yang sudah
     * dibayar masih bisa dikoreksi tanpa menghapus baris pembayaran.
     *
     * Baris tetap ada sebagai jejak; perhitungan saldo hanya memakai
     * pembayaran yang belum di-void.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('user_id');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
